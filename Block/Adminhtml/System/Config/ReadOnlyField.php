<?php
declare(strict_types=1);

namespace Basicrum\Analytics\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/** Display-only information has no saved value to inherit or restore. */
class ReadOnlyField extends Field
{
    /**
     * @inheritDoc
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()
            ->unsCanUseWebsiteValue()
            ->unsCanUseDefaultValue()
            ->unsCanRestoreToDefault();

        return parent::render($element);
    }
}
