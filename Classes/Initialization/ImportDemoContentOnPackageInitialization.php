<?php

declare(strict_types=1);

namespace NITSAN\NsThemeAgency\Initialization;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Finder\Finder;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\Exception\SiteConfigurationWriteException;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Package\Event\PackageInitializationEvent;
use TYPO3\CMS\Core\Package\Initialization\ImportExtensionDataOnPackageInitialization;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Impexp\Utility\ImportExportUtility;

/**
 * Imports demo page tree only on an empty installation.
 *
 * Demo XML lives in Initialisation/Demo/ so core's ImportContentOnPackageInitialization
 * never auto-imports it (that path only looks for Initialisation/data.xml).
 * Existing page trees are never touched.
 */
final class ImportDemoContentOnPackageInitialization implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const EXTENSION_KEY = 'ns_theme_agency';
    private const REGISTRY_NAMESPACE = 'extensionDataImport';
    private const REGISTRY_KEY_SUFFIX = 'Initialisation/Demo/dataImported';

    public function __construct(
        private readonly Registry $registry,
        private readonly ConnectionPool $connectionPool,
        private readonly ImportExportUtility $importExportUtility,
        private readonly SiteConfiguration $siteConfiguration,
        private readonly SiteWriter $siteWriter,
    ) {}

    #[AsEventListener(
        identifier: 'ns-theme-agency/import-demo-content',
        after: ImportExtensionDataOnPackageInitialization::class,
    )]
    public function __invoke(PackageInitializationEvent $event): void
    {
        if ($event->getExtensionKey() !== self::EXTENSION_KEY) {
            return;
        }

        $packagePath = $event->getPackage()->getPackagePath();
        $registryKey = $this->buildRegistryKey($packagePath);

        if ($this->registry->get(self::REGISTRY_NAMESPACE, $registryKey)) {
            return;
        }

        $importFile = $packagePath . 'Initialisation/Demo/data.xml';
        if (!is_file($importFile)) {
            return;
        }

        $pageCount = $this->connectionPool
            ->getConnectionForTable('pages')
            ->count('uid', 'pages', ['deleted' => 0]);

        // Existing tree: mark done and never import/overwrite.
        if ($pageCount > 0) {
            $this->registry->set(self::REGISTRY_NAMESPACE, $registryKey, 1);
            $this->logger?->info(
                'Skipped ns_theme_agency demo import because {count} page(s) already exist.',
                ['count' => $pageCount]
            );
            return;
        }

        Bootstrap::initializeBackendAuthentication();

        try {
            $importResult = $this->importExportUtility->importT3DFile($importFile, 0);
            $this->registry->set(self::REGISTRY_NAMESPACE, $registryKey, 1);
            $this->importSiteConfiguration($event, $packagePath);
            $event->addStorageEntry(__CLASS__, [
                'importResult' => $importResult,
                'importFile' => $importFile,
            ]);
        } catch (\Throwable $e) {
            $this->logger?->warning(
                'ns_theme_agency demo import failed: {message}',
                ['message' => $e->getMessage(), 'exception' => $e]
            );
        }
    }

    private function buildRegistryKey(string $packagePath): string
    {
        return PathUtility::stripPathSitePrefix($packagePath) . self::REGISTRY_KEY_SUFFIX;
    }

    private function importSiteConfiguration(PackageInitializationEvent $event, string $packagePath): void
    {
        $importAbsFolder = $packagePath . 'Initialisation/Site';
        if (!is_dir($importAbsFolder)) {
            return;
        }

        $destinationFolder = Environment::getConfigPath() . '/sites';
        GeneralUtility::mkdir($destinationFolder);
        $existingSites = $this->siteConfiguration->resolveAllExistingSites(false);

        $finder = GeneralUtility::makeInstance(Finder::class);
        $finder->directories()->ignoreUnreadableDirs()->depth(0)->in($importAbsFolder);
        if (!$finder->hasResults()) {
            return;
        }

        foreach ($finder as $siteConfigDirectory) {
            $siteIdentifier = $siteConfigDirectory->getBasename();
            if (isset($existingSites[$siteIdentifier])) {
                $this->logger?->warning(
                    'Skipped importing site configuration {site} because it already exists.',
                    ['site' => $siteIdentifier]
                );
                continue;
            }
            $targetDir = $destinationFolder . '/' . $siteIdentifier;
            if ($this->registry->get('siteConfigImport', $siteIdentifier) || is_dir($targetDir)) {
                continue;
            }
            GeneralUtility::mkdir($targetDir);
            GeneralUtility::copyDirectory($siteConfigDirectory->getPathname(), $targetDir);
            $this->registry->set('siteConfigImport', $siteIdentifier, 1);
        }

        $import = $this->importExportUtility->getImport();
        if ($import === null) {
            return;
        }

        $importedPages = $import->getImportMapId()['pages'] ?? [];
        $newSites = array_diff_key(
            $this->siteConfiguration->resolveAllExistingSites(false),
            $existingSites
        );

        foreach ($newSites as $newSite) {
            $exportedPageId = $newSite->getRootPageId();
            $importedPageId = $importedPages[$exportedPageId] ?? null;
            if ($importedPageId === null) {
                continue;
            }
            $configuration = $this->siteConfiguration->load($newSite->getIdentifier());
            $configuration['rootPageId'] = $importedPageId;
            try {
                $this->siteWriter->write($newSite->getIdentifier(), $configuration);
            } catch (SiteConfigurationWriteException $e) {
                $this->logger?->warning(
                    'Could not write site configuration {site}: {message}',
                    [
                        'site' => $newSite->getIdentifier(),
                        'message' => $e->getMessage(),
                    ]
                );
            }
        }
    }
}
