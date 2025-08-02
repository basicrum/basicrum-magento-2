<?php declare(strict_types=1);

namespace BasicRum\Analytics\ViewModel;

use BasicRum\Analytics\Api\PageTypeDetectorInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

class Footer implements ArgumentInterface
{
    public function __construct(
        private PageTypeDetectorInterface $pageTypeDetector,
        private ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Get module configuration
     */
    public function getConfig(): array
    {
        // If you already have a getConfig method, keep its implementation
        // and add to it if needed
        $config = [
            'beacon_endpoint' => $this->scopeConfig->getValue(
                'basicrum/general/beacon_endpoint',
                ScopeInterface::SCOPE_STORE
            )
        ];

        return $config;
    }

    /**
     * Get the current page type
     */
    public function getPageType(): string
    {
        return $this->pageTypeDetector->getPageType();
    }
}
