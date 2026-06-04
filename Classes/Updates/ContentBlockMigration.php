<?php

declare(strict_types=1);

namespace NITSAN\NsThemeAgency\Updates;

use NITSAN\NsThemeAgency\Service\ContentBlockMigration as MigrationService;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

#[UpgradeWizard('t3agency_content_block_migration')]
final class ContentBlockMigration implements UpgradeWizardInterface
{
    private const CONTENT_BLOCK_CTYPES = [
        'nitsan_nsabout',
        'nitsan_nsbanner',
        'nitsan_nsheadline',
        'nitsan_nslogos',
        'nitsan_nsportfolio',
        'nitsan_nsteaser',
    ];

    private const LEGACY_ELEMENTS = [
        'ns_about',
        'ns_banner',
        'ns_headline',
        'ns_logos',
        'ns_portfolio',
        'ns_teaser',
    ];

    public function __construct(
        private readonly MigrationService $migrationService
    ) {}

    public function getTitle(): string
    {
        return 'NsThemeAgency: Content Block Migration';
    }

    public function getDescription(): string
    {
        return 'Migrate legacy flexform data of Agency content elements into Content Block fields so they can be edited in the backend.';
    }

    public function executeUpdate(): bool
    {
        $this->migrationService->migrate(self::LEGACY_ELEMENTS);

        return true;
    }

    public function updateNecessary(): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');

        $count = (int)$queryBuilder
            ->count('uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->in(
                    'CType',
                    $queryBuilder->createNamedParameter(self::CONTENT_BLOCK_CTYPES, Connection::PARAM_STR_ARRAY)
                ),
                $queryBuilder->expr()->neq(
                    'pi_flexform',
                    $queryBuilder->createNamedParameter('')
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
