<?php
declare(strict_types=1);

namespace Basicrum\Analytics\Model;

use Basicrum\Analytics\Api\PageTypeDetectorInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;

class PageTypeDetector implements PageTypeDetectorInterface
{
    /**
     * Magento 1 labels, selected using Magento 2's final dispatched action.
     * See docs/PAGE-TYPE-ALIGNMENT.md for route evidence and fallback policy.
     */
    private const PAGE_TYPES = [
        'cms_noroute_index' => '404 Not Found',
        'cms_index_defaultnoroute' => '404 Not Found',
        'cms_index_index' => 'Home',
        'cms_page_view' => 'CMS Page',
        'catalog_category_view' => 'Category',
        'catalog_product_view' => 'Product',
        'catalogsearch_result_index' => 'Search',
        'catalogsearch_advanced_index' => 'Advanced Search',
        'checkout_cart_index' => 'Cart',
        'checkout_index_index' => 'Checkout',
        'checkout_onepage_success' => 'Checkout Success',
        'customer_account_login' => 'Login',
        'customer_account_create' => 'Register',
        'customer_account_index' => 'Account',
        'customer_account_logoutsuccess' => 'Logout Success',
        'contact_index_index' => 'Contact',
        'sales_guest_form' => 'Orders and Returns',
        'customer_account_edit' => 'Customer Account Edit',
        'sales_order_view' => 'Order View',
        'paypal_billing_agreement_index' => 'Billing Agreements',
        'paypal_billing_agreement_view' => 'Billing Agreement View',
        'sales_guest_view' => 'Guest Order View',
        'customer_address_form' => 'Customer Address Edit',
        'customer_address_index' => 'Customer Address List',
        'wishlist_index_configure' => 'Wishlist Item Configure',
        'wishlist_index_index' => 'Wishlist Items List',
        'sales_order_history' => 'Order History',
        'customer_account_forgotpassword' => 'Forgot Password',
    ];

    /**
     * Initialize detection from Magento's final request and response.
     *
     * @param HttpRequest $request
     * @param HttpResponse $response
     */
    public function __construct(
        private HttpRequest $request,
        private HttpResponse $response
    ) {
    }

    /**
     * Get the current page type
     *
     * @return string
     */
    public function getPageType(): string
    {
        // A missing entity can return 404 even when its action is otherwise known.
        if ($this->response->getStatusCode() === 404) {
            return '404 Not Found';
        }

        $fullActionName = strtolower($this->request->getFullActionName());

        // Native Http returns "__" when route/controller/action are all unset.
        if ($fullActionName === '' || trim($fullActionName, '_') === '') {
            return 'unknown';
        }

        // Do not infer a type from generic layout handles or URL/entity parameters.
        return self::PAGE_TYPES[$fullActionName] ?? 'unmapped_' . $fullActionName;
    }

    /**
     * Check if current page is homepage
     *
     * @return bool
     */
    public function isHomePage(): bool
    {
        return $this->getPageType() === 'Home';
    }

    /**
     * Check if current page is a product page
     *
     * @return bool
     */
    public function isProductPage(): bool
    {
        return $this->getPageType() === 'Product';
    }

    /**
     * Check if current page is a checkout page
     *
     * @return bool
     */
    public function isCheckoutPage(): bool
    {
        return $this->getPageType() === 'Checkout';
    }
}
