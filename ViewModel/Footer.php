<?php declare(strict_types=1);

namespace BasicRum\Analytics\ViewModel;

use BasicRum\Analytics\Api\PageTypeDetectorInterface;
use BasicRum\Analytics\Model\Config;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Footer implements ArgumentInterface
{
    public function __construct(
        private PageTypeDetectorInterface $pageTypeDetector,
        private Config $config
    ) {
    }

    /**
     * Get validated effective configuration or null when monitoring is inactive.
     *
     * @return array<string, bool|int|string>|null
     */
    public function getConfig(): ?array
    {
        return $this->config->getRuntimeConfig();
    }

    /**
     * Get the current page type
     */
    public function getPageType(): string
    {
        return $this->pageTypeDetector->getPageType();
    }

    /**
     * Get the reviewed bundled Boomerang version.
     */
    public function getBoomerangVersion(): string
    {
        return Config::BOOMERANG_VERSION;
    }
}
