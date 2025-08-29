<?php

namespace NITSAN\NsThemeT3karma\Service;

use SimpleXMLElement;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use NITSAN\NsThemeT3karma\Domain\Repository\ContentBlocksRepository;


class ContentBlockMigration
{
    private $contentBlocksRepository;

    private $connectionPool;

    public function __construct()
    {
        $this->contentBlocksRepository = GeneralUtility::makeInstance(ContentBlocksRepository::class);
        $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
    }

    public function migrate(array $elements)
    {
        foreach ($elements as $ce) {
            $cType = 'nitsan_' . str_replace('_', '', $ce);
            $flexFormPath = GeneralUtility::getFileAbsFileName('EXT:ns_theme_t3karma/Configuration/FlexForms/');
            $originalXml = $this->scanAndParseXmlFiles($flexFormPath, $ce);
            $fields = $this->getFieldsFromXml($originalXml);
            $registeredContentElements = $this->contentBlocksRepository->getRegisteredContentElements($ce);
            if (!empty($registeredContentElements)) {
                foreach ($registeredContentElements as $element) {
                    if (isset($element['pi_flexform']) && $element['pi_flexform'] !== '') {
                        $pid = $element['pid'];
                        $uid = $element['uid'];
                        $langUid = $element['sys_language_uid'];
                        $xml = simplexml_load_string($element['pi_flexform']);
                        $parsed = [];

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
                            if (!isset($result[$key]) || !is_array($result[$key])) {
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

                $fieldType = (string)$elementData->config->type;
                if ($fieldType === '') {
                    $fieldType = 'Text';
                }

                if ($fieldType || ($elementData->section)) {

                    if ($elementData->el->container) {

                        $collectionData = [
                            'identifier' => $elementName,
                            'label' => (string)trim($elementData->title),
                            'type' => 'Collection',
                        ];

                        $containerElements = $elementData->xpath('el/container/el')[0];

                        foreach ($containerElements as $celementName => $celementData) {
                            $fieldType = (string)$celementData->config->type;
                            if ($fieldType === '') {
                                $fieldType = 'Text';
                            }
                            $fieldData = $this->extractFieldProperties($celementData, $fieldType, $celementName);
                            $collectionData['fields'][] = $fieldData;
                        }

                        $fields[] = $collectionData;
                    } else {

                        $fieldData = $this->extractFieldProperties($elementData, $fieldType, $elementName);
                        $fields[] = $fieldData;
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
            'label' => (string)trim($elementData->label ?? $elementData->title),
        ];

        if ($fieldType) {
            $fieldData = array_merge($fieldData, $this->handleFieldTypes($fieldType));
        }
        if (!empty($elementData->onChange)) {
            $fieldData['onChange'] = (string)$elementData->onChange;
        }
        if (!empty($elementData->itemsProcFunc)) {
            $fieldData['itemsProcFunc'] = (string)$elementData->itemsProcFunc;
        }
        if (!empty($elementData->displayCond)) {
            $condition = (string)$elementData->displayCond;
            $condition = str_replace('sDEF.', '', $condition);
            if ($condition !== 'AND' || $condition !== 'OR') {
                $fieldData['displayCond']['AND'] = [$condition];
            }
            $fieldData['size'] = (int)$elementData->config->size;
        }
        $renderType = (string)$elementData->config->renderType;

        if ($fieldType === 'number' || $fieldType === 'Number') {
            $fieldData['type'] = 'Number';
        }

        if ($fieldType === 'input' && $renderType) {

            switch ($renderType) {
                case 'inputLink':
                    $fieldData['type'] =  'Link';
                    break;

                case 'colorpicker':
                    $fieldData['type'] =  'Color';
                    break;

                case 'inputDateTime':
                    $fieldData['type'] =  'DateTime';
                    break;
            }
        }

        if ($fieldType === 'inline') {
            $foreigntable = (string)$elementData->config->foreign_table;
            if ($foreigntable === 'sys_file_reference') {
                $fieldData['type'] = 'File';
                $allowedTypes = '*';
                if (!empty($elementData->config->overrideChildTca->columns)) {
                    $allowedTypes =  (string)$elementData->config->overrideChildTca->columns->uid_local->config->appearance->elementBrowserAllowed;
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
            $fieldData['rows'] = isset($elementData->config->rows) ? (int)(string)$elementData->config->rows : null;
            $fieldData['enableRichtext'] = !empty($elementData->config->enableRichtext)
                ? filter_var($elementData->config->enableRichtext, FILTER_VALIDATE_BOOLEAN)
                : false;
        }

        if ($fieldType === 'select') {
            $fieldData['renderType'] = (string)$renderType;
            $items = (array)$elementData->config->items;

            if ($items) {
                $items = $items['numIndex'];
                foreach ($items as $option) {
                    $fieldData['items'][] = [
                        'label' => (string)$option->numIndex[0],
                        'value' => (string)$option->numIndex[1],
                    ];
                }
            }
        }

        $validations = (string)$elementData->config->eval;

        if ($validations) {
            $fieldData = array_merge($fieldData, $this->handleValidations($validations));
        }

        if ($elementData->config->allowedTypes && $elementData->config->allowedTypes->numIndex) {

            $allowedTypes = (array)$elementData->config->allowedTypes->numIndex;

            if ($allowedTypes) {
                foreach ($allowedTypes as $type) {
                    if (is_string($type)) {
                        $fieldData['allowed'][] = $type;
                    }
                }
            }
        }

        $minitems = (int)$elementData->config->minitems;
        if ($minitems > 0) {
            $fieldData['minitems'] = $minitems;
        }

        $maxitems = (int)$elementData->config->maxitems;
        if ($maxitems > 0) {
            $fieldData['maxitems'] = $maxitems;
        }

        return $fieldData;
    }


    private function handleValidations(string $validations): array
    {
        $validationArray = GeneralUtility::trimExplode(',', $validations);
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
            case 'nitsan_nsaccordions':
                $this->migrateAccordions($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsalert':
                $this->migrateAlert($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsanimationteaser':
                $this->migrateAnimationTeaser($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsbanner':
                $this->migrateBanner($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsbeforeafter':
                $this->migrateBeforeAfter($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsblockquotes':
                $this->migrateBlockQuotes($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsblogmedia':
                $this->migrateBlogMedia($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsbottomcontent':
                $this->migrateBottomContent($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nscalltoaction':
                $this->migrateCallToAction($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nscard':
                $this->migrateCard($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nscard':
                $this->migrateCard($uid, $pid, $cType, $parsed, $langUid);
            case 'nitsan_nscascadingimage':
                $this->migrateCascadingImage($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nscontentrotater':
                $this->migrateContentRotator($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nscountdowns':
                $this->migrateCountDowns($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nscounters':
                $this->migrateCounters($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsdivider':
                $this->migrateDivider($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsdropdownbutton':
                $this->migrateDropdownButton($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsfooter':
                $this->migrateFooter($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsfullwidthsemi':
                $this->migrateFullWidthSemi($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsheaderslider':
                $this->migrateHeaderSlider($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nshistory':
                $this->migrateHistory($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nshotspots':
                $this->migrateHotspots($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsiconbox':
                $this->migrateIconBox($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsiconlist':
                $this->migrateIconList($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsimageanimation':
                $this->migrateImageAnimation($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsimageframes':
                $this->migrateImageFrames($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsimagegallery':
                $this->migrateImageGallery($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsimageviewer':
                $this->migrateImageViewer($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nslandingdemo':
                $this->migrateLandingDemo($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nslightboxes':
                $this->migrateLightBoxes($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nslist':
                $this->migrateList($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsmap':
                $this->migrateMap($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsmaps':
                $this->migrateMaps($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsmasonryimg':
                $this->migrateMasonryImg($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsmedias':
                $this->migrateMedias($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsmodal':
                $this->migrateModal($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsofficedetails':
                $this->migrateOfficeDetails($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsparticles':
                $this->migrateParticles($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nspopover':
                $this->migratePopover($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nspricingtables':
                $this->migratePricingTables($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsprocess':
                $this->migrateProcess($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsprogressbars':
                $this->migrateProgressBars($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsprojectdetailblock':
                $this->migrateProjectDetailBlock($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsrandomimage':
                $this->migrateRandomImage($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsreadmore':
                $this->migrateReadMore($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nssectionparallax':
                $this->migrateSectionParallax($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nssectionteaser':
                $this->migrateSectionTeaser($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsservicelist':
                $this->migrateServicelist($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsshapedivider':
                $this->migrateShapedivider($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsslider':
                $this->migrateSlider($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nssocial':
                $this->migrateSocial($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsstarrating':
                $this->migrateStarRating($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsstickycontent':
                $this->migrateStickyContent($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nstabs':
                $this->migrateTabs($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsteam':
                $this->migrateTeams($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsteaser':
                $this->migrateTeaser($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsteaserimage':
                $this->migrateTeaserImage($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nstestimonials':
                $this->migrateTestimonials($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nstextanimation':
                $this->migrateTextAnimation($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nsthreeimgblock':
                $this->migrateThreeImgBlock($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nstoggles':
                $this->migrateToggles($uid, $pid, $cType, $parsed, $langUid);
                break;
            case 'nitsan_nswordrotator':
                $this->migrateWordRotator($uid, $pid, $cType, $parsed, $langUid);
                break;
            default:
                break;
        }
    }

    private function migrateAccordions($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'style' => $parsed['accordionsType'] ?? '',
            'accordionsSize' => $parsed['accordionsSize'] ?? '',
            'oneAtATime' => (int)($parsed['oneAtATime'] ?? 0),
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'colorbox' => $parsed['colorbox'] ?? '',
            'accordionItem' => isset($parsed['accordionItem']) ? count($parsed['accordionItem']) : 0
        ];
        $this->updateTtContent($data, $uid, $pid);

        if (!empty($parsed['accordionItem'])) {
            foreach ($parsed['accordionItem'] as $accordionItem) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'sys_language_uid' => $langUid,
                    'title' => $accordionItem['title'] ?? '',
                    'content' => $accordionItem['content'] ?? '',
                    'icon' => $accordionItem['icon'] ?? '',
                    'option' => $accordionItem['title'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'accordionItem');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'accordionItem');
            }
        }
    }

    private function migrateAlert($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'alert_variance' => $parsed['variance'] ?? '',
            'size' => $parsed['size'] ?? '',
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'addclosebutton' => (int)($parsed['addclosebutton'] ?? 0),
            'icon' => $parsed['icon'] ?? '',
            'bodytext' => $parsed['description'] ?? ''
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateAnimationTeaser($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'image' => (int)($parsed['image'] ?? 0),
            'animation' => $parsed['animationslist'] ?? '',
            'title' => $parsed['title'] ?? '',
            'bodytext' => $parsed['content'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateBanner($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'style' => $parsed['accordionsType'] ?? '',
            'animation' => $parsed['animation'] ?? '',
            'backgroundOverlay' => $parsed['backgroundOverlay'] ?? '',
            'displayBreadcumb' => $parsed['displayBreadcumb'] ?? '',
            'height' => $parsed['height'] ?? '',
            'contentBackgroundOverlay' => $parsed['contentBackgroundOverlay'] ?? '',
            'horizontalAlignment' => $parsed['horizontalAlignment'] ?? '',
            'verticalAlignment' => $parsed['verticalAlignment'] ?? '',
            'text_1' => $parsed['title'] ?? '',
            'content' => $parsed['content'] ?? '',
            'text_2' => $parsed['btntext'] ?? '',
            'link' => $parsed['btnlink'] ?? '',
            'btnColor' => $parsed['btnColor'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateBeforeAfter($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'before_after_variance' => $parsed['variance'] ?? '',
            'enableBorderRadius' => (int)($parsed['enableBorderRadius'] ?? 0),
            'clickhandler' => (int)($parsed['clickhandler'] ?? 0),
            'sliderhover' => (int)($parsed['displaysliderhoverBreadcumb'] ?? 0),
            'enableOverlay' => (int)($parsed['overlay'] ?? 0),
            'text_1' => $parsed['beforelabel'] ?? '',
            'text_2' => $parsed['afterlabel'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateCallToAction($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'call_to_action_style' => $parsed['calltoactionType'] ?? '',
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'animation' => $parsed['animation'] ?? '',
            'call_to_action_border' => $parsed['border'] ?? '',
            'colorbox' => $parsed['colorbox'] ?? '',
            'iscenteral' => (int)($parsed['iscenteral'] ?? 0),
            'bodytext' => $parsed['content'] ?? '',
            'buttonColor' => $parsed['buttoncolor'] ?? '',
            'text_1' => $parsed['buttontext'] ?? '',
            'link' => $parsed['buttonlink'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateBlockQuotes($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'blockquotes_style' => $parsed['blockquotesType'] ?? '',
            'colorPicker' => $parsed['colorbox'] ?? '',
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'bodytext' => $parsed['content'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateBlogMedia($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'blockquotes_style' => $parsed['variance'] ?? '',
            'blockquote' => $parsed['blockquote'] ?? '',
            'text_1' => $parsed['linktext'] ?? '',
            'link' => $parsed['link'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateCard($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'cardtype' => $parsed['cardtype'] ?? '',
            'enableBorderRadius' => (int)($parsed['enableBorderRadius'] ?? 0),
            'enableSharpBorder' => (int)($parsed['linktext'] ?? 0),
            'imagestyle' => $parsed['imagestyle'] ?? '',
            'shadowstyle' => $parsed['shadowstyle'] ?? '',
            'boderstyle' => $parsed['boderstyle'] ?? '',
            'flipcardstyle' => $parsed['flipcardstyle'] ?? '',
            'card_color' => $parsed['color'] ?? '',
            'cardbordercolor' => $parsed['cardbordercolor'] ?? '',
            'animation' => $parsed['animation'] ?? '',
            'icon' => $parsed['icon'] ?? '',
            'text_1' => $parsed['title'] ?? '',
            'text' => $parsed['text'] ?? '',
            'text_2' => $parsed['headline'] ?? '',
            'text_3' => $parsed['subheadline'] ?? '',
            'textflip' => $parsed['textflip'] ?? '',
            'buttonColor' => $parsed['buttonColor'] ?? '',
            'text_4' => $parsed['btntext'] ?? '',
            'link' => $parsed['btnlink'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateCascadingImage($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'cascadingimage_variance' => $parsed['variance'] ?? '',
            'enableBorderRadius' => (int)($parsed['enableBorderRadius'] ?? 0),
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'animationeffect' => $parsed['animationeffect'] ?? '',
            'nodots' => (int)($parsed['nodots'] ?? 0),
            'cas_color' => $parsed['color'] ?? '',
            'setPosition' => $parsed['position'] ?? '',
            'boxshadow' => $parsed['boxshadow'] ?? '',
            'text' => $parsed['text'] ?? '',
            'text_1' => $parsed['popup'] ?? '',
            'popuptext' => html_entity_decode($parsed['popuptext'] ?? ''),
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateBottomContent($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'text_1' => $parsed['LocationTitle'] ?? '',
            'bodytext' => $parsed['LocationDesc'] ?? '',
            'text_2' => $parsed['CallTitle'] ?? '',
            'text_3' => $parsed['PhoneNumber'] ?? '',
            'text_4' => $parsed['SLinkTitle'] ?? '',
            'text_5' => $parsed['linkTitle1'] ?? '',
            'link1' => $parsed['link1'] ?? '',
            'text_6' => $parsed['linkTitle2'] ?? '',
            'link2' => $parsed['link2'] ?? '',
            'text_7' => $parsed['linkTitle3'] ?? '',
            'link3' => $parsed['link3'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateContentRotator($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'rotator_layout' => $parsed['layout'] ?? '',
            'enableBorderRadius' => (int)($parsed['enableBorderRadius'] ?? 0),
            'text_1' => $parsed['RotateText'] ?? '',
            'contentcolor' => $parsed['contentcolor'] ?? '',
            'rotateDegree' => $parsed['rotateDegree'] ?? '',
            'text_2' => $parsed['top'] ?? '',
            'text_3' => $parsed['left'] ?? '',
            'text_4' => $parsed['RotateText2'] ?? '',
            'contentcolor2' => $parsed['contentcolor2'] ?? '',
            'rotateDegree2' => (int)($parsed['rotateDegree2'] ?? 0),
            'text_5' => $parsed['top2'] ?? '',
            'text_6' => $parsed['right'] ?? '',
            'reversedOption' => $parsed['reversedOption'] ?? '',
            'rotateItem' =>  isset($parsed['rotateItem']) ? count($parsed['rotateItem']) : 0
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['rotateItem'])) {
            foreach ($parsed['rotateItem'] as $rotateItem) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'RotateText' => $rotateItem['RotateText'] ?? '',
                    'rotateDegree' => $rotateItem['rotateDegree'] ?? '',
                    'contentcolor' => $rotateItem['contentcolor'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'rotateItem');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'rotateItem');
            }
        }
    }

    private function migrateCountDowns($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'countdown_variance' => $parsed['variance'] ?? '',
            'countdown_color' => $parsed['color'] ?? '',
            'border' => $parsed['border'] ?? '',
            'enableBorderRadius' => (int)($parsed['enableBorderRadius'] ?? 0),
            'bordercolor' => $parsed['bordercolor'] ?? '',
            'date' => $parsed['date'] ?? '',
            'time' => (int)($parsed['time'] ?? 0),
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateCounters($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'counter_variance' => $parsed['variance'] ?? '',
            'fullwidthbackground' => $parsed['fullwidthbackground'] ?? '',
            'size' => $parsed['size'] ?? '',
            'enableAnimation' => (int)($parsed['animation'] ?? 0),
            'border' => (int)($parsed['border'] ?? 0),
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'alternativeFont' => ($parsed['alternativeFont'] ?? 0),
            'text_1' => $parsed['pretext'] ?? '',
            'counteNumber' => (int)($parsed['counteNumber'] ?? 0),
            'text_2' => $parsed['posttext'] ?? '',
            'speedValue' => $parsed['speedValue'] ?? 0,
            'counter_item' => isset($parsed['default']) ? count($parsed['default']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['default'])) {
            foreach ($parsed['default'] as $default) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'headline' => $default['headline'] ?? '',
                    'datato' => $default['datato'] ?? '',
                    'dataappend' => $default['dataappend'] ?? '',
                    'icon' => $default['icon'] ?? '',
                    'color' => $default['color'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'counter_item');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'counter_item');
            }
        }
    }

    private function migrateDivider($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'divider_colors' => $parsed['colors'] ?? '',
            'spacements' => $parsed['spacements'] ?? '',
            'divider_type' => $parsed['dividerType'] ?? '1',
            'dividerSize' => $parsed['dividerSize'] ?? '1',
            'dividerWeight' => $parsed['dividerWeight'] ?? '1',
            'icon' => $parsed['icon'] ?? '',
            'divider_style' => $parsed['style'] ?? '',
            'divider_icon_size' => $parsed['iconSize'] ?? '',
            'divider_icon_position' => $parsed['iconPosition'] ?? '',
            'isEnableAnimation' => (int)($parsed['animation'] ?? 0),
            'animation' => $parsed['animationType'] ?? '',
            'text_1' => $parsed['animationDelay'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateDropdownButton($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'text_1' => $parsed['headline'] ?? '',
            'class' => $parsed['class'] ?? '',
            'text_2' => $parsed['buttontext'] ?? '1',
            'dropdown_button' =>  isset($parsed['button']) ? count($parsed['button']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['button'])) {
            foreach ($parsed['button'] as $button) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'linktext' => $button['linktext'] ?? '',
                    'link' => $button['link'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'dropdown_button');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'dropdown_button');
            }
        }
    }

    private function migrateFooter($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'layout' => $parsed['layout'] ?? '',
            'bgcolor' => $parsed['bgcolor'] ?? '',
            'text_1' => $parsed['title'] ?? '',
            'logoNavigation' => $parsed['logoNavigation'] ?? '',
            'text_2' => $parsed['formtitle'] ?? '',
            'text_3' => $parsed['logoWidth'] ?? '',
            'height' => (int)($parsed['logoHeight'] ?? 0),
            'copyright' => $parsed['copyright'] ?? '',
            'text_4' => $parsed['leftcolumntitle'] ?? '',
            'text_5' => $parsed['firstcoltitle'] ?? '',
            'text_6' => $parsed['firstNav'] ?? '',
            'text_7' => $parsed['text'] ?? '',
            'viewmorelink' => $parsed['viewmorelink'] ?? '',
            'text_8' => $parsed['viewmoretext'] ?? '',
            'text_9' => $parsed['newsletterformpageid'] ?? '',
            'text_10' => $parsed['secondcoltitle'] ?? '',
            'text_11' => $parsed['secondNav'] ?? '',
            'hour1' => $parsed['hour1'] ?? '',
            'hour2' => (int)($parsed['hour2'] ?? 0),
            'sunday' => (int)($parsed['sunday'] ?? 0),
            'text_12' => $parsed['thirdcoltitle'] ?? '',
            'text_13' => $parsed['thirdNav'] ?? '',
            'text_14' => $parsed['rightcolumntitle'] ?? '',
            'bodytext' => $parsed['address'] ?? '',
            'text_15' => $parsed['phone'] ?? '',
            'numberSize' => $parsed['numberSize'] ?? '',
            'text_16' => $parsed['phone2'] ?? '',
            'text_17' => $parsed['fax'] ?? '',
            'text_18' => $parsed['email'] ?? '',
            'text_19' => $parsed['fourthcoltitle'] ?? '',
            'text_20' => $parsed['fourthNav'] ?? '',
            'text_21' => $parsed['contactformpageid'] ?? '',
            'text_22' => $parsed['bloglatest'] ?? '',
            'text_23' => $parsed['blogcomment'] ?? '',
            'text_24' => $parsed['blogcategory'] ?? '',
            'navigationId' => (int)($parsed['navigationId'] ?? 0),
            'latitude' => (float)($parsed['latitude'] ?? 0),
            'longitude' => (float)($parsed['longitude'] ?? 0),
            'text_25' => trim($parsed['map'] ?? ''),
            'payment' => isset($parsed['payment']) ? count($parsed['payment']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['payment'])) {
            foreach ($parsed['payment'] as $payment) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'icon' => $payment['icon'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'payment');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'payment');
            }
        }
    }

    private function migrateFullWidthSemi($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'height' => (int)($parsed['height'] ?? 0),
            'bodytext' => $parsed['content'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateHeaderSlider($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'nav' => (int)($parsed['nav'] ?? 0),
            'dots' => (int)($parsed['dots'] ?? 0),
            'autoplay' => $parsed['autoplay'] ?? '',
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'overlay' => $parsed['overlay'] ?? 'none',
            'boxposition' => $parsed['boxposition'] ?? '',
            'align' => $parsed['align'] ?? '',
            'wave' => (int)($parsed['wave'] ?? 0),
            'colorPicker' => $parsed['wavecolorpick'] ?? '',
            'header_slider_navstyle' => $parsed['navstyle'] ?? 'style-1',
            'navbg' => $parsed['navbg'] ?? '',
            'height' => (int)($parsed['height'] ?? 0),
            'minheight' => (int)($parsed['minheight'] ?? 0),
            'slider' => isset($parsed['default']) ? count($parsed['default']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['default'])) {
            foreach ($parsed['default'] as $slider) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'animationsrightimage' => $slider['animationsrightimage'] ?? '',
                    'textcolor' => $slider['textcolor'] ?? '',
                    'subheadline' => $slider['subheadline'] ?? '',
                    'animationsheadlinelist' => $slider['animationsheadlinelist'] ?? '',
                    'animationHeadlineDuration' => $slider['animationHeadlineDuration'] ?? '',
                    'bigSubHeadline' => (int)($slider['bigSubHeadline'] ?? 0),
                    'subheadlineSidebars' => (int)($slider['subheadlineSidebars'] ?? 0),
                    'headline' => $slider['headline'] ?? '',
                    'animationssubheadline' => $slider['animationssubheadline'] ?? '',
                    'animationSubheadlineDuration' => $slider['animationSubheadlineDuration'] ?? '',
                    'text' => $slider['text'] ?? '',
                    'animationstext' => $slider['animationstext'] ?? '',
                    'animationTextDuration' => $slider['animationTextDuration'] ?? '',
                    'color' => $slider['color'] ?? '',
                    'btnstyle' => $slider['btnstyle'] ?? '',
                    'btntext' => $slider['btntext'] ?? '',
                    'link' => $slider['btnlink'] ?? '',
                    'animationsbtn' => $slider['animationsbtn'] ?? '',
                    'image' => $slider['image'] ?? '',
                    'rightimage' => $slider['rightimage'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'slider');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'slider');
            }
        }
    }

    private function migrateHistory($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'enableBorderRadius' => (int)($parsed['enableBorderRadius'] ?? 0),
            'history' => isset($parsed['history']) ? count($parsed['history']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['history'])) {
            foreach ($parsed['history'] as $history) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'title' => $history['title'] ?? '',
                    'text' => $history['text'] ?? '',
                    'image' => $history['image'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'history');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'history');
            }
        }
    }

    private function migrateHotspots($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['default'])) {
            foreach ($parsed['default'] as $hotspots) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'left' => $hotspots['left'] ?? '',
                    'top' => $hotspots['top'] ?? '',
                    'header' => $hotspots['text'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'hotspots');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'hotspots');
            }
        }
    }

    private function migrateIconBox($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'icon_box_style' => $parsed['style'] ?? '',
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'enableBorderRadius' => (int)($parsed['enableBorderRadius'] ?? 0),
            'effect' => $parsed['effect'] ?? '',
            'hovershadow' => $parsed['hovershadow'] ?? '',
            'hover' => (int)($parsed['hover'] ?? 0),
            'iconbox_color' => $parsed['color'] ?? '',
            'colorShades' => $parsed['colorShades'] ?? '',
            'icon' => $parsed['icon'] ?? '',
            'animation' => $parsed['animation'] ?? '',
            'icon_position' => $parsed['icon_position'] ?? '',
            'list_type' => $parsed['list_type'] ?? '',
            'title' => $parsed['title'] ?? '',
            'prepend_title' => $parsed['prepend_title'] ?? '',
            'title_divider' => (int)($parsed['title_divider'] ?? 0),
            'text' => $parsed['text'] ?? '',
            'text_divider' => (int)($parsed['text_divider'] ?? 0),
            'btntext' => $parsed['btntext'] ?? '',
            'link' => $parsed['btnlink'] ?? '',
            'iconbox_border' => $parsed['border'] ?? '',
            'fullheight' => (int)($parsed['fullheight'] ?? 0),
            'mixediconbox' => isset($parsed['mixediconbox']) ? count($parsed['mixediconbox']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['mixediconbox'])) {
            foreach ($parsed['mixediconbox'] as $mixediconbox) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'icon' => $mixediconbox['icon'] ?? '',
                    'image' => $mixediconbox['image'] ?? '',
                    'title' => $mixediconbox['title'] ?? '',
                    'prepend_title' => $mixediconbox['prepend_title'] ?? '',
                    'text' => $mixediconbox['text'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'mixediconbox');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'mixediconbox');
            }
        }
    }

    private function migrateIconList($uid, $pid, $cType, $parsed, $langUid)
    {

        $data = [
            'CType' => $cType,
            'text_1' => $parsed['headline'] ?? '',
            'text_2' => $parsed['subheadline'] ?? '',
            'icon_list' => isset($parsed['default']) ? count($parsed['default']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['default'])) {
            foreach ($parsed['default'] as $default) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'title' => $default['title'] ?? '',
                    'icon' => $default['icon'] ?? '',
                    'link' => $default['link'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'icon_list');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'icon_list');
            }
        }
    }

    private function migrateImageAnimation($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'imageAnimations' => $parsed['imageAnimations'] ?? '',
            'hoverAnimations' => $parsed['hoverAnimations'] ?? '',
            'transform' => $parsed['transform'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateImageFrames($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'imageframe_variance' => $parsed['variance'] ?? '',
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'imageframe_style' => $parsed['style'] ?? '',
            'hoverstyle' => $parsed['hoverstyle'] ?? '',
            'gridstyle' => $parsed['gridstyle'] ?? '',
            'triangle' => (int)($parsed['triangle'] ?? 0),
            'link' => $parsed['link'] ?? '',
            'text_1' => $parsed['headline'] ?? '',
            'text_2' => $parsed['subheadline'] ?? '',
            'text_3' => $parsed['buttontext'] ?? '',
            'text' => $parsed['text'] ?? '',
            'fontsize' => (int)($parsed['fontsize'] ?? 0),
            'nav' => (int)($parsed['nav'] ?? 0),
            'text_4' => $parsed['item'] ?? '',
            'hover_style' => isset($parsed['default']) ? count($parsed['default']) : 0,
            'grid' => isset($parsed['grid']) ? count($parsed['grid']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['default'])) {
            foreach ($parsed['default'] as $default) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'smallimage' => $default['smallimage'] ?? '',
                    'largeimage' => $default['largeimage'] ?? '',
                    'headline' => $default['headline'] ?? '',
                    'subheadline' => $default['subheadline'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'hover_style');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'hover_style');
            }
        }
        if (!empty($parsed['grid'])) {
            foreach ($parsed['grid'] as $grid) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'image' => $grid['image'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'grid');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'grid');
            }
        }
    }

    private function migrateImageGallery($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'image_gallery_variance' => $parsed['variance'] ?? 'default',
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'enableBorderRadius' => (int)($parsed['enableBorderRadius'] ?? 0),
            'numberofitem' => (int)($parsed['numberofitem'] ?? 0),
            'image_gallery_categories' => isset($parsed['categories']) ? count($parsed['categories']) : 0,
            'image_gallery' => isset($parsed['default']) ? count($parsed['default']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['categories'])) {
            foreach ($parsed['categories'] as $categories) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'headline' => $categories['headline'] ?? '',
                    'value' => $categories['value'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'image_gallery_categories');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'image_gallery_categories');
            }
        }
        if (!empty($parsed['default'])) {
            foreach ($parsed['default'] as $default) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'categoryvalues' => $default['categoryvalues'] ?? '',
                    'headline' => $default['headline'] ?? '',
                    'image' => $default['image'] ?? '',
                    'description' => $default['description'] ?? '',
                    'facebooklink' => $default['facebooklink'] ?? '',
                    'twitterlink' => $default['twitterlink'] ?? '',
                    'linkedinlink' => $default['linkedinlink'] ?? '',
                    'link' => $default['link'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'image_gallery');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'image_gallery');
            }
        }
    }

    private function migrateImageViewer($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateLandingDemo($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'text_1' => $parsed['title'] ?? '',
            'text_2' => $parsed['number'] ?? '',
            'text_3' => $parsed['label'] ?? '',
            'category' => $parsed['category'] ?? '',
            'tagline' => isset($parsed['tagline']) ? count($parsed['tagline']) : 0,
            'sliderItem' => isset($parsed['default']) ? count($parsed['default']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['tagline'])) {
            foreach ($parsed['tagline'] as $tagline) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'headline' => $tagline['headline'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'tagline');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'tagline');
            }
        }
        if (!empty($parsed['default'])) {
            foreach ($parsed['default'] as $default) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'headline' => $default['headline'] ?? '',
                    'image' => $default['image'] ?? '',
                    'link' => $default['link'] ?? '',
                    'category' => $default['category'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'sliderItem');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'sliderItem');
            }
        }
    }

    private function migrateLightBoxes($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'lightbox_variance' => $parsed['variance'] ?? 'default',
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'sliderautoplay' => (int)($parsed['sliderautoplay'] ?? 0),
            'lightbox_style' => $parsed['style'] ?? 'default',
            'btnsize' => $parsed['btnsize'] ?? 'btn-sm',
            'maxcarousel' => (int)($parsed['maxcarousel'] ?? 0),
            'text_1' => $parsed['buttontext'] ?? '',
            'text_2' => $parsed['iframebuttontext'] ?? '',
            'contentanimation' => $parsed['contentanimation'] ?? '',
            'lightbox_color' => $parsed['color'] ?? '',
            'text' => $parsed['text'] ?? '',
            'form' => $parsed['form'] ?? '',
            'text_4' => $parsed['iframe'] ?? '',
            'images' => isset($parsed['default']) ? count($parsed['default']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['default'])) {
            foreach ($parsed['default'] as $default) {
                $image = $default['image'] ?? '';
                if ($default['image'] !== '' && !str_starts_with($default['image'], 't3://file?uid=')) {
                    $image = 't3://file?uid=' . $default['image'];
                }
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'image' => $image,
                    'headline' => $default['headline'] ?? '',
                    'icon' => $default['icon'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'images');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'images');
            }
        }
    }

    private function migrateList($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'list' => $parsed['list'] ?? 'default',
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'icon' => $parsed['icon'] ?? '',
            'iconsize' => $parsed['iconsize'] ?? '',
            'list_style' => $parsed['style'] ?? '',
            'list_color' => $parsed['color'] ?? '',
            'borderSelect' => $parsed['border'] ?? '',
            'ordered' => (int)($parsed['ordered'] ?? 0),
            'iconposition' => (int)($parsed['iconposition'] ?? 0),
            'text' => $parsed['text'] ?? '',
            'listItem' => isset($parsed['default']) ? count($parsed['default']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['default'])) {
            foreach ($parsed['default'] as $default) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'list' => $default['list'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'listItem');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'listItem');
            }
        }
    }

    private function migrateMap($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'text_25' => $parsed['map'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateMaps($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'maps_variance' => $parsed['variance'] ?? 'default',
            'markers' => isset($parsed['markers']) ? count($parsed['markers']) : 0,
            'height' => (int)($parsed['height'] ?? 0),
            'latitude' => (int)($parsed['latitude'] ?? 0),
            'longitude' => (int)($parsed['longitude'] ?? 0),
            'formlink' => $parsed['formlink'] ?? '',
            'zoom' => (int)($parsed['zoom'] ?? 0),
            'border' => (int)($parsed['border'] ?? 0),
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'zoomControl' => (int)($parsed['zoomControl'] ?? 0),
            'mapTypeControl' => (int)($parsed['mapTypeControl'] ?? 0),
            'scaleControl' => (int)($parsed['scaleControl'] ?? 0),
            'streetViewControl' => (int)($parsed['streetViewControl'] ?? 0),
            'overviewMapControl' => (int)($parsed['overviewMapControl'] ?? 0),
            'scrollwheel' => (int)($parsed['scrollwheel'] ?? 0),
            'mapdetails' => isset($parsed['mapdetails']) ? count($parsed['mapdetails']) : 0,
            'tabs' => isset($parsed['tabs']) ? count($parsed['tabs']) : 0,
            'animatedmap' => isset($parsed['animatedmap']) ? count($parsed['animatedmap']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['markers'])) {
            foreach ($parsed['markers'] as $markers) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'address' => $markers['address'] ?? '',
                    'display' => $markers['display'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'markers');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'markers');
            }
        }
        if (!empty($parsed['mapdetails'])) {
            foreach ($parsed['mapdetails'] as $mapdetails) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'title' => $mapdetails['title'] ?? '',
                    'details' => $mapdetails['details'] ?? '',
                    'btntext' => $mapdetails['btntext'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'mapdetails');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'mapdetails');
            }
        }
        if (!empty($parsed['tabs'])) {
            foreach ($parsed['tabs'] as $tabs) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'headline' => $tabs['headline'] ?? '',
                    'address' => $tabs['address'] ?? '',
                    'display' => $tabs['display'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'tabs');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'tabs');
            }
        }
        if (!empty($parsed['animatedmap'])) {
            foreach ($parsed['animatedmap'] as $animatedmap) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'latitude' => (int)($animatedmap['latitude'] ?? 0),
                    'longitude' => (int)($animatedmap['longitude'] ?? 0),
                    'htmltext' => $animatedmap['htmltext'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'animatedmap');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'animatedmap');
            }
        }
    }

    private function migrateMasonryImg($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateMedias($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'mediastype' => $parsed['mediastype'] ?? 'default-video',
            'ratio' => $parsed['ratio'] ?? 'ratio-1x1',
            'controls' => (int)($parsed['controls'] ?? 0),
            'loop' => (int)($parsed['loop'] ?? 0),
            'autoplay' => (int)($parsed['autoplay'] ?? 0),
            'playbtn' => (int)($parsed['playbtn'] ?? 0),
            'soundcloud' => $parsed['soundcloud'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateModal($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'text' => $parsed['text'] ?? '',
            'modalbtntext' => isset($parsed['modalbtntext']) ? count($parsed['modalbtntext']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['modalbtntext'])) {
            foreach ($parsed['modalbtntext'] as $modalbtntext) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'btntext' => $modalbtntext['btntext'] ?? '',
                    'modalsize' => $modalbtntext['modalsize'] ?? '',
                    'form' => $modalbtntext['form'] ?? '',
                    'modaltitle' => $modalbtntext['modaltitle'] ?? '',
                    'modaldetails' => $modalbtntext['modaldetails'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'modalbtntext');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'modalbtntext');
            }
        }
    }

    private function migrateOfficeDetails($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'office_details_variance' => $parsed['variance'] ?? '0',
            'text_1' => $parsed['header'] ?? '',
            'subheader' => $parsed['subheader'] ?? '',
            'iscoloredheader' => (int)($parsed['iscoloredheader'] ?? 0),
            'iconfilleded' => (int)($parsed['iconfilleded'] ?? 0),
            'contact' => isset($parsed['contact']) ? count($parsed['contact']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['contact'])) {
            foreach ($parsed['contact'] as $contact) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'icon' => $contact['icon'] ?? '',
                    'header' => $contact['header'] ?? '',
                    'text' => $contact['text'] ?? '',
                    'link' => $contact['link'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'contact');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'contact');
            }
        }
    }

    private function migrateParticles($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'particles_effect' => $parsed['effect'] ?? 'default',
            'height' => (int)($parsed['height'] ?? 0),
            'bodytext' => $parsed['title'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migratePopover($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'popover_style' => $parsed['position'] ?? 'top',
            'isPopover' => (int)($parsed['isPopover'] ?? 0),
            'bodytext' => $parsed['content'] ?? '',
            'text_1' => $parsed['buttontext'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migratePricingTables($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'pricingtables_variance' => $parsed['variance'] ?? 'default',
            'greyHeadlineBg' => (int)($parsed['greyHeadlineBg'] ?? 0),
            'featuredcolor' => $parsed['featuredcolor'] ?? '',
            'isgapstyle' => (int)($parsed['isgapstyle'] ?? 0),
            'text_1' => $parsed['monthlyheadline'] ?? '',
            'text_2' => $parsed['yearlyheadline'] ?? '',
            'text_3' => $parsed['headline'] ?? '',
            'text_4' => $parsed['title'] ?? '',
            'text_5' => $parsed['priceunit'] ?? '',
            'floatField' => (float)($parsed['floatField'] ?? 0),
            'text_6' => $parsed['pricelabel'] ?? '',
            'yearlyprice' => (float)($parsed['yearlyprice'] ?? 0),
            'text_7' => $parsed['yearlypricelable'] ?? '',
            'text_8' => $parsed['buttontext'] ?? '',
            'link' => $parsed['buttonlink'] ?? '',
            'singledetails' => isset($parsed['singledetails']) ? count($parsed['singledetails']) : 0,
            'price_data' => isset($parsed['default']) ? count($parsed['default']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['singledetails'])) {
            foreach ($parsed['singledetails'] as $singledetails) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'headline' => $singledetails['headline'] ?? '',
                    'icon' => $singledetails['icon'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'singledetails');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'singledetails');
            }
        }
        if (!empty($parsed['default'])) {
            foreach ($parsed['default'] as $default) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'image' => $default['image'] ?? '',
                    'isfeatured' => (int)($default['isfeatured'] ?? 0),
                    'headline' => $default['headline'] ?? '',
                    'priceunit' => $default['priceunit'] ?? '',
                    'floatField' => (float)($default['floatField'] ?? 0),
                    'pricelabel' => $default['pricelabel'] ?? '',
                    'yearlyprice' => (float)($default['yearlyprice'] ?? 0),
                    'yearlypricelable' => $default['yearlypricelable'] ?? '',
                    'buttontext' => $default['buttontext'] ?? '',
                    'buttonlink' => $default['buttonlink'] ?? '',
                    'text' => $default['text'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'price_data');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'price_data');
            }
        }
    }

    private function migrateProcess($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'processstyle' => $parsed['processstyle'] ?? '0',
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'animation' => $parsed['animation'] ?? '',
            'columnsstep' => $parsed['columnsstep'] ?? '',
            'stepitems' => isset($parsed['stepitems']) ? count($parsed['stepitems']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['stepitems'])) {
            foreach (array_reverse($parsed['stepitems']) as $stepitems) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'title' => $stepitems['title'] ?? '',
                    'icon' => $stepitems['icon'] ?? '',
                    'text' => $stepitems['text'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'stepitems');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'stepitems');
            }
        }
    }

    private function migrateProgressBars($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'bars_type' => $parsed['bars_type'] ?? 'default',
            'size' => $parsed['size'] ?? '',
            'borderRadius' => $parsed['borderRadius'] ?? '',
            'progressbar_style' => $parsed['style'] ?? '',
            'chart_type' => $parsed['chart_type'] ?? '',
            'showProgress' => (int)($parsed['showProgress'] ?? 0),
            'enabledGrid' => (int)($parsed['enabledGrid'] ?? 0),
            'enabledScaleColor' => (int)($parsed['enabledScaleColor'] ?? 0),
            'bars' => isset($parsed['bars']) ? count($parsed['bars']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['bars'])) {
            foreach (array_reverse($parsed['bars']) as $bars) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'icon' => $bars['icon'] ?? '',
                    'title' => $bars['title'] ?? '',
                    'progress' => $bars['progress'] ?? '',
                    'color' => $bars['color'] ?? '',
                    'headline' => $bars['headline'] ?? '',
                    'description' => $bars['description'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'bars');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'bars');
            }
        }
    }

    private function migrateProjectDetailBlock($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'text_1' => $parsed['headline'] ?? '',
            'text_2' => $parsed['strongHeadline'] ?? '',
            'text_3' => $parsed['client'] ?? '',
            'date' => strtotime($parsed['date'] ?? ''),
            'skills' => is_array($parsed['default']) ? count($parsed['default']) : '',
            'text_4' => $parsed['projectText'] ?? '',
            'text_5' => $parsed['projectURL'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['default'])) {
            foreach (array_reverse($parsed['default']) as $default) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'skill' => $default['skill'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'skills');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'skills');
            }
        }
    }

    private function migrateRandomImage($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'randomimagetype' => $parsed['randomimagetype'] ?? 'default',
            'enableBorderRadius' => (int)($parsed['enableBorderRadius'] ?? 0),
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'text_1' => $parsed['title'] ?? '',
            'imagesSameSize' => (int)($parsed['imagesSameSize'] ?? 0),
            'text_2' => $parsed['delayimage'] ?? '',
            'animateIn' => $parsed['animateIn'] ?? '',
            'animateOut' => $parsed['animateOut'] ?? '',
            'imageinside' => isset($parsed['imageinside']) ? count($parsed['imageinside']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['imageinside'])) {
            foreach (array_reverse($parsed['imageinside']) as $imageinside) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'multipledelayvalue' => $imageinside['multipledelayvalue'] ?? '',
                    'insideimg' => $imageinside['insideimg'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'imageinside');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'imageinside');
            }
        }
    }

    private function migrateReadMore($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'text_1' => $parsed['morebtn'] ?? '',
            'text_2' => $parsed['lessbtn'] ?? '',
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'startOpened' => (int)($parsed['startOpened'] ?? 0),
            'enableToggle' => (int)($parsed['enableToggle'] ?? 0),
            'buttonstyle' => $parsed['buttonstyle'] ?? 'default',
            'alignment' => $parsed['alignment'] ?? '',
            'overlay' => $parsed['overlay'] ?? '',
            'onlyicon' => (int)($parsed['onlyicon'] ?? 0),
            'moreicon' => $parsed['moreicon'] ?? '',
            'lessicon' => $parsed['lessicon'] ?? '',
            'bodytext' => $parsed['text'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateSectionParallax($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'parallax_style' => $parsed['style'] ?? '',
            'enableBorderRadius' => (int)($parsed['enableBorderRadius'] ?? 0),
            'text_1' => $parsed['speed'] ?? '',
            'colorPicker' => $parsed['bgColor'] ?? '',
            'parallaxOverlay' => $parsed['parallaxOverlay'] ?? '',
            'bodytext' => $parsed['content'] ?? '',
            'scaleinvert' => (int)($parsed['scaleinvert'] ?? 0),
            'text_2' => $parsed['responsiveMinHeight'] ?? '',
            'text_3' => $parsed['desktopMinHeight'] ?? '',
            'section' => isset($parsed['section']) ? count($parsed['section']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['section'])) {
            foreach (array_reverse($parsed['section']) as $section) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'image' => $section['image'] ?? '',
                    'setPosition' => $section['position'] ?? '',
                    'content' => $section['content'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'section');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'section');
            }
        }
    }

    private function migrateSectionTeaser($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'section_teaser_style' => $parsed['style'] ?? 'cls',
            'variation' => $parsed['variation'] ?? '',
            'variations' => $parsed['variations'] ?? '',
            'color' => $parsed['color'] ?? '',
            'expandcolor' => $parsed['expandcolor'] ?? '',
            'bgcolor' => $parsed['bgcolor'] ?? '',
            'angledLayerTop' => (int)($parsed['angledLayerTop'] ?? 0),
            'angledbgcolor' => $parsed['angledbgcolor'] ?? '',
            'angledLayerBottom' => (int)($parsed['angledLayerBottom'] ?? 0),
            'angledbgcolors' => $parsed['angledbgcolors'] ?? '',
            'withoutBorder' => (int)($parsed['withoutBorder'] ?? 0),
            'withoutMargin' => (int)($parsed['withoutMargin'] ?? 0),
            'centerAligned' => (int)($parsed['centerAligned'] ?? 0),
            'imagedirection' => (int)($parsed['imagedirection'] ?? 0),
            'darktext' => (int)($parsed['darktext'] ?? 0),
            'icon' => $parsed['icon'] ?? '',
            'bodytext' => $parsed['headline'] ?? '',
            'text_1' => $parsed['title'] ?? '',
            'subheadlinetext' => $parsed['subheadline'] ?? '',
            'text' => $parsed['text'] ?? '',
            'colorPicker' => $parsed['colorpicker'] ?? '',
            'listText' => $parsed['list'] ?? '',
            'fourtwelvesection' => isset($parsed['fourtwelvesection']) ? count($parsed['fourtwelvesection']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);

        if (!empty($parsed['fourtwelvesection'])) {
            foreach (array_reverse($parsed['fourtwelvesection']) as $fourtwelvesection) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'title' => $fourtwelvesection['title'] ?? '',
                    'image' => $fourtwelvesection['image'] ?? '',
                    'text' => $fourtwelvesection['text'] ?? '',
                    'imagedirection' => (int)($fourtwelvesection['imagedirection'] ?? 0),
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'fourtwelvesection');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'fourtwelvesection');
            }
        }
    }

    private function migrateServicelist($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'bodytext' => $parsed['title'] ?? '',
            'text_1' => $parsed['btntext'] ?? '',
            'link' => $parsed['btnlink'] ?? '',
            'service' => isset($parsed['service']) ? count($parsed['service']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['service'])) {
            foreach (array_reverse($parsed['service']) as $service) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'image' => $service['image'] ?? '',
                    'text' => $service['text'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'service');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'service');
            }
        }
    }

    private function migrateShapedivider($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'selectStyle' => $parsed['selectStyle'] ?? 'shape-style-1',
            'shapeBefore' => (int)($parsed['shapeBefore'] ?? 0),
            'selectBeforeColor' => $parsed['selectBeforeColor'] ?? '',
            'shapeAfter' => (int)($parsed['shapeAfter'] ?? 0),
            'colorPicker' => $parsed['selectAfterColor'] ?? '',
            'overlayColor' => $parsed['overlayColor'] ?? '',
            'blurIntensity' => $parsed['blurIntensity'] ?? '',
            'bodytext' => $parsed['content'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateSlider($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'sliderStyle' => $parsed['sliderStyle'] ?? '',
            'dots' => (int)($parsed['dots'] ?? 0),
            'loop' => (int)($parsed['loop'] ?? 0),
            'nav' => (int)($parsed['nav'] ?? 0),
            'autoHeight' => (int)($parsed['autoHeight'] ?? 0),
            'navStyle' => $parsed['navStyle'] ?? '',
            'header' => $parsed['items'] ?? '',
            'shadow' => (int)($parsed['shadow'] ?? 0),
            'slider_item' => isset($parsed['default']) ? count($parsed['default']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['default'])) {
            foreach (array_reverse($parsed['default']) as $default) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'title' => $default['title'] ?? '',
                    'post' => $default['post'] ?? '',
                    'imgs' => $default['imgs'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'slider_item');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'slider_item');
            }
        }
    }

    private function migrateSocial($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'text_1' => $parsed['title'] ?? '',
            'inlineCheck' => (int)($parsed['inlineCheck'] ?? 0),
            'social_item' => isset($parsed['default']) ? count($parsed['default']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['default'])) {
            foreach (array_reverse($parsed['default']) as $default) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'icon' => $default['icon'] ?? '',
                    'link' => $default['link'] ?? '',
                    'socialmediahoverclass' => $default['socialmediahoverclass'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'social_item');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'social_item');
            }
        }
    }

    private function migrateStarRating($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'rating_variance' => $parsed['variance'] ?? '',
            'floatingValue' => (float)($parsed['floatingValue'] ?? 0),
            'captionCheck' => (int)($parsed['captionCheck'] ?? 0),
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateStickyContent($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'image_position' => $parsed['image_position'] ?? '',
            'text' => $parsed['text'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateTabs($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'tab_variance' => $parsed['variance'] ?? '',
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'tab_style' => $parsed['position'] ?? '',
            'color' => $parsed['color'] ?? '',
            'righttab' => (int)($parsed['righttab'] ?? 0),
            'tab_items' => isset($parsed['default']) ? count($parsed['default']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['default'])) {
            foreach (array_reverse($parsed['default']) as $default) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'icon' => $default['icon'] ?? '',
                    'headline' => $default['headline'] ?? '',
                    'text' => $default['text'] ?? '',
                    'formvariance' => $default['formvariance'] ?? '',
                    'bggray' => (int)($default['bggray'] ?? 0),
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'tab_items');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'tab_items');
            }
        }
    }

    private function migrateTeams($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'team_variance' => $parsed['variance'] ?? 'twocolumn',
            'bodytext' => $parsed['title'] ?? '',
            'team' => isset($parsed['team']) ? count($parsed['team']) : 0,
            'text_1' => $parsed['btntext'] ?? '',
            'link' => $parsed['btnlink'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['team'])) {
            foreach (array_reverse($parsed['team']) as $team) {
                $randomString = StringUtility::getUniqueId('NEW');
                $image = $team['image'] ?? '';
                if ($team['image'] !== '' && !str_starts_with($team['image'], 't3://file?uid=')) {
                    $image = 't3://file?uid=' . $team['image'];
                }
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'image' => $image,
                    'name' => $team['name'] ?? '',
                    'post' => $team['post'] ?? '',
                    'color' => $team['color'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'team');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'team');
            }
        }
    }

    private function migrateTeaser($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'text_1' => $parsed['headline'] ?? '',
            'teaseritems' => isset($parsed['teaseritems']) ? count($parsed['teaseritems']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['teaseritems'])) {
            foreach (array_reverse($parsed['teaseritems']) as $teaseritems) {
                $randomString = StringUtility::getUniqueId('NEW');
                $image = $teaseritems['image'] ?? '';
                if ($teaseritems['image'] !== '' && !str_starts_with($teaseritems['image'], 't3://file?uid=')) {
                    $image = 't3://file?uid=' . $teaseritems['image'];
                }
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'image' => $image,
                    'name' => $teaseritems['name'] ?? '',
                    'link' => $teaseritems['link'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'teaseritems');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'teaseritems');
            }
        }
    }

    private function migrateTeaserImage($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'header_position' => $parsed['direction'] ?? '',
            'bodytext' => $parsed['content'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateTestimonials($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'testimonials_variance' => $parsed['variance'] ?? 'default',
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'ratingstar' => (int)($parsed['ratingstar'] ?? 0),
            'nav' => (int)($parsed['nav'] ?? 0),
            'dots' => (int)($parsed['dots'] ?? 0),
            'autoplay' => (int)($parsed['autoplay'] ?? 0),
            'loop' => (int)($parsed['loop'] ?? 0),
            'testimonials_style' => $parsed['style'] ?? '',
            'countdown_color' => $parsed['color'] ?? '',
            'enableBlockquote' => (int)($parsed['blockquote'] ?? 0),
            'text_1' => $parsed['name'] ?? '',
            'text_2' => $parsed['post'] ?? '',
            'text' => $parsed['text'] ?? '',
            'text_3' => $parsed['cardtext'] ?? '',
            'text_4' => $parsed['ratings'] ?? '',
            'carousels' => isset($parsed['carousels']) ? count($parsed['carousels']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['carousels'])) {
            foreach (array_reverse($parsed['carousels']) as $carousels) {
                $randomString = StringUtility::getUniqueId('NEW');
                $image = $carousels['image'] ?? '';
                if ($carousels['image'] !== '' && !str_starts_with($carousels['image'], 't3://file?uid=')) {
                    $image = 't3://file?uid=' . $carousels['image'];
                }
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'image' => $image,
                    'name' => $carousels['name'] ?? '',
                    'post' => $carousels['post'] ?? '',
                    'text' => $carousels['text'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'carousels');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'carousels');
            }
        }
    }

    private function migrateTextAnimation($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'textAnimations' => $parsed['textAnimations'] ?? '',
            'duplicated' => (int)($parsed['duplicated'] ?? 0),
            'maxWidth' => (float)($parsed['maxWidth'] ?? 0),
            'fontsize' => (float)($parsed['fontsize'] ?? 0),
            'duration' => (float)($parsed['duration'] ?? 0),
            'lettersAnimations' => $parsed['lettersAnimations'] ?? '',
            'gsapAnimations' => $parsed['gsapAnimations'] ?? '',
            'appearAnimations' => $parsed['appearAnimations'] ?? '',
            'text_1' => $parsed['animationSpeed'] ?? '',
            'text_2' => $parsed['startDelay'] ?? '',
            'header_layout' => $parsed['texttype'] ?? '',
            'textAlign' => $parsed['textAlign'] ?? '',
            'alignment' => $parsed['alignment'] ?? '',
            'color' => $parsed['color'] ?? '',
            'backgrouncolor' => $parsed['backgrouncolor'] ?? '',
            'text_25' => $parsed['title'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateThreeImgBlock($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'text_1' => $parsed['marginBottom'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
    }

    private function migrateToggles($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'toggle_variance' => $parsed['variance'] ?? '1',
            'enableSharpBorder' => (int)($parsed['enableSharpBorder'] ?? 0),
            'colors' => $parsed['colors'] ?? '',
            'oneToggleOpenAtATime' => (int)($parsed['oneToggleOpenAtATime'] ?? 0),
            'items' => isset($parsed['items']) ? count($parsed['items']) : 0,
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['items'])) {
            foreach (array_reverse($parsed['items']) as $items) {
                $randomString = StringUtility::getUniqueId('NEW');
                $image = $items['image'] ?? '';
                if ($items['image'] !== '' && !str_starts_with($items['image'], 't3://file?uid=')) {
                    $image = 't3://file?uid=' . $items['image'];
                }
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'image' => $image,
                    'headline' => $items['headline'] ?? '',
                    'description' => $items['description'] ?? '',
                    'imagePosition' => $items['imagePosition'] ?? '',
                    'afterImageDescription' => $items['afterImageDescription'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'items');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'items');
            }
        }
    }

    private function migrateWordRotator($uid, $pid, $cType, $parsed, $langUid)
    {
        $data = [
            'CType' => $cType,
            'rotator_animation' => $parsed['animation'] ?? 'rotate-1',
            'header_layout' => $parsed['texttype'] ?? '',
            'header_position' => $parsed['textAlign'] ?? '',
            'color' => $parsed['color'] ?? '',
            'backgrouncolor' => $parsed['backgrouncolor'] ?? '',
            'textcolor' => $parsed['textcolor'] ?? '',
            'text_1' => $parsed['pretext'] ?? '',
            'word_rotate_items' => isset($parsed['default']) ? count($parsed['default']) : 0,
            'text_2' => $parsed['posttext'] ?? '',
        ];
        $this->updateTtContent($data, $uid, $pid);
        if (!empty($parsed['default'])) {
            foreach (array_reverse($parsed['default']) as $default) {
                $randomString = StringUtility::getUniqueId('NEW');
                $data = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $uid,
                    'word' => $default['word'] ?? '',
                    'sys_language_uid' => $langUid,
                ];
                $this->contentBlocksRepository->deleteOldRecord($uid,$langUid,'word_rotate_items');
                $this->contentBlocksRepository->insertDataWithDataHandler($data, $randomString, 'word_rotate_items');
            }
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
        $queryBuilder->executeStatement();
    }
}
