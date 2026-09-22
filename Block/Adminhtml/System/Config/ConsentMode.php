<?php
declare(strict_types=1);

namespace Basicrum\Analytics\Block\Adminhtml\System\Config;

use Basicrum\Analytics\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Manual consent connection choices, including preserved legacy values.
 */
class ConsentMode implements OptionSourceInterface
{
    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private RequestInterface $request
    ) {
    }

    public function toOptionArray(): array
    {
        $options = [
            ['value' => Config::CONSENT_MODE_MANUAL, 'label' => __('Manual callbacks')],
        ];

        $currentMode = $this->getCurrentMode();
        $legacyLabels = [
            'explicit' => __('Legacy: Explicit Consent (manual callbacks; review)'),
            'implicit' => __('Legacy: Implicit Consent (manual callbacks; review)'),
            'cookie' => __('Legacy: Cookie Banner (manual callbacks; review)'),
            'gdpr' => __('Legacy: GDPR Banner (manual callbacks; review)'),
        ];

        // Preserve a legacy value at the selected effective scope without
        // offering the other historical modes for new configuration.
        if (isset($legacyLabels[$currentMode])) {
            $options[] = ['value' => $currentMode, 'label' => $legacyLabels[$currentMode]];
        }

        return $options;
    }

    private function getCurrentMode(): string
    {
        $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $scopeCode = null;

        $storeCode = (string) $this->request->getParam('store', '');
        if ($storeCode !== '') {
            $scopeType = ScopeInterface::SCOPE_STORE;
            $scopeCode = $storeCode;
        } else {
            $websiteCode = (string) $this->request->getParam('website', '');
            if ($websiteCode !== '') {
                $scopeType = ScopeInterface::SCOPE_WEBSITE;
                $scopeCode = $websiteCode;
            }
        }

        return trim((string) $this->scopeConfig->getValue(
            Config::XML_PATH_CONSENT_MODE,
            $scopeType,
            $scopeCode
        ));
    }
}
