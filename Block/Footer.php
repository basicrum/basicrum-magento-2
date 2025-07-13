<?php
declare(strict_types=1);

namespace BasicRum\Analytics\Block;

use Magento\Framework\View\Element\Template;
use BasicRum\Analytics\Api\PageTypeDetectorInterface;

class Footer extends Template
{
    /**
     * @var PageTypeDetectorInterface
     */
    private $pageTypeDetector;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param Template\Context $context
     * @param PageTypeDetectorInterface $pageTypeDetector
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        PageTypeDetectorInterface $pageTypeDetector,
        array $data = []
    ) {
        $this->pageTypeDetector = $pageTypeDetector;
        $this->scopeConfig = $context->getScopeConfig();
        parent::__construct($context, $data);
    }

    /**
     * Get module configuration
     *
     * @return array
     */
    public function getConfig()
    {
        // If you already have a getConfig method, keep its implementation
        // and add to it if needed
        $config = [
            'beacon_endpoint' => $this->scopeConfig->getValue(
                'basicrum/general/beacon_endpoint',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            )
        ];
        
        return $config;
    }

    /**
     * Get the current page type
     *
     * @return string
     */
    public function getPageType()
    {
        return $this->pageTypeDetector->getPageType();
    }
}
