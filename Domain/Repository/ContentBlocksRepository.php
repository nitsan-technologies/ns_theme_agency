<?php

declare(strict_types=1);

namespace NITSAN\NsThemeAgency\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;

class ContentBlocksRepository
{
    protected ConnectionPool $connectionPool;

    public function __construct()
    {
        $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /**
     * @return array
     */
    public function getRegisteredContentElements(string $cType): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
        $result = $queryBuilder
            ->select('uid', 'pi_flexform','pid', 'sys_language_uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter($cType))
            )
            ->executeQuery()->fetchAllAssociative();
        if ($result) {
            return $result;
        }
        return [];
    }

    public function insertDataWithDataHandler(array $data, string $randomString, string $tableName): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($tableName);
        $queryBuilder->insert($tableName)->values($data)->executeStatement();
    }

    public function deleteOldRecord(int $parentId, int $langId, string $tableName): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($tableName);
        $queryBuilder
        ->delete($tableName)
        ->where(
            $queryBuilder->expr()->eq('foreign_table_parent_uid', $queryBuilder->createNamedParameter($parentId, Connection::PARAM_INT))
        )
        ->andWhere(
            $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($langId, Connection::PARAM_INT))
        )
        ->executeStatement();
    }
}