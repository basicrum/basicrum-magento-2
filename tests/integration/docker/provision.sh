#!/usr/bin/env sh
set -eu

# Only this isolated Compose application is supported. Never point this at a live store.
test "${BASICRUM_DISPOSABLE_MAGENTO:-}" = 1
test "${MAGENTO_ROOT:-}" = /var/www/html
cd "$MAGENTO_ROOT"
if [ -f app/etc/env.php ]; then
    echo 'Already installed. Provisioning refuses to overwrite an existing installation.' >&2
    exit 1
fi

composer create-project --repository-url=https://mirror.mage-os.org/ \
    --no-install --no-interaction magento/project-community-edition . 2.4.7-p10
composer config repositories.magento composer https://mirror.mage-os.org/
composer config --unset repositories.0
composer install --no-dev --prefer-dist --no-interaction

php bin/magento setup:install --base-url="$MAGENTO_STOREFRONT_URL" \
    --base-url-secure="$MAGENTO_STOREFRONT_URL" --use-secure=1 --use-secure-admin=1 \
    --db-host=db --db-name=magento --db-user=magento --db-password=disposable \
    --backend-frontname=admin --admin-firstname=Basicrum --admin-lastname=Test \
    --admin-email=admin@example.test --admin-user="$MAGENTO_ADMIN_USERNAME" \
    --admin-password="$MAGENTO_ADMIN_PASSWORD" --language=en_US --currency=USD --timezone=UTC \
    --search-engine=opensearch --opensearch-host=search --opensearch-port=9200
# setup:install enables modules, so disable only after installation. Braintree
# adds external scripts even without checkout; keep Magento_Paypal for CSP checks.
php bin/magento module:disable Magento_AdminAdobeImsTwoFactorAuth Magento_TwoFactorAuth \
    Magento_AdminAnalytics PayPal_Braintree PayPal_BraintreeGraphQl \
    PayPal_BraintreeCustomerBalance PayPal_BraintreeGiftCardAccount PayPal_BraintreeGiftWrapping
php bin/magento deploy:mode:set developer
php bin/magento config:set system/smtp/disable 1
# Admin Analytics is already disabled; its admin/usage/enabled field no longer exists.
php bin/magento config:set web/secure/use_in_frontend 1
php bin/magento cache:enable
echo 'Pinned disposable Magento is installed. Install the module artifact before running the gate.'
