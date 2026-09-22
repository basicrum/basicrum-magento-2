<?php
declare(strict_types=1);

namespace Basicrum\Analytics\Block\Adminhtml\System\Config;

use Basicrum\Analytics\Model\Config;
use Magento\Framework\Data\Form\Element\AbstractElement;

class BoomerangVersion extends ReadOnlyField
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        return (string) $this->escapeHtml(sprintf(
            'Boomerang JS v. %s - cutting-edge - 30 KB (gzipped)',
            Config::BOOMERANG_VERSION
        ));
    }
}
