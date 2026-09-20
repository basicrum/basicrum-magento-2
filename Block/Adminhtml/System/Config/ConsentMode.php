<?php
declare(strict_types=1);

namespace BasicRum\Analytics\Block\Adminhtml\System\Config;

use BasicRum\Analytics\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Manual consent connection choices, including preserved legacy values.
 */
class ConsentMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::CONSENT_MODE_MANUAL, 'label' => __('Manual callbacks')],
            ['value' => 'explicit', 'label' => __('Legacy: Explicit Consent (manual callbacks; review)')],
            ['value' => 'implicit', 'label' => __('Legacy: Implicit Consent (manual callbacks; review)')],
            ['value' => 'cookie', 'label' => __('Legacy: Cookie Banner (manual callbacks; review)')],
            ['value' => 'gdpr', 'label' => __('Legacy: GDPR Banner (manual callbacks; review)')],
        ];
    }
}
