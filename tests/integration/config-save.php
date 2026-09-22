<?php
declare(strict_types=1);

use Basicrum\Analytics\Model\Config;
use Magento\Config\Model\ConfigFactory;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

if (getenv('BASICRUM_DISPOSABLE_MAGENTO') !== '1') {
    throw new RuntimeException('Native save tests require BASICRUM_DISPOSABLE_MAGENTO=1.');
}
require __DIR__ . '/bootstrap.php';

$resource = $objectManager->get(ResourceConnection::class);
$connection = $resource->getConnection();
$table = $resource->getTableName('core_config_data');
$scopeConfig = $objectManager->get(ReinitableConfigInterface::class);
$cacheState = $objectManager->get(StateInterface::class);
$factory = $objectManager->get(ConfigFactory::class);
$config = $objectManager->get(Config::class);
$stores = $objectManager->get(StoreManagerInterface::class)->getStores();
$store = reset($stores);
if (!$store || $connection->getTransactionLevel() !== 0) {
    throw new RuntimeException('An installed store and an idle database connection are required.');
}
$storeId = (int) $store->getId();
$websiteId = (int) $store->getWebsiteId();
$siteId = '550e8400-e29b-41d4-a716-446655440000';
$websiteSiteId = '123e4567-e89b-42d3-a456-426614174000';
$snapshot = static fn (): array => $connection->fetchAll(
    $connection->select()->from($table)->where('path LIKE ?', 'basicrum/%')->order('config_id')
);
$original = $snapshot();
$cacheEnabled = $cacheState->isEnabled('config');
// Per-process only; never persist this flag. Uncommitted fixture values must
// neither read from nor be published into Magento's shared configuration cache.
$cacheState->setEnabled('config', false);

$assertSame = static function ($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
};
$save = static function (array $fields, array $scope = []) use ($factory): void {
    $groups = [];
    foreach ($fields as $path => $value) {
        [$group, $field] = explode('/', $path);
        $groups[$group]['fields'][$field] = $value === null ? ['inherit' => '1'] : ['value' => $value];
    }
    // This is the same model/data shape used by the native Admin save action.
    $factory->create(['data' => array_merge(['section' => 'basicrum', 'groups' => $groups], $scope)])->save();
};
$seed = static function () use ($save, $siteId): void {
    $save([
        'general/enabled' => '1',
        'general/beacon_endpoint' => 'https://default.example.test/beacon',
        'general/brum_site_id' => $siteId,
    ]);
};
$raw = static function (string $path, string $scope = 'default', int $id = 0) use ($connection, $table) {
    return $connection->fetchOne($connection->select()->from($table, 'value')
        ->where('path = ?', 'basicrum/' . $path)->where('scope = ?', $scope)->where('scope_id = ?', $id));
};
$expectInvalid = static function (array $fields) use ($save): void {
    try {
        $save($fields);
    } catch (LocalizedException $exception) {
        return;
    }
    throw new RuntimeException('Native configuration save accepted invalid input.');
};

$tests = [];
$tests['native XML defaults disable monitoring and require consent'] = static function () use ($scopeConfig, $config, $assertSame): void {
    $assertSame('0', (string) $scopeConfig->getValue(Config::XML_PATH_ENABLED, 'default'), 'enabled default');
    $assertSame('1', (string) $scopeConfig->getValue(Config::XML_PATH_CONSENT_ENABLED, 'default'), 'consent default');
    $assertSame(null, $config->getRuntimeConfig(), 'fresh native runtime gate');
};
$tests['native backend normalizes HTTPS and bounds the wait'] = static function () use ($save, $siteId, $raw, $config, $assertSame): void {
    $save([
        'general/enabled' => '1', 'general/brum_site_id' => $siteId,
        'general/beacon_endpoint' => ' http://collector.example.test/beacon ',
        'performance/wait_after_onload' => '1', 'performance/delay_ms' => '45000',
    ]);
    $assertSame('https://collector.example.test/beacon', $raw('general/beacon_endpoint'), 'saved HTTPS policy');
    $assertSame(30000, $config->getRuntimeConfig()['delay_ms'], 'saved and effective bounded wait');
};
$tests['native save rejects invalid endpoint without replacing its value'] = static function () use ($seed, $expectInvalid, $raw, $assertSame): void {
    $seed();
    $expectInvalid(['general/beacon_endpoint' => 'not-a-url']);
    $assertSame('https://default.example.test/beacon', $raw('general/beacon_endpoint'), 'invalid endpoint must not persist');
};
$tests['native save rejects invalid site identity without replacing its value'] = static function () use ($seed, $expectInvalid, $raw, $siteId, $assertSame): void {
    $seed();
    $expectInvalid(['general/brum_site_id' => 'not-a-uuid']);
    $assertSame($siteId, $raw('general/brum_site_id'), 'invalid identity must not persist');
};
$tests['same-form HTTP exception applies before its old saved value'] = static function () use ($seed, $save, $raw, $config, $assertSame): void {
    $seed();
    $save(['general/beacon_endpoint' => 'http://dev.example.test/beacon', 'developer/development_mode' => '1']);
    $assertSame('http://dev.example.test/beacon', $raw('general/beacon_endpoint'), 'same-form allow');
    $assertSame('http://dev.example.test/beacon', $config->getRuntimeConfig()['beacon_endpoint'], 'effective allow');
    $save(['general/beacon_endpoint' => 'http://dev.example.test/beacon', 'developer/development_mode' => '0']);
    $assertSame('https://dev.example.test/beacon', $raw('general/beacon_endpoint'), 'same-form disallow');
};
$tests['store HTTP inheritance and identity resolve through the native website'] = static function () use (
    $seed, $save, $raw, $config, $websiteId, $storeId, $siteId, $websiteSiteId, $assertSame
): void {
    $seed();
    $save([
        'general/beacon_endpoint' => 'http://website.example.test/beacon',
        'general/brum_site_id' => $websiteSiteId, 'developer/development_mode' => '1',
    ], ['website' => $websiteId]);
    $save(['developer/development_mode' => '0'], ['store' => $storeId]);
    $save([
        'general/beacon_endpoint' => 'http://store.example.test/beacon',
        'developer/development_mode' => null,
    ], ['store' => $storeId]);
    $assertSame(false, $raw('developer/development_mode', 'stores', $storeId), 'inherit deletes the override');
    $runtime = $config->getRuntimeConfig('store', $storeId);
    $assertSame('http://store.example.test/beacon', $runtime['beacon_endpoint'], 'store HTTP inherited from website');
    $assertSame($websiteSiteId, $runtime['brum_site_id'], 'website identity inheritance');
    $assertSame($siteId, $config->getRuntimeConfig('default')['brum_site_id'], 'default identity unchanged');
    $save(['general/beacon_endpoint' => null], ['store' => $storeId]);
    $assertSame('http://website.example.test/beacon', $config->getRuntimeConfig('store', $storeId)['beacon_endpoint'], 'endpoint inheritance');
};
$tests['website HTTP inheritance uses the default policy in the same form'] = static function () use (
    $seed, $save, $raw, $config, $websiteId, $assertSame
): void {
    $seed();
    $save(['developer/development_mode' => '1']);
    $save(['developer/development_mode' => '0'], ['website' => $websiteId]);
    $save([
        'general/beacon_endpoint' => 'http://website.example.test/beacon',
        'developer/development_mode' => null,
    ], ['website' => $websiteId]);
    $assertSame(false, $raw('developer/development_mode', 'websites', $websiteId), 'website override deleted');
    $assertSame('http://website.example.test/beacon', $config->getRuntimeConfig('website', $websiteId)['beacon_endpoint'], 'default policy inherited');
};
$tests['native consent override and re-inheritance remain fail closed'] = static function () use ($seed, $save, $config, $storeId, $assertSame): void {
    $seed();
    $save(['consent/enabled' => '0'], ['store' => $storeId]);
    $assertSame(false, $config->getRuntimeConfig('store', $storeId)['consent_enabled'], 'deliberate immediate override');
    $save(['consent/enabled' => null], ['store' => $storeId]);
    $assertSame(true, $config->getRuntimeConfig('store', $storeId)['consent_enabled'], 'inherit requires consent again');
};
$tests['missing identity can be saved but native rendering stays inactive'] = static function () use ($seed, $save, $config, $assertSame): void {
    $seed();
    $save(['general/brum_site_id' => '']);
    $assertSame(null, $config->getRuntimeConfig(), 'missing identity runtime gate');
    $assertSame('missing_site_id', $config->getStatus(), 'same Admin gate');
};
$tests['invalid imported values are rejected by native runtime configuration'] = static function () use (
    $seed, $connection, $table, $scopeConfig, $config, $assertSame
): void {
    foreach ([Config::XML_PATH_BEACON_ENDPOINT => 'invalid_endpoint', Config::XML_PATH_BRUM_SITE_ID => 'invalid_site_id'] as $path => $status) {
        $seed();
        // Simulate a CLI/import bypass of Admin validation, still inside the rollback.
        $connection->update($table, ['value' => 'invalid-import'], [
            'scope = ?' => 'default', 'scope_id = ?' => 0, 'path = ?' => $path,
        ]);
        $scopeConfig->reinit();
        $assertSame(null, $config->getRuntimeConfig(), 'invalid imported value');
        $assertSame($status, $config->getStatus(), 'Admin and runtime agree');
    }
};

try {
    foreach ($tests as $name => $test) {
        $connection->beginTransaction();
        try {
            $connection->delete($table, ['path LIKE ?' => 'basicrum/%']);
            $scopeConfig->reinit();
            $test();
            echo 'PASS: ' . $name . PHP_EOL;
        } finally {
            // Native Config saves use nested transactions. Roll back this
            // process's outer transaction even after a backend exception.
            while ($connection->getTransactionLevel() > 0) {
                $connection->rollBack();
            }
            $scopeConfig->reinit();
        }
    }
} finally {
    $cacheState->setEnabled('config', $cacheEnabled);
    $assertSame($original, $snapshot(), 'Configuration was not restored by rollback.');
}
echo count($tests) . ' native save checks passed; original configuration restored.' . PHP_EOL;
