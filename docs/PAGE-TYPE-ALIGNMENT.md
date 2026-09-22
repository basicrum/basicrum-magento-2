# Magento 1 page-type alignment

## Decision and scope

Magento 1 is the naming baseline specifically for `p_type`. Magento 2 emits
the same 27 named values, including capitalization and spaces, for equivalent
native pages. WordPress remains the behavioral reference for the other parity
work; neither reference plugin is changed. `p_gen=mage2` remains unchanged.

The reviewed source is Magento 1's `Helper/PageTypeDetector.php` in its
Analytics community module, at repository
commit `f90de1e78b024b98c533f38fae467beac5f5b2c0` (the detector's last change was
`f8c4e2d5aa71ea87b5d34a2bb1dd9ed47634b99c`). Only its labels are adopted; the
Magento 2 implementation uses the final native dispatched action and HTTP
response, not Magento 1 layout handles or copied platform code.

This is the explicitly approved Magento 2-to-Magento 1 portion of D-001,
not a shared WordPress/Magento taxonomy or a historical reporting migration.
The shared parity ledger remains unchanged for the central task to reconcile.

## Exact mapping

Action names below are normalized to lowercase before lookup. Unless noted,
the corresponding Magento 1 action name is identical.

| Emitted `p_type` | Magento 2 full action | Platform adaptation |
| --- | --- | --- |
| `404 Not Found` | `cms_noroute_index`, `cms_index_defaultnoroute` | Magento 1's primary route is `cms_index_noroute`; any HTTP 404 also wins over other mappings. |
| `Home` | `cms_index_index` | |
| `CMS Page` | `cms_page_view` | |
| `Category` | `catalog_category_view` | |
| `Product` | `catalog_product_view` | |
| `Search` | `catalogsearch_result_index` | |
| `Advanced Search` | `catalogsearch_advanced_index` | |
| `Cart` | `checkout_cart_index` | |
| `Checkout` | `checkout_index_index` | Magento 1 uses `checkout_onepage_index`. |
| `Checkout Success` | `checkout_onepage_success` | Requires a valid completed-checkout session to render. |
| `Login` | `customer_account_login` | |
| `Register` | `customer_account_create` | |
| `Account` | `customer_account_index` | |
| `Logout Success` | `customer_account_logoutsuccess` | |
| `Contact` | `contact_index_index` | Magento 1 uses `contacts_index_index`. |
| `Orders and Returns` | `sales_guest_form` | |
| `Customer Account Edit` | `customer_account_edit` | |
| `Order View` | `sales_order_view` | |
| `Billing Agreements` | `paypal_billing_agreement_index` | Magento 1 uses `sales_billing_agreement_index`. |
| `Billing Agreement View` | `paypal_billing_agreement_view` | Magento 1 uses `sales_billing_agreement_view`. |
| `Guest Order View` | `sales_guest_view` | |
| `Customer Address Edit` | `customer_address_form` | Native address `edit` and `new` actions forward to `form`, including adding the first address. |
| `Customer Address List` | `customer_address_index` | An empty address book redirects to `customer_address_new`, which forwards to the address form. |
| `Wishlist Item Configure` | `wishlist_index_configure` | |
| `Wishlist Items List` | `wishlist_index_index` | |
| `Order History` | `sales_order_history` | |
| `Forgot Password` | `customer_account_forgotpassword` | |

The non-identical routes and address forwards were checked against installed
Magento Open Source 2.4.9 controller implementations. PayPal's billing agreement
controllers live in `Magento_Paypal`, not `Magento_Sales`. Native layout results
apply their HTTP headers before rendering the footer. The CMS no-route actions
are also mapped explicitly for route parity and defensive recognition if a
custom response does not retain the usual 404 status; this is not a workaround
for native header timing.

Detection describes the rendered page, not the URL the visitor first requested.
For example, a logged-out account request renders `Login`; an empty-cart
checkout or a success URL without an order session redirects to `Cart`.

## Deliberate fallback

- An HTTP 404 response has priority and emits `404 Not Found`.
- An unrecognized action emits `unmapped_<lowercase full action name>`.
  For example, `search_term_popular` emits `unmapped_search_term_popular`.
- An unavailable action (including Magento's uninitialized `__`) emits
  `unknown`.
- Generic layout handles, URL slugs, product/category identifiers, and request
  parameters are not used to guess a label. Magento 1-only route aliases are
  not registered as Magento 2 equivalents.
- Magento 1 has no named mapping for `catalogsearch_advanced_result`; that
  action deliberately stays `unmapped_catalogsearch_advanced_result`.
- The public helper methods remain available: `isHomePage`, `isProductPage`,
  and `isCheckoutPage`. The last means the checkout page itself, not success.

## Reporting and upgrade impact

This intentionally changes Magento 2's beacon labels, with no legacy-label
mode or stored-data migration:

| Previous Magento 2 label | New label |
| --- | --- |
| `home` | `Home` |
| `cms_page` | `CMS Page` |
| `product` | `Product` |
| `category` | `Category` |
| `checkout` | `Checkout` |
| `cart` | `Cart` |
| `customer_login` | `Login` |
| `customer_register` | `Register` |
| `customer_account` | `Account` |
| `order_history` | `Order History` |
| `contact` | `Contact` |
| `search_results` | `Search` |
| `404_not_found` | `404 Not Found` |

Newly recognized actions also stop emitting their previous `unmapped_*`
values. Historical beacon records are not rewritten. If reports already exist,
update filters/groupings or explicitly combine old/new Magento 2 labels on the
reporting side. Magento 1 reporting vocabulary is unchanged. Unknown action
diagnostics now normalize casing and distinguish unavailable actions from
unrecognized ones.

After updating the module, regenerate compiled dependency injection with
`bin/magento setup:di:compile` if the installation uses compiled DI, including
developer installations where it was previously generated. This also applies
the CSP registration correction below. Manual source updates must not retain
the obsolete collector declaration from `etc/frontend/di.xml`; that file is
no longer distributed. Clean Magento's configuration, layout, block HTML,
and full-page caches, plus any external cache retaining HTML. The label is embedded in the
rendered page; cached HTML can otherwise retain old labels. No loader or
Boomerang asset change is required for this mapping.

## Verification

`tests/php/run.php` checks every mapped action and exact label, mixed-case
actions, view-model passthrough, 404 precedence, fallback, and all three helper
methods. Existing CI runs these checks on PHP 8.2, 8.3, and 8.4. The real
Boomerang fixture and native-storefront expectations use the new labels.

The native browser matrix in `tests/integration/page-types.spec.js` checks
actual rendered public pages and intercepted Boomerang beacons, including
redirect destinations, the no-route page, and an unmapped native route.
Sample-data cases cover CMS, category, and product pages. The separately
opted-in offline checkout test can create a disposable order to verify both
`Checkout` and `Checkout Success`. See `tests/integration/README.md` for setup,
side effects, and execution flags. These tests never substitute `p_type` in
the browser.

### Checkout CSP correction discovered during verification

The native checkout check uncovered an existing R-007 issue: registering the
collector array in `etc/frontend/di.xml` replaced Magento's global collectors
at the area DI stage. Checkout's enforced policy consequently omitted core
sources such as `'self'`, blocking its own requests. Registration now lives
in `etc/di.xml`, where Magento merges it with its original collectors. An
explicit frontend area check keeps Basicrum's contribution out of Admin,
API, cron, and unset-area contexts. Only the validated collector origin is
added; core policies are not copied or loosened. The browser suite now checks
the actual merged CSP header, and PHP tests cover the area guard.

### Local execution record — 2026-09-22

This records the original alignment run. Subsequent review fixes and newer
test results are in `OPUS-REVIEW-FOLLOWUPS.md`; the order-creating journey was
not repeated for those follow-ups.

- PHP 8.2, 8.3, and 8.4: all 16 focused groups and all module/test PHP/PHTML
  syntax checks passed. The focused harness also passed on the local store's
  PHP 8.5.6.
- `npm test`: minified-artifact verification and all 28 loader/real-Boomerang
  browser tests passed.
- Native Magento Open Source 2.4.9 from the Mage-OS mirror, on PHP 8.5.6 with
  Luma sample data: all 21 Chromium integration tests passed. This includes
  18 page-route cases, the consent/cache/CSP storefront check, Admin rendering,
  and the offline checkout journey. All collector requests were intercepted
  locally; saved endpoint and Site ID settings were left unchanged.
- The offline checkout produced synthetic pending order `000000003` in the
  disposable local store. No live payment was used. Failed setup attempts also
  left disposable quotes; no user data was deleted.
- All 28 mapped actions resolved to real native controllers through Magento's
  route configuration and action list. This verifies controller existence, not
  every authenticated journey or its fixtures.
- Native `setup:di:compile` and configuration/layout/block/full-page cache
  cleaning passed. The final browser run used the regenerated DI; an interim
  run failed on stale one-argument constructor metadata before recompilation.
- `git diff --check` and integration shell syntax checks passed. The initial
  offline checkout failure reproduced the CSP issue documented above; the
  completed journey passed after the registration fix and recompilation.

Not run: authenticated customer/address/order/wishlist and PayPal agreement
browser journeys (no corresponding fixture suite was added); the declared
2.4.7-p10/PHP 8.3 native release gate (the available store is 2.4.9/PHP 8.5.6);
remote CI; release/package/publishing checks. Local 8.5 success does not widen
Composer's declared `>=8.2 <8.5` constraint or certify proprietary Adobe
Commerce. No reference plugin or shared ledger was edited.
