<?php
declare(strict_types=1);

namespace Basicrum\Analytics\Model\System\Config\Backend;

use Basicrum\Analytics\Model\Config;
use Magento\Framework\App\Config\Value;

/**
 * Bounds Wait After Onload to zero through 30 seconds.
 */
class WaitMilliseconds extends Value
{
    public function beforeSave()
    {
        $this->setValue(Config::normalizeWaitMilliseconds($this->getValue()));

        return parent::beforeSave();
    }
}
