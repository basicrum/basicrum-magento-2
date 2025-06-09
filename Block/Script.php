<?php
declare(strict_types=1);

namespace BasicRum\Analytics\Block;

use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Script extends Template
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->scopeConfig = $scopeConfig;
    }

    public function getConfig()
    {
        return [
            'enabled' => $this->scopeConfig->isSetFlag('basicrum/general/enabled', ScopeInterface::SCOPE_STORE),
            'beacon_endpoint' => $this->scopeConfig->getValue('basicrum/general/beacon_endpoint', ScopeInterface::SCOPE_STORE)
        ];
    }
}
