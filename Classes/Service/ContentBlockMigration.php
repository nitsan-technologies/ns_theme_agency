<?php

declare(strict_types=1);

namespace NITSAN\NsThemeAgency\Service;

use SimpleXMLElement;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentBlockMigration
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function migrate(array $elements)
    {
        foreach ($elements as $ce) {
            $cType                     = 'nitsan_' . str_replace('_', '', $ce);
            $flexFormPath              = GeneralUtility::getFileAbsFileName('EXT:ns_theme_agency/Configuration/FlexForms/');
            $originalXml               = $this->scanAndParseXmlFiles($flexFormPath, $ce);
            $fields                    = $this->getFieldsFromXml($originalXml);
            $registeredContentElements = $this->getContentElementsWithFlexForm($cType);
            if (! empty($registeredContentElements)) {
                foreach ($registeredContentElements as $element) {
                    if (isset($element['pi_flexform']) && $element['pi_flexform'] !== '') {
                        $pid     = $element['pid'];
                        $uid     = $element['uid'];
                        $langUid = $element['sys_language_uid'];
                        $xml     = simplexml_load_string($element['pi_flexform']);
                        $parsed  = [];

                        if (isset($xml->data->sheet)) {
                            foreach ($xml->data->sheet as $sheet) {
                                $fields = $sheet->language->field ?? null;
                                if ($fields) {
                                    $parsed = array_merge_recursive($parsed, $this->parseFields($fields));
                                }
                            }
                        }
                        $this->migrateFlexForm(
                            $uid,
                            $pid,
                            $cType,
                            $parsed,
                            $langUid
                        );
                    }
                }
            }
        }
    }

    private function parseFields($fields)
    {
        $result = [];

        foreach ($fields as $field) {
            $key = (string) $field['index'];

            // If it has a direct value (simple case)
            if (isset($field->value) && (string) $field->value['index'] === 'vDEF') {
                $result[$key] = html_entity_decode((string) $field->value);
            }

            // If it has nested <el> content
            if (isset($field->el)) {
                foreach ($field->el->field as $nestedField) {
                    $nestedKey = (string) $nestedField['index'];
                    $container = $nestedField->value['index'] == 'container' ? $nestedField->value->el->field : null;
                    try {
                        if ($container) {
                            // Ensure $result[$key] is an array before assigning to $result[$key][$nestedKey]
                            if (! isset($result[$key]) || ! is_array($result[$key])) {
                                $result[$key] = [];
                            }

                            $result[$key][$nestedKey] = $this->parseFields($container);
                        }
                    } catch (\Exception $e) {
                        \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump($result, __FILE__ . ' ' . __LINE__);
                    }
                }
            }
        }

        return $result;
    }

    private function getFieldsFromXml(SimpleXMLElement $xml): array
    {
        $fields = [];
        if ($xml) {
            $elElements = $xml->xpath('//el')[0];
            foreach ($elElements as $elementName => $elementData) {

                $fieldType = (string) $elementData->config->type;
                if ($fieldType === '') {
                    $fieldType = 'Text';
                }

                if ($fieldType || ($elementData->section)) {

                    if ($elementData->el->container) {

                        $collectionData = [
                            'identifier' => $elementName,
                            'label'      => (string) trim($elementData->title),
                            'type'       => 'Collection',
                        ];

                        $containerElements = $elementData->xpath('el/container/el')[0];

                        foreach ($containerElements as $celementName => $celementData) {
                            $fieldType = (string) $celementData->config->type;
                            if ($fieldType === '') {
                                $fieldType = 'Text';
                            }
                            $fieldData                  = $this->extractFieldProperties($celementData, $fieldType, $celementName);
                            $collectionData['fields'][] = $fieldData;
                        }

                        $fields[] = $collectionData;
                    } else {

                        $fieldData = $this->extractFieldProperties($elementData, $fieldType, $elementName);
                        $fields[]  = $fieldData;
                    }
                }
            }
        }
        return $fields;
    }

    private function handleFieldTypes(string $fieldType): array
    {
        return match ($fieldType) {
            'input' => ['type' => 'Text'],
            'text' => ['type' => 'Textarea'],
            'check' => ['type' => 'Checkbox'],
            'link' => ['type' => 'Link'],
            'select' => ['type' => 'Select'],
            default => [],
        };
    }

    private function scanAndParseXmlFiles(string $directory, string $targetFile): ?SimpleXMLElement
    {
        $files = scandir($directory);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'xml' && $file === $targetFile . '.xml') {
                $xmlContent = file_get_contents($directory . DIRECTORY_SEPARATOR . $file);
                return simplexml_load_string($xmlContent) ?: null;
            }
        }
        return null;
    }

    private function extractFieldProperties(SimpleXMLElement $elementData, string $fieldType, string $fieldName): array
    {
        $fieldData = [
            'identifier' => $fieldName,
            'label'      => (string) trim($elementData->label ?? $elementData->title),
        ];

        if ($fieldType) {
            $fieldData = array_merge($fieldData, $this->handleFieldTypes($fieldType));
        }
        if (! empty($elementData->onChange)) {
            $fieldData['onChange'] = (string) $elementData->onChange;
        }
        if (! empty($elementData->itemsProcFunc)) {
            $fieldData['itemsProcFunc'] = (string) $elementData->itemsProcFunc;
        }
        if (! empty($elementData->displayCond)) {
            $condition = (string) $elementData->displayCond;
            $condition = str_replace('sDEF.', '', $condition);
            if ($condition !== 'AND' || $condition !== 'OR') {
                $fieldData['displayCond']['AND'] = [$condition];
            }
            $fieldData['size'] = (int) $elementData->config->size;
        }
        $renderType = (string) $elementData->config->renderType;

        if ($fieldType === 'number' || $fieldType === 'Number') {
            $fieldData['type'] = 'Number';
        }

        if ($fieldType === 'input' && $renderType) {

            switch ($renderType) {
                case 'inputLink':
                    $fieldData['type'] = 'Link';
                    break;

                case 'colorpicker':
                    $fieldData['type'] = 'Color';
                    break;

                case 'inputDateTime':
                    $fieldData['type'] = 'DateTime';
                    break;
            }
        }

        if ($fieldType === 'inline') {
            $foreigntable = (string) $elementData->config->foreign_table;
            if ($foreigntable === 'sys_file_reference') {
                $fieldData['type'] = 'File';
                $allowedTypes      = '*';
                if (! empty($elementData->config->overrideChildTca->columns)) {
                    $allowedTypes = (string) $elementData->config->overrideChildTca->columns->uid_local->config->appearance->elementBrowserAllowed;
                }
                if ($allowedTypes) {
                    $allowedTypes = GeneralUtility::trimExplode(',', $allowedTypes);
                    foreach ($allowedTypes as $type) {
                        $fieldData['allowed'][] = $type;
                    }
                }
            }
        }

        if ($fieldType === 'text') {
            $fieldData['rows']           = isset($elementData->config->rows) ? (int) (string) $elementData->config->rows : null;
            $fieldData['enableRichtext'] = ! empty($elementData->config->enableRichtext)
            ? filter_var($elementData->config->enableRichtext, FILTER_VALIDATE_BOOLEAN)
            : false;
        }

        if ($fieldType === 'select') {
            $fieldData['renderType'] = (string) $renderType;
            $items                   = (array) $elementData->config->items;

            if ($items) {
                $items = $items['numIndex'];
                foreach ($items as $option) {
                    $fieldData['items'][] = [
                        'label' => (string) $option->numIndex[0],
                        'value' => (string) $option->numIndex[1],
                    ];
                }
            }
        }

        $validations = (string) $elementData->config->eval;

        if ($validations) {
            $fieldData = array_merge($fieldData, $this->handleValidations($validations));
        }

        if ($elementData->config->allowedTypes && $elementData->config->allowedTypes->numIndex) {

            $allowedTypes = (array) $elementData->config->allowedTypes->numIndex;

            if ($allowedTypes) {
                foreach ($allowedTypes as $type) {
                    if (is_string($type)) {
                        $fieldData['allowed'][] = $type;
                    }
                }
            }
        }

        $minitems = (int) $elementData->config->minitems;
        if ($minitems > 0) {
            $fieldData['minitems'] = $minitems;
        }

        $maxitems = (int) $elementData->config->maxitems;
        if ($maxitems > 0) {
            $fieldData['maxitems'] = $maxitems;
        }

        return $fieldData;
    }

    private function handleValidations(string $validations): array
    {
        $validationArray  = GeneralUtility::trimExplode(',', $validations);
        $validationFields = [];

        foreach ($validationArray as $validation) {
            switch ($validation) {
                case 'required':
                    $validationFields['required'] = true;
                    break;
            }
        }

        return $validationFields;
    }

    public function migrateFlexForm(
        $uid,
        $pid,
        $cType,
        $parsed,
        $langUid
    ) {

        switch ($cType) {
            case 'nitsan_nsabout':
                $this->migrateAbout($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsbanner':
                $this->migrateBanner($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsheadline':
                $this->migrateHeadline($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nslogos':
                $this->migrateLogos($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsportfolio':
                $this->migratePortfolio($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsteaser':
                $this->migrateTeaser(  $uid,  $pid, $cType,  $parsed,  $langUid);
                break;

            default:
                break;
        }
    }

private function migrateAbout(int $uid, int $pid, string $cType, array $parsed, int $langUid): void
{
   
    $data = [
        'CType'              => $cType,
        'headline'           => $parsed['headline'] ?? '',
        'subHeadline'        => $parsed['subHeadline'] ?? '',
        'check'              => (int)($parsed['check'] ?? 0),
        'text'               => $parsed['text'] ?? '',
        'sys_language_uid'   => $langUid,
        'pid'                => $pid,
    ];

    $this->updateTtContent($data, $uid, $pid);

  
    if (!empty($parsed['gallery']) && is_array($parsed['gallery'])) {

        $this->deleteChildRecords($uid, $langUid, 'gallery');

        foreach ($parsed['gallery'] as $galleryItem) {
            $imagePath = $galleryItem['image'] ?? '';
            if (!empty($imagePath) && $imagePath !== '0' && !str_starts_with($imagePath, 't3://file')) {
                $imagePath = 't3://file?uid=' . (int)$imagePath;
            }

            $itemData = [
                'pid'                      => $pid,
                'foreign_table_parent_uid' => $uid,
                'sys_language_uid'         => $langUid,
                'image'                    => $imagePath,
                'title'                    => $galleryItem['title'] ?? '',
                'subtitle'                 => $galleryItem['subtitle'] ?? '',
                'text'                     => $galleryItem['text'] ?? '',
            ];

            $this->insertChildRecord($itemData, 'gallery');
        }
    }
}


    private function migrateBanner(int $uid, int $pid, string $cType, array $parsed, int $langUid): void
    {
        $fileUid = (int)($parsed['image'] ?? 0);
        if ($fileUid > 0) {
            $this->replaceFileReference($uid, $pid, $fileUid, 'image', $langUid);
        }

        $data = [
            'CType'              => $cType,
            'title'              => $parsed['title'] ?? '',
            'subtitle'           => $parsed['subtitle'] ?? '',
            'btntext'            => $parsed['btntext'] ?? '',
            'scrollid'           => $parsed['scrollid'] ?? ($parsed['btnlink'] ?? ''),
            'editlock'           => 0,
            'sys_language_uid'   => $langUid,
            'pid'                => $pid,
        ];

        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateHeadline(int $uid, int $pid, string $cType, array $parsed, int $langUid): void
    {

        $data = [
            'CType'              => $cType,
            'title'              => $parsed['title'] ?? '',
            'subtitle'           => $parsed['subtitle'] ?? '',
           
            'sys_language_uid'   => $langUid,
            'pid'                => $pid,
        ];

        $this->updateTtContent($data, $uid, $pid);

    }

    private function migrateLogos(int $uid, int $pid, string $cType, array $parsed, int $langUid): void
    {

        $imageUids = [];
        if (! empty($parsed['image'])) {
            $images = (array) $parsed['image'];
            foreach ($images as $img) {
                $imageUids[] = (int) $img;
            }
        }

        if (empty($imageUids)) {
            $imageUids[] = 0;
        }

        $data = [
            'CType'              => $cType,
            'image'              => implode(',', $imageUids),
            'space_before_class' => $parsed['space_before_class'] ?? '',
            'space_after_class'  => $parsed['space_after_class'] ?? '',
            'sys_language_uid'   => $langUid,
            'pid'                => $pid,
        ];

        $this->updateTtContent($data, $uid, $pid);
    }

    private function migratePortfolio(int $uid, int $pid, string $cType, array $parsed, int $langUid): void
    {
        $data = [
            'CType'              => $cType,
            'title'              => $parsed['title'] ?? '',
            'subtitle'           => $parsed['subtitle'] ?? '',
            'check'              => (int) ($parsed['check'] ?? 0),
            'sys_language_uid'   => $langUid,
            'pid'                => $pid,
        ];

        $this->updateTtContent($data, $uid, $pid);

        if (!empty($parsed['portfolio']) ) {
            foreach ($parsed['portfolio'] as $portfolioItem) {
                $imagePath = $portfolioItem['image'] ?? '';
                
                if (!str_starts_with($imagePath, 't3://file')) {
                    $imagePath = 't3://file?uid=' . (int)$imagePath;
                }

                $itemData = [
                    'pid'                      => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'sys_language_uid'         => $langUid,
                    'image'                    => $imagePath,
                    'title'                    => $portfolioItem['title'] ?? '',
                    'subtitle'                 => $portfolioItem['subtitle'] ?? '',
                    'text'                     => $portfolioItem['text'] ?? '',
                ];

                $this->insertChildRecord($itemData, 'portfolio');
            }
        }
    }

    private function migrateTeaser(int $uid, int $pid, string $cType, array $parsed, int $langUid): void
{
    $data = [
        'CType'              => $cType,
        'headline'           => $parsed['headline'] ?? '',
        'subHeadline'        => $parsed['subHeadline'] ?? '',
        'check'              => (int)($parsed['check'] ?? 0),
        'otherText'          => $parsed['otherText'] ?? '',
        'sys_language_uid'   => $langUid,
        'pid'                => $pid,
    ];

    $this->updateTtContent($data, $uid, $pid);

    $this->deleteChildRecords($uid, $langUid, 'teaser_items');
    $items = [];
    if (!empty($parsed['teaser_items'])) {
        $items = $parsed['teaser_items'];
    } elseif (!empty($parsed['teaser']) && is_array($parsed['teaser'])) {
        $items = $parsed['teaser'];
    }

    foreach ($items as $item) {
        $imagePath = $item['image'] ?? '';
        if (!empty($imagePath) && $imagePath !== '0' && !str_starts_with($imagePath, 't3://file')) {
            $imagePath = 't3://file?uid=' . (int)$imagePath;
        }

        $itemData = [
            'pid'                      => $pid,
            'foreign_table_parent_uid' => $uid,
            'sys_language_uid'         => $langUid,
            'check'                    => (int)($item['check'] ?? 0),
            'image'                    => $imagePath,
            'icon'                     => $item['icon'] ?? '',
            'title'                    => $item['title'] ?? '',
            'text'                     => $item['text'] ?? '',
            'twitter'                  => $item['twitter'] ?? '',
            'facebook'                 => $item['facebook'] ?? '',
            'linkedin'                 => $item['linkedin'] ?? '',
        ];

        $this->insertChildRecord($itemData, 'teaser_items');
    }
}




    private function updateTtContent($data, $uid, $pid)
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder
            ->update('tt_content')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid))
            )
            ->andWhere(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid))
            );
        foreach ($data as $key => $val) {
            $queryBuilder->set($key, $val);
        }
        $queryBuilder->set('pi_flexform', '');
        $queryBuilder->set('editlock', 0);
        $queryBuilder->executeStatement();
    }

    /**
     * @return list<array{uid: int, pi_flexform: string, pid: int, sys_language_uid: int}>
     */
    private function getContentElementsWithFlexForm(string $cType): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
        $result = $queryBuilder
            ->select('uid', 'pi_flexform', 'pid', 'sys_language_uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'CType',
                    $queryBuilder->createNamedParameter($cType)
                ),
                $queryBuilder->expr()->neq(
                    'pi_flexform',
                    $queryBuilder->createNamedParameter('')
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return $result ?: [];
    }

    private function insertChildRecord(array $data, string $tableName): void
    {
        $this->connectionPool
            ->getQueryBuilderForTable($tableName)
            ->insert($tableName)
            ->values($data)
            ->executeStatement();
    }

    private function deleteChildRecords(int $parentId, int $langId, string $tableName): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($tableName);
        $queryBuilder
            ->delete($tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'foreign_table_parent_uid',
                    $queryBuilder->createNamedParameter($parentId, Connection::PARAM_INT)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($langId, Connection::PARAM_INT)
                )
            )
            ->executeStatement();
    }

    private function replaceFileReference(
        int $contentUid,
        int $pid,
        int $fileUid,
        string $fieldName,
        int $languageId = 0
    ): void {
        if ($fileUid <= 0) {
            return;
        }

        $referenceConnection = $this->connectionPool->getConnectionForTable('sys_file_reference');
        $referenceConnection->delete(
            'sys_file_reference',
            [
                'uid_foreign' => $contentUid,
                'tablenames' => 'tt_content',
                'fieldname' => $fieldName,
            ]
        );

        $referenceConnection->insert(
            'sys_file_reference',
            [
                'pid' => $pid,
                'uid_local' => $fileUid,
                'uid_foreign' => $contentUid,
                'tablenames' => 'tt_content',
                'fieldname' => $fieldName,
                'sorting_foreign' => 1,
                'sys_language_uid' => $languageId,
                'tstamp' => time(),
                'crdate' => time(),
            ]
        );
    }
}
