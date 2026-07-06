<?php

declare(strict_types=1);

namespace NITSAN\NsThemeAgency\ViewHelpers\Mark;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Fallback for <f:mark.contentArea> when EXT:visual_editor is not installed.
 *
 * On the frontend this behaves like the visual editor ViewHelper outside edit mode:
 * it renders the wrapped content unchanged. When visual_editor is active, that
 * extension registers its own Mark\ContentAreaViewHelper instead.
 */
final class ContentAreaViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('colPos', 'int', 'The colPos number', true);
        $this->registerArgument(
            'txContainerParent',
            'int',
            'Container parent uid when using EXT:container',
            false,
            0
        );
    }

    public function render(): string
    {
        return (string)$this->renderChildren();
    }
}
