<?php
declare(strict_types=1);

use Magento\Framework\App\Area;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ObjectManager\ConfigLoader;
use Magento\Framework\App\State;

// Native checks intentionally load Magento, never tests/php/bootstrap.php.
$magentoRoot = getenv('MAGENTO_ROOT') ?: '';
if (!is_file($magentoRoot . '/app/bootstrap.php')) {
    throw new RuntimeException('MAGENTO_ROOT must point to an installed Magento instance.');
}
require $magentoRoot . '/app/bootstrap.php';
$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$objectManager->get(State::class)->setAreaCode(Area::AREA_ADMINHTML);
$objectManager->configure($objectManager->get(ConfigLoader::class)->load(Area::AREA_ADMINHTML));
