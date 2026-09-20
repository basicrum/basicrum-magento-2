<?php
declare(strict_types=1);

namespace BasicRum\Analytics\Model\System\Config\Backend;

use BasicRum\Analytics\Model\Config;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Validates the Brum Site ID before it is saved.
 */
class BrumSiteId extends Value
{
    /**
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = trim((string) $this->getValue());

        if ($value !== '' && !Config::isValidBrumSiteId($value)) {
            throw new LocalizedException(
                __('Brum Site ID must be a valid UUIDv4 copied from the Basicrum backoffice.')
            );
        }

        $this->setValue($value);

        return parent::beforeSave();
    }
}
