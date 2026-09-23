<?php
declare(strict_types=1);

namespace Basicrum\Analytics\Model\System\Config\Backend;

use Basicrum\Analytics\Model\Config;
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
    /**
     * Initialize the scoped native configuration backend.
     *
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param StoreManagerInterface $storeManager
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array<string,mixed> $data
     */
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
     * Validate the endpoint and apply the effective HTTP policy before saving.
     *
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

        $this->setValue(Config::normalizeBeaconEndpoint($value, $this->isHttpAllowed()));

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
                $websiteId = $this->storeManager->getStore($scopeCode)->getWebsiteId();
                $websiteCode = (string) $this->storeManager->getWebsite($websiteId)->getCode();
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
