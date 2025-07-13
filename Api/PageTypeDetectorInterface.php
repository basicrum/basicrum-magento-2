<?php
declare(strict_types=1);

namespace BasicRum\Analytics\Api;

/**
 * Interface for page type detection service
 */
interface PageTypeDetectorInterface
{
    /**
     * Get the current page type
     *
     * @return string
     */
    public function getPageType(): string;

    /**
     * Check if current page is homepage
     *
     * @return bool
     */
    public function isHomePage(): bool;

    /**
     * Check if current page is a product page
     *
     * @return bool
     */
    public function isProductPage(): bool;

    /**
     * Check if current page is a checkout page
     *
     * @return bool
     */
    public function isCheckoutPage(): bool;
}
