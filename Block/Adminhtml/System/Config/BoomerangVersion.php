<?php
declare(strict_types=1);

namespace BasicRum\Analytics\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class BoomerangVersion extends Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        return 'Boomerang JS v. 1.815.60 - cutting-edge - 30 KB (gzipped)';
    }
}