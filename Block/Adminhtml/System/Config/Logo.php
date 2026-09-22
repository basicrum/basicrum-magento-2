<?php
declare(strict_types=1);

namespace Basicrum\Analytics\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Logo extends Field
{
    protected $_template = 'Basicrum_Analytics::system/config/logo.phtml';

    public function render(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->render($element);
    }

    public function getLogoUrl(): string
    {
        return $this->getViewFileUrl('Basicrum_Analytics::images/basicrum-log.svg');
    }
}
