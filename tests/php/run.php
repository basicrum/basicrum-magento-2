<?php
declare(strict_types=1);

use BasicRum\Analytics\Api\PageTypeDetectorInterface;
use BasicRum\Analytics\Model\Config;
use BasicRum\Analytics\Model\System\Config\Backend\BeaconEndpoint;
use BasicRum\Analytics\Model\System\Config\Backend\BrumSiteId;
use BasicRum\Analytics\Model\System\Config\Backend\WaitMilliseconds;
use BasicRum\Analytics\ViewModel\Footer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;

$root = dirname(__DIR__, 2);
require __DIR__ . '/bootstrap.php';
require $root . '/Api/PageTypeDetectorInterface.php';
require $root . '/Model/Config.php';
require $root . '/ViewModel/Footer.php';
require $root . '/Model/System/Config/Backend/BeaconEndpoint.php';
require $root . '/Model/System/Config/Backend/BrumSiteId.php';
require $root . '/Model/System/Config/Backend/WaitMilliseconds.php';

/** @var array<string, Closure> $tests */
$tests = [];

$tests['fresh-install defaults fail closed and match config.xml'] = function () use ($root): void {
    $defaults = Config::getDefaults();
    basicrum_assert_false($defaults['enabled'], 'fresh installs must be disabled');
    basicrum_assert_true($defaults['consent_enabled'], 'fresh installs must require consent');
    basicrum_assert_false($defaults['strip_query_string'], 'query stripping must be opt-in');
    basicrum_assert_false($defaults['wait_after_onload'], 'wait must be disabled initially');
    basicrum_assert_same(0, $defaults['delay_ms'], 'delay must default to zero');
    basicrum_assert_false($defaults['development_mode'], 'HTTP exception must be off');

    $xml = simplexml_load_file($root . '/etc/config.xml');
    basicrum_assert_same('0', (string) $xml->default->basicrum->general->enabled, 'config enabled default');
    basicrum_assert_same('1', (string) $xml->default->basicrum->consent->enabled, 'config consent default');
    basicrum_assert_same('manual', (string) $xml->default->basicrum->consent->mode, 'manual integration default');
    basicrum_assert_same('0', (string) $xml->default->basicrum->privacy->strip_query_string, 'query default');
    basicrum_assert_same('0', (string) $xml->default->basicrum->performance->wait_after_onload, 'wait default');
    basicrum_assert_same('0', (string) $xml->default->basicrum->performance->delay_ms, 'delay default');
};

$tests['validators accept only supported endpoint and UUIDv4 values'] = function (): void {
    basicrum_assert_true(
        Config::isValidBeaconEndpoint('https://collector.example.test/beacon?key=value'),
        'HTTPS endpoint should be accepted'
    );
    basicrum_assert_true(
        Config::isValidBeaconEndpoint('http://127.0.0.1:8080/beacon'),
        'HTTP endpoint should be structurally valid for explicit development use'
    );
    basicrum_assert_false(Config::isValidBeaconEndpoint('javascript:alert(1)'), 'executable scheme');
    basicrum_assert_false(Config::isValidBeaconEndpoint('https:///missing-host'), 'missing host');
    basicrum_assert_false(Config::isValidBeaconEndpoint(' https://collector.test'), 'untrimmed URL');
    basicrum_assert_true(
        Config::isValidBrumSiteId('550e8400-e29b-41d4-a716-446655440000'),
        'UUIDv4 should be accepted'
    );
    basicrum_assert_false(
        Config::isValidBrumSiteId('550e8400-e29b-11d4-a716-446655440000'),
        'non-v4 UUID should fail'
    );
    basicrum_assert_false(Config::normalizeBoolean('yes', false), 'invalid enable must fail disabled');
    basicrum_assert_true(Config::normalizeBoolean('yes', true), 'invalid consent must fail required');
    basicrum_assert_same(0, Config::normalizeWaitMilliseconds(-1), 'negative wait');
    basicrum_assert_same(30000, Config::normalizeWaitMilliseconds(90000), 'bounded wait');
    basicrum_assert_same(0, Config::normalizeWaitMilliseconds('not-a-number'), 'invalid wait');
};

$tests['save backends validate normalize and honor the same-form HTTP decision'] = function (): void {
    $scopeConfig = new BasicrumTestScopeConfig();
    $storeManager = new BasicrumTestStoreManager();
    $context = new Context();
    $registry = new Registry();
    $cacheTypeList = new class implements TypeListInterface {};

    $endpoint = new BeaconEndpoint($context, $registry, $scopeConfig, $cacheTypeList, $storeManager);
    $endpoint->setValue('http://collector.example.test/beacon');
    $endpoint->setData('groups', [
        'developer' => ['fields' => ['development_mode' => ['value' => '0']]],
    ]);
    $endpoint->beforeSave();
    basicrum_assert_same('https://collector.example.test/beacon', $endpoint->getValue(), 'HTTP upgrade');

    $developmentEndpoint = new BeaconEndpoint($context, $registry, $scopeConfig, $cacheTypeList, $storeManager);
    $developmentEndpoint->setValue('http://127.0.0.1:8080/beacon');
    $developmentEndpoint->setData('groups', [
        'developer' => ['fields' => ['development_mode' => ['value' => '1']]],
    ]);
    $developmentEndpoint->beforeSave();
    basicrum_assert_same('http://127.0.0.1:8080/beacon', $developmentEndpoint->getValue(), 'HTTP exception');

    $scopedConfig = new BasicrumTestScopeConfig([
        basicrum_test_key(ScopeInterface::SCOPE_WEBSITE, 'base', Config::XML_PATH_DEVELOPMENT_MODE) => '1',
    ]);
    $scopedEndpoint = new BeaconEndpoint(
        $context,
        $registry,
        $scopedConfig,
        $cacheTypeList,
        new BasicrumTestStoreManager(['default' => 'base'])
    );
    $scopedEndpoint->setValue('http://scoped.test/beacon');
    $scopedEndpoint->setData('scope', ScopeInterface::SCOPE_STORES);
    $scopedEndpoint->setData('scope_code', 'default');
    $scopedEndpoint->setData('groups', [
        'developer' => ['fields' => ['development_mode' => ['inherit' => '1']]],
    ]);
    $scopedEndpoint->beforeSave();
    basicrum_assert_same('http://scoped.test/beacon', $scopedEndpoint->getValue(), 'inherited website HTTP policy');

    $site = new BrumSiteId($context, $registry, $scopeConfig, $cacheTypeList);
    $site->setValue('not-a-uuid');
    try {
        $site->beforeSave();
        throw new RuntimeException('invalid Site ID did not throw');
    } catch (LocalizedException $exception) {
        basicrum_assert_contains('UUIDv4', $exception->getMessage(), 'site validation error');
    }

    $wait = new WaitMilliseconds($context, $registry, $scopeConfig, $cacheTypeList);
    $wait->setValue('45000');
    $wait->beforeSave();
    basicrum_assert_same(30000, $wait->getValue(), 'save-time wait bound');
};

$tests['runtime gate requires enable endpoint and site identity'] = function (): void {
    $default = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
    $base = [
        basicrum_test_key($default, 0, Config::XML_PATH_ENABLED) => '1',
        basicrum_test_key($default, 0, Config::XML_PATH_CONSENT_ENABLED) => '1',
        basicrum_test_key($default, 0, Config::XML_PATH_CONSENT_MODE) => 'implicit',
    ];

    $missing = new Config(new BasicrumTestScopeConfig($base));
    basicrum_assert_same(null, $missing->getRuntimeConfig($default), 'missing identity must be inactive');
    basicrum_assert_same('missing_endpoint', $missing->getStatus($default)['state'], 'admin missing endpoint');

    $base[basicrum_test_key($default, 0, Config::XML_PATH_BEACON_ENDPOINT)] = 'https://collector.test/beacon';
    $badSite = new Config(new BasicrumTestScopeConfig($base + [
        basicrum_test_key($default, 0, Config::XML_PATH_BRUM_SITE_ID) => 'invalid',
    ]));
    basicrum_assert_same(null, $badSite->getRuntimeConfig($default), 'invalid Site ID must be inactive');

    $base[basicrum_test_key($default, 0, Config::XML_PATH_BRUM_SITE_ID)] =
        '550e8400-e29b-41d4-a716-446655440000';
    $valid = new Config(new BasicrumTestScopeConfig($base));
    $runtime = $valid->getRuntimeConfig($default);
    basicrum_assert_same('implicit', $runtime['consent_mode'], 'legacy value must be retained');
    basicrum_assert_true($runtime['consent_enabled'], 'legacy mode must not grant consent');
    basicrum_assert_same('active_consent', $valid->getStatus($default)['state'], 'consent state');

    $base[basicrum_test_key($default, 0, Config::XML_PATH_ENABLED)] = 'malformed';
    basicrum_assert_same(
        null,
        (new Config(new BasicrumTestScopeConfig($base)))->getRuntimeConfig($default),
        'invalid enable value must fail closed'
    );
};

$tests['effective default website and store scope values are preserved'] = function (): void {
    $default = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
    $values = [
        basicrum_test_key($default, 0, Config::XML_PATH_ENABLED) => '1',
        basicrum_test_key($default, 0, Config::XML_PATH_BEACON_ENDPOINT) => 'http://default.test/beacon',
        basicrum_test_key($default, 0, Config::XML_PATH_BRUM_SITE_ID) => '550e8400-e29b-41d4-a716-446655440000',
        basicrum_test_key($default, 0, Config::XML_PATH_CONSENT_ENABLED) => '1',
        basicrum_test_key($default, 0, Config::XML_PATH_DEVELOPMENT_MODE) => '0',
        basicrum_test_key(ScopeInterface::SCOPE_WEBSITE, 'eu', Config::XML_PATH_BEACON_ENDPOINT) =>
            'https://eu.test/beacon',
        basicrum_test_key(ScopeInterface::SCOPE_WEBSITE, 'eu', Config::XML_PATH_CONSENT_ENABLED) => '0',
        basicrum_test_key(ScopeInterface::SCOPE_STORE, 'bg', Config::XML_PATH_BRUM_SITE_ID) =>
            '123e4567-e89b-42d3-a456-426614174000',
        basicrum_test_key(ScopeInterface::SCOPE_STORE, 'dev', Config::XML_PATH_DEVELOPMENT_MODE) => '1',
        basicrum_test_key(ScopeInterface::SCOPE_STORE, 'dev', Config::XML_PATH_BEACON_ENDPOINT) =>
            'http://127.0.0.1:8080/beacon',
    ];
    $config = new Config(new BasicrumTestScopeConfig($values, ['bg' => 'eu', 'dev' => 'eu']));

    $defaultRuntime = $config->getRuntimeConfig($default);
    basicrum_assert_same('https://default.test/beacon', $defaultRuntime['beacon_endpoint'], 'HTTPS runtime policy');

    $websiteRuntime = $config->getRuntimeConfig(ScopeInterface::SCOPE_WEBSITE, 'eu');
    basicrum_assert_same('https://eu.test/beacon', $websiteRuntime['beacon_endpoint'], 'website endpoint');
    basicrum_assert_false($websiteRuntime['consent_enabled'], 'website immediate override');

    $storeRuntime = $config->getRuntimeConfig(ScopeInterface::SCOPE_STORE, 'bg');
    basicrum_assert_same('https://eu.test/beacon', $storeRuntime['beacon_endpoint'], 'store inherits website endpoint');
    basicrum_assert_same('123e4567-e89b-42d3-a456-426614174000', $storeRuntime['brum_site_id'], 'store Site ID');

    $devRuntime = $config->getRuntimeConfig(ScopeInterface::SCOPE_STORE, 'dev');
    basicrum_assert_same(
        'http://127.0.0.1:8080/beacon',
        $devRuntime['beacon_endpoint'],
        'explicit development mode permits HTTP'
    );
};

$tests['system fields preserve default website and store inheritance'] = function () use ($root): void {
    $xml = simplexml_load_file($root . '/etc/adminhtml/system.xml');
    $fields = [
        $xml->system->section->group[0]->field[3],
        $xml->system->section->group[0]->field[4],
        $xml->system->section->group[1]->field[0],
        $xml->system->section->group[2]->field[0],
        $xml->system->section->group[3]->field[0],
        $xml->system->section->group[3]->field[1],
        $xml->system->section->group[4]->field[0],
    ];

    foreach ($fields as $field) {
        foreach (['showInDefault', 'showInWebsite', 'showInStore'] as $scopeAttribute) {
            basicrum_assert_same('1', (string) $field[$scopeAttribute], $field['id'] . ' ' . $scopeAttribute);
        }
    }

    basicrum_assert_same(
        'BasicRum\\Analytics\\Model\\System\\Config\\Backend\\BeaconEndpoint',
        (string) $xml->system->section->group[0]->field[3]->backend_model,
        'endpoint save validator'
    );
    basicrum_assert_same(
        'BasicRum\\Analytics\\Model\\System\\Config\\Backend\\BrumSiteId',
        (string) $xml->system->section->group[0]->field[4]->backend_model,
        'Site ID save validator'
    );
};

$tests['template renders safely and selects consent or immediate loader'] = function () use ($root): void {
    $detector = new class implements PageTypeDetectorInterface {
        public function getPageType(): string
        {
            return 'home</script><script>alert(1)</script>';
        }

        public function isHomePage(): bool { return true; }
        public function isProductPage(): bool { return false; }
        public function isCheckoutPage(): bool { return false; }
    };

    $render = static function (array $values) use ($root, $detector): string {
        $config = new Config(new BasicrumTestScopeConfig($values));
        $footer = new Footer($detector, $config);
        $block = new class($footer) {
            public function __construct(private Footer $footer) {}
            public function getViewModel(): Footer { return $this->footer; }
            public function getViewFileUrl(string $asset): string
            {
                return 'https://shop.test/static/version123/' . str_replace('::', '/', $asset);
            }
        };
        $secureRenderer = new class {
            public function renderTag(string $tag, array $attributes, string $content, bool $textContent): string
            {
                $rendered = '';
                foreach ($attributes as $name => $value) {
                    $rendered .= ' ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '="'
                        . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
                }
                return '<' . $tag . $rendered . '>' . $content . '</' . $tag . '>';
            }
        };

        ob_start();
        include $root . '/view/frontend/templates/footer.phtml';
        return (string) ob_get_clean();
    };

    $default = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
    basicrum_assert_same('', $render([]), 'disabled render must be empty');

    $base = [
        basicrum_test_key($default, 0, Config::XML_PATH_ENABLED) => '1',
        basicrum_test_key($default, 0, Config::XML_PATH_BEACON_ENDPOINT) => 'https://collector.test/beacon',
        basicrum_test_key($default, 0, Config::XML_PATH_BRUM_SITE_ID) => '550e8400-e29b-41d4-a716-446655440000',
        basicrum_test_key($default, 0, Config::XML_PATH_CONSENT_ENABLED) => '1',
        basicrum_test_key($default, 0, Config::XML_PATH_WAIT_ENABLED) => '1',
        basicrum_test_key($default, 0, Config::XML_PATH_WAIT_MS) => '500',
    ];
    $consent = $render($base);
    basicrum_assert_contains('consent-boomerang-loader-v1-15.min.js', $consent, 'consent loader');
    basicrum_assert_contains('brum_site_id', $consent, 'Site ID variable');
    basicrum_assert_contains('p_gen', $consent, 'generator variable');
    basicrum_assert_contains('mage2', $consent, 'Magento generator value');
    basicrum_assert_contains('strip_query_string', $consent, 'query config');
    basicrum_assert_contains('this.timer', $consent, 'cancellable wait timer');
    basicrum_assert_contains('500', $consent, 'configured wait');
    basicrum_assert_contains('\\u003C/script\\u003E', $consent, 'JSON-safe page type');
    basicrum_assert_not_contains('home</script><script>', $consent, 'unsafe page type');

    $base[basicrum_test_key($default, 0, Config::XML_PATH_CONSENT_ENABLED)] = '0';
    $immediate = $render($base);
    basicrum_assert_contains('boomerang-loader-v15.min.js', $immediate, 'immediate loader');
    basicrum_assert_not_contains('consent-boomerang-loader-v1-15.min.js', $immediate, 'no consent loader');
};

$tests['reviewed Boomerang artifact and notices are pinned'] = function () use ($root): void {
    $artifact = $root . '/view/frontend/web/js/boomr/boomerang-1.815.60.cutting-edge.min.js';
    basicrum_assert_same(
        '90e8a1c85949b10d43e441efc3f0545f95e4384e26ee3042344a8b2b4110589c',
        hash_file('sha256', $artifact),
        'Boomerang checksum'
    );
    basicrum_assert_true(is_file($root . '/view/frontend/web/js/boomr/LICENSE.txt'), 'Boomerang license');
    basicrum_assert_true(is_file($root . '/THIRD-PARTY-NOTICES.txt'), 'third-party notices');
};

$failures = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        fwrite(STDOUT, "PASS: {$name}\n");
    } catch (Throwable $throwable) {
        $failures++;
        fwrite(STDERR, "FAIL: {$name}\n{$throwable->getMessage()}\n");
    }
}

fwrite(STDOUT, sprintf("%d tests, %d failures\n", count($tests), $failures));
exit($failures === 0 ? 0 : 1);
