# BasicRum Analytics for Magento 2

BasicRum Analytics is a Magento 2 extension that helps you collect and analyze real user monitoring (RUM) data for your Magento store, providing insights into your website's performance from the user's perspective.

## Requirements

- Magento Open Source or Commerce version 2.3.x or higher
- PHP 7.2 or higher

## Installation

### Via Composer (recommended)

1. Open a terminal and navigate to your Magento root directory
2. Run the following commands:

```bash
composer require basicrum/magento-2-extension
bin/magento module:enable BasicRum_Analytics
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

### Manual Installation

1. Download the extension archive
2. Extract the contents to `app/code/BasicRum/Analytics` in your Magento root directory
3. Run the following commands:

```bash
bin/magento module:enable BasicRum_Analytics
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

## Configuration

1. Log in to your Magento Admin Panel
2. Navigate to **Stores > Configuration > BasicRum Analytics**
3. Configure the following options:
   - **Enable Module**: Set to "Yes" to enable the extension
   - **Beacon Endpoint**: Enter the URL where the data should be sent. This will be the endpoint where a BasicRUM beacon catcher is running.

4. Click "Save Config" to apply the changes
5. Clear the cache by going to **System > Cache Management** and clicking "Flush Magento Cache"

## Verification

To verify that the extension is working properly:

1. Open your store in a web browser
2. Open the browser's developer tools (F12)
3. Check the Network tab for requests to the BasicRum collection endpoint
4. Visit your BasicRum dashboard to confirm that data is being collected


## License

This extension is released under the [MIT License](LICENSE).
