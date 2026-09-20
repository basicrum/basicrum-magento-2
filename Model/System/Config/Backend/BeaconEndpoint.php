<?php
declare(strict_types=1);

namespace BasicRum\Analytics\Model\System\Config\Backend;

use BasicRum\Analytics\Model\Config;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Validates and normalizes the Beacon Endpoint before it is saved.
 */
class BeaconEndpoint extends Value
{
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private StoreManagerInterface $storeManager,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = trim((string) $this->getValue());

        if ($value !== '' && !Config::isValidBeaconEndpoint($value)) {
            throw new LocalizedException(
                __('Beacon Endpoint must be a valid HTTP or HTTPS URL without embedded credentials or a fragment.')
            );
        }

        if (!$this->isHttpAllowed() && stripos($value, 'http://') === 0) {
            $value = 'https://' . substr($value, 7);
        }

        $this->setValue($value);

        return parent::beforeSave();
    }

    /**
     * Resolve the policy submitted in the same scoped configuration form.
     */
    private function isHttpAllowed(): bool
    {
        $groups = $this->getData('groups');
        $field = $groups['developer']['fields']['development_mode'] ?? null;

        if (is_array($field) && empty($field['inherit']) && array_key_exists('value', $field)) {
            return Config::normalizeBoolean($field['value'], false);
        }

        $scope = (string) $this->getScope();
        $scopeCode = (string) $this->getScopeCode();

        if ($scope === ScopeInterface::SCOPE_STORES && $scopeCode !== '') {
            if (is_array($field) && !empty($field['inherit'])) {
                $websiteCode = (string) $this->storeManager->getStore($scopeCode)->getWebsite()->getCode();
                return Config::normalizeBoolean(
                    $this->_config->getValue(
                        Config::XML_PATH_DEVELOPMENT_MODE,
                        ScopeInterface::SCOPE_WEBSITE,
                        $websiteCode
                    ),
                    false
                );
            }

            return Config::normalizeBoolean(
                $this->_config->getValue(
                    Config::XML_PATH_DEVELOPMENT_MODE,
                    ScopeInterface::SCOPE_STORE,
                    $scopeCode
                ),
                false
            );
        }

        if ($scope === ScopeInterface::SCOPE_WEBSITES
            && $scopeCode !== ''
            && !(is_array($field) && !empty($field['inherit']))
        ) {
            return Config::normalizeBoolean(
                $this->_config->getValue(
                    Config::XML_PATH_DEVELOPMENT_MODE,
                    ScopeInterface::SCOPE_WEBSITE,
                    $scopeCode
                ),
                false
            );
        }

        return Config::normalizeBoolean(
            $this->_config->getValue(Config::XML_PATH_DEVELOPMENT_MODE),
            false
        );
    }
}
