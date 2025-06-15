<?php
declare(strict_types=1);

namespace BasicRum\Analytics\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Logo extends Field
{
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        $html = '<div style="margin: 20px 0; text-align: center; font-size: 3rem;">';
        $html .= '<img src="' . $this->getViewFileUrl('BasicRum_Analytics::images/basicrum-log.svg') . '" alt="BasicRum Logo" style="width: 35px; height: 35px;" />'; 
        $html .= 'BasicRUM Analytics';
        $html .= '</div>';
        return $html;
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->render($element);
    }
}
