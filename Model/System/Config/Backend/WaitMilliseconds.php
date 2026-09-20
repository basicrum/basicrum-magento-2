<?php
declare(strict_types=1);

namespace BasicRum\Analytics\Model\System\Config\Backend;

use BasicRum\Analytics\Model\Config;
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
