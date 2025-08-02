<?php
declare(strict_types=1);

namespace BasicRum\Analytics\Model;

use BasicRum\Analytics\Api\PageTypeDetectorInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResponseInterface;

class PageTypeDetector implements PageTypeDetectorInterface
{
    public function __construct(
        private HttpRequest $request,
        private ResponseInterface $response
    ) {
    }

    /**
     * Get the current page type
     *
     * @return string
     */
    public function getPageType(): string
    {
        // Check for error pages first
        if ($this->response->getStatusCode() == 404) {
            return '404_not_found';
        }

        // Get full action name
        $fullActionName = $this->request->getFullActionName();

        // Common page types based on full action name
        $pageTypeMap = [
            'cms_index_index' => 'home',
            'cms_page_view' => 'cms_page',
            'catalog_product_view' => 'product',
            'catalog_category_view' => 'category',
            'checkout_index_index' => 'checkout',
            'checkout_cart_index' => 'cart',
            'customer_account_login' => 'customer_login',
            'customer_account_create' => 'customer_register',
            'customer_account_index' => 'customer_account',
            'sales_order_history' => 'order_history',
            'contact_index_index' => 'contact',
            'catalogsearch_result_index' => 'search_results',
        ];

        if (isset($pageTypeMap[$fullActionName])) {
            return $pageTypeMap[$fullActionName];
        }

        // Default fallback
        return 'unmapped_' . $fullActionName;
    }

    /**
     * Check if current page is homepage
     *
     * @return bool
     */
    public function isHomePage(): bool
    {
        return $this->getPageType() === 'home';
    }

    /**
     * Check if current page is a product page
     *
     * @return bool
     */
    public function isProductPage(): bool
    {
        return $this->getPageType() === 'product';
    }

    /**
     * Check if current page is a checkout page
     *
     * @return bool
     */
    public function isCheckoutPage(): bool
    {
        return $this->getPageType() === 'checkout';
    }
}
