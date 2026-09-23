<?php
declare(strict_types=1);

namespace Basicrum\Analytics\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Logo extends Field
{
    /** @var string */
    protected $_template = 'Basicrum_Analytics::system/config/logo.phtml';

    /**
     * @inheritDoc
     */
    public function render(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    /**
     * @inheritDoc
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->render($element);
    }

    /**
     * Resolve the logo through Magento's deployed static-asset URLs.
     */
    public function getLogoUrl(): string
    {
        return $this->getViewFileUrl('Basicrum_Analytics::images/basicrum-logo.png');
    }
}
