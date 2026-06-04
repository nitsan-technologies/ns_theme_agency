<?php
declare(strict_types=1);

namespace NITSAN\NsThemeAgency\Updates;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use NITSAN\NsThemeAgency\Service\ContentBlockMigration as MigrationService;


#[UpgradeWizard('t3agency_content_block_migration')]
final class ContentBlockMigration implements UpgradeWizardInterface
{
    private array $elements = [];

    public function __construct()
    {   
        $this->elements = [
            'ns_about',
            'ns_banner',
            'ns_headline',
            'ns_logos',
            'ns_portfolio',
            'ns_teaser',
        ];
    }

    public function getTitle(): string
    {
        return 'NsThemeAgency: Content Block Migration';
    }

    public function getDescription(): string
    {
        return 'Migrate flexform content elements to content blocks. Please make sure to take a backup of your database before running this migration.';
    }

    public function executeUpdate(): bool
    {
        try {
            $migrationService = GeneralUtility::makeInstance(MigrationService::class);
            $migrationService->migrate($this->elements);
            return true;
        } catch (\Throwable $e) {
            \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump($e, __FILE__ . ' ' . __LINE__);
            die;
        }
    }

    public function updateNecessary(): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');

        $count = $queryBuilder
            ->count('uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->in(
                    'CType',
                    $queryBuilder->createNamedParameter($this->elements, Connection::PARAM_STR_ARRAY)
                )
            )
            ->executeQuery()
            ->fetchOne();
        
        return $count > 0;
    }

    public function getPrerequisites(): array
    {
        return [];
    }
}




