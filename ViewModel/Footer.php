<?php declare(strict_types=1);

namespace Basicrum\Analytics\ViewModel;

use Basicrum\Analytics\Api\PageTypeDetectorInterface;
use Basicrum\Analytics\Model\Config;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/** @phpstan-import-type RuntimeConfig from Config */
class Footer implements ArgumentInterface
{
    /**
     * Supply validated store configuration and native page classification.
     *
     * @param PageTypeDetectorInterface $pageTypeDetector
     * @param Config $config
     */
    public function __construct(
        private PageTypeDetectorInterface $pageTypeDetector,
        private Config $config
    ) {
    }

    /**
     * Get validated effective configuration or null when monitoring is inactive.
     *
     * @return RuntimeConfig|null
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
