<?php
declare(strict_types=1);

use Basicrum\Analytics\Api\PageTypeDetectorInterface;
use Basicrum\Analytics\Model\Config;
use Basicrum\Analytics\Model\Csp\BeaconPolicyCollector;
use Basicrum\Analytics\Model\PageTypeDetector;
use Basicrum\Analytics\Model\System\Config\Backend\BeaconEndpoint;
use Basicrum\Analytics\Model\System\Config\Backend\BrumSiteId;
use Basicrum\Analytics\Model\System\Config\Backend\WaitMilliseconds;
use Basicrum\Analytics\ViewModel\Footer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;

$root = dirname(__DIR__, 2);
require __DIR__ . '/bootstrap.php';
require $root . '/Api/PageTypeDetectorInterface.php';
require $root . '/Model/Config.php';
require $root . '/Model/Csp/BeaconPolicyCollector.php';
require $root . '/Model/PageTypeDetector.php';
require $root . '/ViewModel/Footer.php';
require $root . '/Model/System/Config/Backend/BeaconEndpoint.php';
require $root . '/Model/System/Config/Backend/BrumSiteId.php';
require $root . '/Model/System/Config/Backend/WaitMilliseconds.php';
require __DIR__ . '/footer-fixture.php';
require $root . '/tests/integration/baseline.php';
require $root . '/tests/integration/candidate-files.php';

/** @var array<string, Closure> $tests */
$tests = [];

$tests['release baseline rejects wrong or missing platform versions'] = function () use ($root): void {
    $expected = parse_ini_file($root . '/tests/integration/baseline.env', false, INI_SCANNER_RAW);
    $matching = [
        'MAGENTO_VERSION' => '2.4.7-p10',
        'PHP_VERSION' => '8.3.30',
        'COMPOSER_VERSION' => '2.10.0',
        'MARIADB_VERSION' => '10.11.14-MariaDB',
        'OPENSEARCH_VERSION' => '2.19.4',
    ];
    basicrum_assert_same([], basicrum_baseline_errors($expected, $matching), 'declared platform passes');
    foreach ([
        'MAGENTO_VERSION' => '2.4.9',
        'PHP_VERSION' => '8.5.6',
        'COMPOSER_VERSION' => '2.1.0',
        'MARIADB_VERSION' => '11.4.0-MariaDB',
        'OPENSEARCH_VERSION' => '2.190.0',
    ] as $key => $version) {
        $wrong = array_replace($matching, [$key => $version]);
        basicrum_assert_same([$key], array_keys(basicrum_baseline_errors($expected, $wrong)), $key . ' mismatch');
    }
    basicrum_assert_same(array_keys($matching), array_keys(basicrum_baseline_errors($expected, [])), 'missing versions fail closed');
};

/**
 * @return array{status: int, stdout: string, stderr: string}
 */
$runDisposableGuard = static function (string $moduleOutput, int $moduleStatus, bool $withRunner = true) use ($root): array {
    $temporaryRoot = sys_get_temp_dir() . '/basicrum-magento-guard-' . bin2hex(random_bytes(6));
    $temporaryBin = $temporaryRoot . '/bin';
    basicrum_assert_true(mkdir($temporaryBin, 0700, true), 'create temporary Magento root');
    $module = $temporaryRoot . '/module';
    mkdir($module . '/tests/integration', 0700, true);
    mkdir($module . '/node_modules/.bin', 0700, true);
    copy($root . '/tests/integration/configure-disposable.sh', $module . '/tests/integration/configure-disposable.sh');
    if ($withRunner) {
        file_put_contents($module . '/node_modules/.bin/playwright', "#!/bin/sh\nexit 74\n");
        chmod($module . '/node_modules/.bin/playwright', 0700);
    }

    $fakeMagento = $temporaryBin . '/magento';
    $fakeMagentoScript = <<<'SH'
#!/bin/sh
if [ "$1" = "module:status" ] && [ "$2" = "--enabled" ]; then
    printf '%s\n' "${BASICRUM_FAKE_MODULE_OUTPUT:-}"
    exit "${BASICRUM_FAKE_MODULE_STATUS:-0}"
fi
echo "configuration mutation: $*" >&2
exit 73
SH;
    basicrum_assert_true(file_put_contents($fakeMagento, $fakeMagentoScript) !== false, 'write fake Magento CLI');
    basicrum_assert_true(chmod($fakeMagento, 0700), 'make fake Magento CLI executable');

    $pipes = [];
    try {
        $process = proc_open(
            ['/bin/sh', $module . '/tests/integration/configure-disposable.sh'],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $root,
            [
                'PATH' => (string) getenv('PATH'),
                'BASICRUM_DISPOSABLE_MAGENTO' => '1',
                'BASICRUM_FAKE_MODULE_OUTPUT' => $moduleOutput,
                'BASICRUM_FAKE_MODULE_STATUS' => (string) $moduleStatus,
                'MAGENTO_ROOT' => $temporaryRoot,
                'MAGENTO_STOREFRONT_URL' => 'https://magento.test/',
            ]
        );
        basicrum_assert_true(is_resource($process), 'start disposable integration guard');
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'status' => proc_close($process),
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    } finally {
        if (isset($pipes[1]) && is_resource($pipes[1])) {
            fclose($pipes[1]);
        }
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            fclose($pipes[2]);
        }
        if (is_file($fakeMagento)) {
            unlink($fakeMagento);
        }
        if (is_dir($temporaryBin)) {
            rmdir($temporaryBin);
        }
        if ($withRunner) {
            unlink($module . '/node_modules/.bin/playwright');
        }
        unlink($module . '/tests/integration/configure-disposable.sh');
        rmdir($module . '/tests/integration');
        rmdir($module . '/tests');
        rmdir($module . '/node_modules/.bin');
        rmdir($module . '/node_modules');
        rmdir($module);
        if (is_dir($temporaryRoot)) {
            rmdir($temporaryRoot);
        }
    }
};

/**
 * @return array{status: int, stdout: string, stderr: string}
 */
$runReleaseGateGuard = static function (string $releaseTag, array $environment = []) use ($root): array {
    $pipes = [];
    try {
        $process = proc_open(
            ['/bin/sh', $root . '/tests/integration/release-gate.sh'],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $root,
            array_merge([
                'PATH' => (string) getenv('PATH'),
                'BASICRUM_DISPOSABLE_MAGENTO' => '1',
                'BASICRUM_RELEASE_TAG' => $releaseTag,
            ], $environment)
        );
        basicrum_assert_true(is_resource($process), 'start native release gate guard');
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'status' => proc_close($process),
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    } finally {
        if (isset($pipes[1]) && is_resource($pipes[1])) {
            fclose($pipes[1]);
        }
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            fclose($pipes[2]);
        }
    }
};

$tests['fresh-install defaults fail closed and match config.xml'] = function () use ($root): void {
    $xml = simplexml_load_file($root . '/etc/config.xml');
    basicrum_assert_same('0', (string) $xml->default->basicrum->general->enabled, 'config enabled default');
    basicrum_assert_same('1', (string) $xml->default->basicrum->consent->enabled, 'config consent default');
    basicrum_assert_same('0', (string) $xml->default->basicrum->privacy->strip_query_string, 'query default');
    basicrum_assert_same('0', (string) $xml->default->basicrum->performance->wait_after_onload, 'wait default');
    basicrum_assert_same('0', (string) $xml->default->basicrum->performance->delay_ms, 'delay default');
    basicrum_assert_same('0', (string) $xml->default->basicrum->developer->development_mode, 'HTTP default');

    // Exercise the defaults Magento actually merges, not an unused parallel array.
    $values = [];
    foreach ($xml->default->basicrum->children() as $group => $fields) {
        foreach ($fields->children() as $field => $value) {
            $values[basicrum_test_key('default', 0, 'basicrum/' . $group . '/' . $field)] = (string) $value;
        }
    }
    basicrum_assert_same(null, (new Config(new BasicrumTestScopeConfig($values)))->getRuntimeConfig(), 'fresh install inactive');
    $values[basicrum_test_key('default', 0, Config::XML_PATH_ENABLED)] = '1';
    $values[basicrum_test_key('default', 0, Config::XML_PATH_BEACON_ENDPOINT)] = 'http://collector.test/beacon';
    $values[basicrum_test_key('default', 0, Config::XML_PATH_BRUM_SITE_ID)] = '550e8400-e29b-41d4-a716-446655440000';
    $runtime = (new Config(new BasicrumTestScopeConfig($values)))->getRuntimeConfig();
    basicrum_assert_true($runtime['consent_enabled'], 'consent required by default');
    basicrum_assert_false($runtime['strip_query_string'], 'redaction opt-in');
    basicrum_assert_false($runtime['wait_after_onload'], 'wait opt-in');
    basicrum_assert_same(0, $runtime['delay_ms'], 'zero wait');
    basicrum_assert_same('https://collector.test/beacon', $runtime['beacon_endpoint'], 'default HTTPS policy');
};

$tests['technical identity and direct Magento dependencies are declared consistently'] = function () use ($root): void {
    $composer = json_decode(
        (string) file_get_contents($root . '/composer.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    basicrum_assert_same('^102.0', $composer['require']['magento/module-backend'], 'Backend dependency');
    basicrum_assert_same('^101.2', $composer['require']['magento/module-config'], 'Config dependency');
    basicrum_assert_same('^100.4', $composer['require']['magento/module-csp'], 'CSP dependency');
    basicrum_assert_same('^101.1', $composer['require']['magento/module-store'], 'Store dependency');
    basicrum_assert_same(
        ['/tests/', '/.test-results/', '/node_modules/', '/test-results/', '/playwright-report/'],
        $composer['autoload']['exclude-from-classmap'],
        'test doubles and generated verification trees excluded from production classmap'
    );
    $quality = json_decode(file_get_contents($root . '/tests/quality/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($composer['require'] as $package => $constraint) {
        basicrum_assert_same($constraint, $quality['require'][$package] ?? null, 'quality constraint matches ' . $package);
    }
    $exportExclusions = array_map(
        static fn (string $line): string => explode(' ', trim($line))[0],
        file($root . '/.gitattributes', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    );
    basicrum_assert_same($composer['archive']['exclude'], $exportExclusions, 'Composer and Git production boundaries match');
    basicrum_assert_same(
        ['Basicrum\\Analytics\\' => ''],
        $composer['autoload']['psr-4'],
        'Composer namespace uses the canonical Basicrum spelling'
    );
    basicrum_assert_false(
        array_key_exists('version', $composer),
        'Composer package version must come from an immutable VCS tag'
    );

    $module = simplexml_load_file($root . '/etc/module.xml');
    basicrum_assert_same('Basicrum_Analytics', (string) $module->module['name'], 'Magento module identifier');
    basicrum_assert_same(
        ['Magento_Backend', 'Magento_Config', 'Magento_Csp', 'Magento_Store'],
        array_map(
            static fn (SimpleXMLElement $dependency): string => (string) $dependency['name'],
            iterator_to_array($module->module->sequence->module, false)
        ),
        'module sequence'
    );
    basicrum_assert_contains(
        "'Basicrum_Analytics'",
        (string) file_get_contents($root . '/registration.php'),
        'registration identifier'
    );

    $acl = simplexml_load_file($root . '/etc/acl.xml');
    $aclResources = $acl->xpath('//resource[@id="Basicrum_Analytics::basicrum_analytics"]');
    basicrum_assert_same(1, count($aclResources), 'canonical ACL resource');

    $system = simplexml_load_file($root . '/etc/adminhtml/system.xml');
    basicrum_assert_same(
        (string) $aclResources[0]['id'],
        (string) $system->system->section->resource,
        'system configuration and ACL resource must match'
    );

    $di = simplexml_load_file($root . '/etc/di.xml');
    $environment = $di->xpath('//type[@name="Magento\\Config\\Model\\Config\\TypePool"]/arguments/argument[@name="environment"]/item');
    basicrum_assert_same(1, count($environment), 'only the development HTTP exception changes export classification');
    basicrum_assert_same(Config::XML_PATH_DEVELOPMENT_MODE, (string) $environment[0]['name'], 'environment-specific path');
    basicrum_assert_same('1', trim((string) $environment[0]), 'HTTP exception excluded from shared configuration');
    basicrum_assert_same(
        'Basicrum\\Analytics\\Api\\PageTypeDetectorInterface',
        (string) $di->preference['for'],
        'DI preference interface'
    );
    basicrum_assert_same(
        'Basicrum\\Analytics\\Model\\PageTypeDetector',
        (string) $di->preference['type'],
        'DI preference implementation'
    );

    $collector = $di->xpath('//type[@name="Magento\\Csp\\Model\\CompositePolicyCollector"]/arguments/argument[@name="collectors"]/item[@name="basicrum_beacon"]');
    basicrum_assert_same(1, count($collector), 'CSP collector merges at the global DI stage');
    basicrum_assert_same(
        'Basicrum\\Analytics\\Model\\Csp\\BeaconPolicyCollector',
        trim((string) $collector[0]),
        'frontend CSP collector class'
    );
    foreach (glob($root . '/etc/*/di.xml') as $areaDiFile) {
        $areaDi = simplexml_load_file($areaDiFile);
        basicrum_assert_same(
            [],
            $areaDi->xpath('//type[@name="Magento\\Csp\\Model\\CompositePolicyCollector"]/arguments/argument[@name="collectors"]'),
            $areaDiFile . ' must not replace core CSP collectors'
        );
    }

    $frontendLayout = simplexml_load_file($root . '/view/frontend/layout/default.xml');
    $footerBlock = $frontendLayout->xpath('//block[@name="basicrum.analytics.footer"]');
    basicrum_assert_same(1, count($footerBlock), 'footer block declaration');
    basicrum_assert_same(
        'Basicrum_Analytics::footer.phtml',
        (string) $footerBlock[0]['template'],
        'footer template alias'
    );
    $footerViewModel = $frontendLayout->xpath(
        '//block[@name="basicrum.analytics.footer"]/arguments/argument[@name="view_model"]'
    );
    basicrum_assert_same(
        'Basicrum\\Analytics\\ViewModel\\Footer',
        trim((string) $footerViewModel[0]),
        'footer view model'
    );

    $adminLayout = simplexml_load_file($root . '/view/adminhtml/layout/adminhtml_system_config_edit.xml');
    basicrum_assert_same(
        'Basicrum_Analytics::css/basicrum-config.css',
        (string) $adminLayout->head->css['src'],
        'Admin stylesheet alias'
    );

    $logoBlock = (string) file_get_contents($root . '/Block/Adminhtml/System/Config/Logo.php');
    basicrum_assert_contains(
        'Basicrum_Analytics::system/config/logo.phtml',
        $logoBlock,
        'Admin logo template alias'
    );
    basicrum_assert_contains(
        'Basicrum_Analytics::images/basicrum-logo.png',
        $logoBlock,
        'Admin logo asset alias'
    );

    foreach ([
        'Model/Csp/BeaconPolicyCollector.php',
        'etc/di.xml',
        'view/adminhtml/layout/adminhtml_system_config_edit.xml',
        'view/adminhtml/templates/system/config/logo.phtml',
        'view/adminhtml/web/css/basicrum-config.css',
        'view/adminhtml/web/images/basicrum-logo.png',
        'tests/integration/admin.spec.js',
        'tests/integration/release-gate.sh',
        'CHANGELOG.md',
    ] as $packagedFile) {
        basicrum_assert_true(is_file($root . '/' . $packagedFile), 'required package file ' . $packagedFile);
    }

};

$tests['disposable integration guard rejects disabled modules and CLI failures before mutation'] =
    function () use ($runDisposableGuard): void {
        $disabled = $runDisposableGuard("Magento_Store", 0);
        basicrum_assert_same(1, $disabled['status'], 'disabled module must fail before configuration mutation');
        basicrum_assert_contains(
            'Basicrum_Analytics is not registered and enabled',
            $disabled['stderr'],
            'actionable disabled-module error'
        );
        basicrum_assert_not_contains(
            'configuration mutation',
            $disabled['stdout'] . $disabled['stderr'],
            'disabled module causes no Magento config mutation'
        );

        $failedStatus = $runDisposableGuard("Basicrum_Analytics", 42);
        basicrum_assert_same(1, $failedStatus['status'], 'failed module query must fail closed');
        basicrum_assert_contains(
            'Unable to read enabled Magento modules',
            $failedStatus['stderr'],
            'actionable Magento CLI failure'
        );
        basicrum_assert_not_contains(
            'configuration mutation',
            $failedStatus['stdout'] . $failedStatus['stderr'],
            'failed module query causes no Magento config mutation'
        );
    };

$tests['disposable integration guard accepts an enabled canonical module'] = function () use ($runDisposableGuard): void {
    $enabled = $runDisposableGuard("Magento_Store\nBasicrum_Analytics", 0);
    basicrum_assert_same(73, $enabled['status'], 'enabled module must progress beyond the guard');
    basicrum_assert_contains(
        'configuration mutation: config:set basicrum/general/enabled 1',
        $enabled['stderr'],
        'positive guard reaches the first intended configuration write'
    );
    basicrum_assert_not_contains(
        'is not registered and enabled',
        $enabled['stdout'] . $enabled['stderr'],
        'enabled module is accepted'
    );
    $missingRunner = $runDisposableGuard('Basicrum_Analytics', 0, false);
    basicrum_assert_same(1, $missingRunner['status'], 'missing pinned runner fails before configuration writes');
    basicrum_assert_contains('Run npm ci', $missingRunner['stderr'], 'actionable dependency error');
    basicrum_assert_not_contains('configuration mutation', $missingRunner['stderr'], 'no mutation without the runner');
};

$tests['native release gate requires a new 0.1.0 tag identity'] = function () use ($runReleaseGateGuard): void {
    $oldTag = $runReleaseGateGuard('0.0.2');
    basicrum_assert_same(1, $oldTag['status'], 'previous release tag must fail');
    basicrum_assert_contains('0.0.2 must not be reused', $oldTag['stderr'], 'actionable old-tag error');
    basicrum_assert_not_contains('MAGENTO_ROOT', $oldTag['stderr'], 'old tag fails before native work');

    $phaseOneTag = $runReleaseGateGuard('0.1.0');
    basicrum_assert_same(1, $phaseOneTag['status'], 'missing Magento root must still fail closed');
    basicrum_assert_contains('MAGENTO_ROOT must point', $phaseOneTag['stderr'], '0.1.0 passes the tag guard');
};

$tests['native release gate stops before mutations when identity or baseline checks fail'] = function () use ($runReleaseGateGuard): void {
    $temporaryRoot = sys_get_temp_dir() . '/basicrum-baseline-guard-' . bin2hex(random_bytes(6));
    $bin = $temporaryRoot . '/bin';
    basicrum_assert_true(mkdir($bin, 0700, true), 'create temporary command directory');
    try {
        // Only stub the external commands, not the release script being tested.
        file_put_contents($bin . '/php', <<<'SH'
#!/bin/sh
case "$1" in
    */check-artifact.php)
        if [ "${BASICRUM_FAKE_ARTIFACT_MISMATCH:-0}" = "1" ]; then
            echo 'artifact mismatch' >&2
            exit 67
        fi
        exit 0 ;;
    */check-installed-candidate.php)
        if [ "${BASICRUM_FAKE_SOURCE_MISMATCH:-0}" = "1" ]; then
            echo 'installed candidate mismatch' >&2
            exit 68
        fi
        exit 0 ;;
    */check-baseline.php) echo "baseline rejected: $1" >&2; exit 69 ;;
esac
exit 75
SH);
        file_put_contents($bin . '/git', <<<'SH'
#!/bin/sh
case "$3 $4" in
    'rev-parse --show-toplevel') printf '%s\n' "$2" ;;
    'status --porcelain') exit 0 ;;
    'rev-parse --verify') echo '1111111111111111111111111111111111111111' ;;
    *) exit 76 ;;
esac
SH);
        file_put_contents($bin . '/magento', "#!/bin/sh\necho 'unexpected Magento mutation' >&2\nexit 73\n");
        chmod($bin . '/php', 0700);
        chmod($bin . '/git', 0700);
        chmod($bin . '/magento', 0700);
        $environment = [
            'PATH' => $bin . ':' . getenv('PATH'),
            'MAGENTO_ROOT' => $temporaryRoot,
            'MAGENTO_STOREFRONT_URL' => 'https://magento.test/',
            'MAGENTO_ADMIN_URL' => 'https://magento.test/admin/',
            'MAGENTO_ADMIN_USERNAME' => 'synthetic',
            'MAGENTO_ADMIN_PASSWORD' => 'synthetic',
        ];
        $artifactMismatch = $runReleaseGateGuard('0.1.0', $environment + ['BASICRUM_FAKE_ARTIFACT_MISMATCH' => '1']);
        basicrum_assert_same(67, $artifactMismatch['status'], 'artifact mismatch propagates');
        basicrum_assert_not_contains('unexpected Magento mutation', $artifactMismatch['stderr'], 'no mutation on artifact mismatch');
        $mismatched = $runReleaseGateGuard('0.1.0', $environment + ['BASICRUM_FAKE_SOURCE_MISMATCH' => '1']);
        basicrum_assert_same(68, $mismatched['status'], 'installed candidate mismatch propagates');
        basicrum_assert_contains('installed candidate mismatch', $mismatched['stderr'], 'installed identity was checked');
        basicrum_assert_not_contains('baseline rejected', $mismatched['stderr'], 'identity fails before native baseline bootstrap');
        basicrum_assert_not_contains('unexpected Magento mutation', $mismatched['stderr'], 'no mutation on identity mismatch');
        $result = $runReleaseGateGuard('0.1.0', $environment);
        basicrum_assert_same(69, $result['status'], 'baseline failure propagates');
        basicrum_assert_contains('check-baseline.php', $result['stderr'], 'baseline check was executed');
        basicrum_assert_not_contains('unexpected Magento mutation', $result['stderr'], 'no native mutation ran');
    } finally {
        unlink($bin . '/php');
        unlink($bin . '/git');
        unlink($bin . '/magento');
        rmdir($bin);
        rmdir($temporaryRoot);
    }
};

$tests['committed candidate excludes ignored files and strictly checks the installed distribution'] = function (): void {
    $temporaryRoot = sys_get_temp_dir() . '/basicrum-candidate-' . bin2hex(random_bytes(6));
    foreach (['candidate', 'installed'] as $name) {
        $directory = $temporaryRoot . '/' . $name;
        mkdir($directory . '/view', 0700, true);
        file_put_contents($directory . '/registration.php', '<?php // fixture');
        file_put_contents($directory . '/composer.json', '{"archive":{"exclude":["/docs","/.gitignore","/.gitattributes"]}}');
        file_put_contents($directory . '/view/loader.js', '// reviewed loader');
    }
    $candidate = $temporaryRoot . '/candidate';
    $installed = $temporaryRoot . '/installed';
    $expectMismatch = static function (string $file) use ($candidate, $installed): void {
        try {
            basicrum_assert_installed_candidate($candidate, $installed);
        } catch (RuntimeException $exception) {
            basicrum_assert_contains($file, $exception->getMessage(), 'identify mismatched package file');
            return;
        }
        throw new RuntimeException('Incorrect installed candidate was accepted.');
    };
    try {
        mkdir($candidate . '/docs', 0700);
        file_put_contents($candidate . '/docs/notes.md', 'development only');
        file_put_contents($candidate . '/.gitignore', "*.secret\n");
        file_put_contents($candidate . '/.gitattributes', "/docs export-ignore\n/.gitignore export-ignore\n/.gitattributes export-ignore\n");
        basicrum_candidate_git($candidate, ['init', '--quiet', '--template=']);
        basicrum_candidate_git($candidate, ['add', '.']);
        basicrum_candidate_git($candidate, [
            '-c', 'user.name=Basicrum Test', '-c', 'user.email=test@example.test', '-c', 'core.hooksPath=/dev/null',
            'commit', '--quiet', '--no-gpg-sign', '-m', 'Synthetic package fixture'
        ]);
        $expected = basicrum_candidate_files($candidate);
        basicrum_assert_same(['composer.json', 'registration.php', 'view/loader.js'], array_keys($expected), 'committed production boundary');
        // Also cover a locally ignored file, independently of the tracked .gitignore.
        mkdir($candidate . '/.git/info', 0700);
        file_put_contents($candidate . '/.git/info/exclude', "local-only.txt\n");
        file_put_contents($candidate . '/local-only.txt', 'synthetic local data');
        file_put_contents($candidate . '/fixture.secret', 'synthetic, not a credential');
        basicrum_assert_same('', basicrum_candidate_git($candidate, ['status', '--porcelain']), 'ignored files do not dirty Git');
        basicrum_assert_same($expected, basicrum_candidate_files($candidate), 'ignored files cannot enter expected manifest');
        $archive = $temporaryRoot . '/package.tar';
        basicrum_candidate_git($candidate, ['archive', '--format=tar', '--output=' . $archive, 'HEAD']);
        mkdir($temporaryRoot . '/exported', 0700);
        (new PharData($archive))->extractTo($temporaryRoot . '/exported');
        basicrum_assert_installed_candidate($candidate, $temporaryRoot . '/exported');
        file_put_contents($candidate . '/view/loader.js', '// uncommitted change');
        basicrum_assert_same($expected, basicrum_candidate_files($candidate), 'manifest hashes committed blobs, not dirty files');
        basicrum_assert_installed_candidate($candidate, $installed);
        mkdir($installed . '/docs', 0700);
        file_put_contents($installed . '/docs/notes.md', 'stale development file');
        $expectMismatch('docs/notes.md');
        unlink($installed . '/docs/notes.md');
        rmdir($installed . '/docs');
        file_put_contents($installed . '/.unexpected', 'extra hidden file');
        $expectMismatch('.unexpected');
        unlink($installed . '/.unexpected');
        file_put_contents($installed . '/view/loader.js', '// stale loader');
        $expectMismatch('view/loader.js');
        unlink($installed . '/view/loader.js');
        $expectMismatch('view/loader.js');
        file_put_contents($installed . '/view/loader.js', '// reviewed loader');
        file_put_contents($installed . '/view/extra.js', '// unexpected asset');
        $expectMismatch('view/extra.js');
        unlink($installed . '/view/extra.js');
        symlink($candidate . '/view/loader.js', $installed . '/view/extra.js');
        $expectMismatch('view/extra.js');
        unlink($installed . '/view/extra.js');
        unlink($installed . '/registration.php');
        $expectMismatch('registration.php');
    } finally {
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temporaryRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        ) as $file) {
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($temporaryRoot);
    }
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
    basicrum_assert_false(
        Config::isValidBeaconEndpoint('https://user:secret@collector.test/beacon'),
        'embedded endpoint credentials'
    );
    basicrum_assert_false(
        Config::isValidBeaconEndpoint('https://collector.test/beacon#client-only'),
        'endpoint fragment'
    );
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

    $invalidEndpoint = new BeaconEndpoint($context, $registry, $scopeConfig, $cacheTypeList, $storeManager);
    $invalidEndpoint->setValue('https://user:secret@collector.example.test/beacon');
    try {
        $invalidEndpoint->beforeSave();
        throw new RuntimeException('credential-bearing endpoint did not throw');
    } catch (LocalizedException $exception) {
        basicrum_assert_contains('without embedded credentials', $exception->getMessage(), 'endpoint validation error');
    }

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
        basicrum_test_key($default, 0, 'basicrum/consent/mode') => 'implicit',
    ];

    $missing = new Config(new BasicrumTestScopeConfig($base));
    basicrum_assert_same(null, $missing->getRuntimeConfig($default), 'missing identity must be inactive');
    basicrum_assert_same('missing_endpoint', $missing->getStatus($default), 'admin missing endpoint');

    $base[basicrum_test_key($default, 0, Config::XML_PATH_BEACON_ENDPOINT)] = 'https://collector.test/beacon';
    $badSite = new Config(new BasicrumTestScopeConfig($base + [
        basicrum_test_key($default, 0, Config::XML_PATH_BRUM_SITE_ID) => 'invalid',
    ]));
    basicrum_assert_same(null, $badSite->getRuntimeConfig($default), 'invalid Site ID must be inactive');

    $base[basicrum_test_key($default, 0, Config::XML_PATH_BRUM_SITE_ID)] =
        '550e8400-e29b-41d4-a716-446655440000';
    $base[basicrum_test_key($default, 0, Config::XML_PATH_BEACON_ENDPOINT)] =
        'https://collector.test/beacon#client-only';
    $badEndpoint = new Config(new BasicrumTestScopeConfig($base));
    basicrum_assert_same(null, $badEndpoint->getRuntimeConfig($default), 'fragment endpoint must be inactive');
    basicrum_assert_same('invalid_endpoint', $badEndpoint->getStatus($default), 'admin invalid endpoint');

    $base[basicrum_test_key($default, 0, Config::XML_PATH_BEACON_ENDPOINT)] = 'https://collector.test/beacon';
    $valid = new Config(new BasicrumTestScopeConfig($base));
    $runtime = $valid->getRuntimeConfig($default);
    basicrum_assert_false(array_key_exists('consent_mode', $runtime), 'obsolete consent metadata is ignored');
    basicrum_assert_true($runtime['consent_enabled'], 'legacy mode must not grant consent');
    basicrum_assert_same('active_consent', $valid->getStatus($default), 'consent state');

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
    $getField = static function (SimpleXMLElement $config, string $groupId, string $fieldId): SimpleXMLElement {
        $matches = $config->xpath(sprintf(
            '/config/system/section[@id="basicrum"]/group[@id="%s"]/field[@id="%s"]',
            $groupId,
            $fieldId
        ));
        basicrum_assert_same(1, count($matches), $groupId . '/' . $fieldId . ' field');
        return $matches[0];
    };
    $fields = [
        $getField($xml, 'general', 'beacon_endpoint'),
        $getField($xml, 'general', 'brum_site_id'),
        $getField($xml, 'consent', 'enabled'),
        $getField($xml, 'privacy', 'strip_query_string'),
        $getField($xml, 'performance', 'wait_after_onload'),
        $getField($xml, 'performance', 'delay_ms'),
        $getField($xml, 'developer', 'development_mode'),
    ];

    foreach ($fields as $field) {
        foreach (['showInDefault', 'showInWebsite', 'showInStore'] as $scopeAttribute) {
            basicrum_assert_same('1', (string) $field[$scopeAttribute], $field['id'] . ' ' . $scopeAttribute);
        }
    }

    basicrum_assert_same(
        'Basicrum\\Analytics\\Model\\System\\Config\\Backend\\BeaconEndpoint',
        (string) $getField($xml, 'general', 'beacon_endpoint')->backend_model,
        'endpoint save validator'
    );
    basicrum_assert_same(
        'Basicrum\\Analytics\\Model\\System\\Config\\Backend\\BrumSiteId',
        (string) $getField($xml, 'general', 'brum_site_id')->backend_model,
        'Site ID save validator'
    );
    foreach (['monitoring_status' => 'Status', 'boomerang_version' => 'BoomerangVersion'] as $id => $renderer) {
        basicrum_assert_same(
            'Basicrum\\Analytics\\Block\\Adminhtml\\System\\Config\\' . $renderer,
            (string) $getField($xml, 'general', $id)->frontend_model,
            $id . ' display renderer'
        );
    }
    basicrum_assert_same(
        'Basicrum\\Analytics\\Block\\Adminhtml\\System\\Config\\ReadOnlyField',
        (string) $getField($xml, 'consent', 'integration_help')->frontend_model,
        'callback instructions use the display-only renderer'
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

    $render = static fn (array $values): string => basicrum_render_footer($values, $detector);

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
    basicrum_assert_contains('Basicrum_Analytics/js/', $consent, 'canonical static asset module identifier');
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

$tests['page type detector matches the exact Magento 1 labels on native Magento 2 actions'] = function (): void {
    // Expectations frozen from Magento 1's PageTypeDetector.php at f8c4e2d5aa71.
    // Route keys are deliberately Magento 2's, not copied Magento 1 handles.
    $expected = [
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

    foreach ($expected as $action => $label) {
        foreach ([$action, strtoupper($action)] as $casedAction) {
            $detector = new PageTypeDetector(new HttpRequest($casedAction), new HttpResponse(200));
            basicrum_assert_same($label, $detector->getPageType(), $casedAction . ' exact label');
            basicrum_assert_same($label === 'Home', $detector->isHomePage(), $casedAction . ' home helper');
            basicrum_assert_same($label === 'Product', $detector->isProductPage(), $casedAction . ' product helper');
            basicrum_assert_same($label === 'Checkout', $detector->isCheckoutPage(), $casedAction . ' checkout helper');
            $footer = new Footer($detector, new Config(new BasicrumTestScopeConfig()));
            basicrum_assert_same($label, $footer->getPageType(), $casedAction . ' reaches the view model unchanged');
        }
    }
};

$tests['page type detection prioritizes 404 and deliberately handles missing or unmapped actions'] = function (): void {
    foreach (['cms_index_index', 'catalog_product_view', 'custom_route_index', '__'] as $action) {
        $notFound = new PageTypeDetector(new HttpRequest($action), new HttpResponse(404));
        basicrum_assert_same('404 Not Found', $notFound->getPageType(), $action . ' status takes precedence');
        basicrum_assert_false($notFound->isHomePage(), '404 is not Home');
        basicrum_assert_false($notFound->isProductPage(), '404 is not Product');
        basicrum_assert_false($notFound->isCheckoutPage(), '404 is not Checkout');
    }

    foreach (['', '__'] as $action) {
        $unknown = new PageTypeDetector(new HttpRequest($action), new HttpResponse(200));
        basicrum_assert_same('unknown', $unknown->getPageType(), 'absent action has no fabricated label');
    }

    // Advanced-search results have no named Magento 1 mapping. Old Magento 1
    // routes must not classify an unrelated Magento 2 extension's actions.
    foreach ([
        'custom_route_index',
        'Custom_Route_Index',
        'catalogsearch_advanced_result',
        'checkout_onepage_index',
        'contacts_index_index',
        'sales_billing_agreement_view',
    ] as $action) {
        $unmapped = new PageTypeDetector(new HttpRequest($action), new HttpResponse(200));
        basicrum_assert_same('unmapped_' . strtolower($action), $unmapped->getPageType(), $action . ' diagnostic fallback');
    }
};

$tests['CSP collector follows the effective runtime gate and emits origin-only fetch policies'] = function (): void {
    $default = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
    $existingPolicy = new \Magento\Csp\Model\Policy\FetchPolicy('default-src', false, ["'self'"]);

    $frontend = new State(Area::AREA_FRONTEND);
    $inactiveCollector = new BeaconPolicyCollector(new Config(new BasicrumTestScopeConfig()), $frontend);
    basicrum_assert_same(
        [$existingPolicy],
        $inactiveCollector->collect([$existingPolicy]),
        'inactive configuration must not add a CSP origin'
    );

    $values = [
        basicrum_test_key($default, 0, Config::XML_PATH_ENABLED) => '1',
        basicrum_test_key($default, 0, Config::XML_PATH_BEACON_ENDPOINT) =>
            'http://collector.test:8443/path/to/beacon?token=secret',
        basicrum_test_key($default, 0, Config::XML_PATH_BRUM_SITE_ID) =>
            '550e8400-e29b-41d4-a716-446655440000',
        basicrum_test_key($default, 0, Config::XML_PATH_DEVELOPMENT_MODE) => '0',
    ];
    $activeCollector = new BeaconPolicyCollector(new Config(new BasicrumTestScopeConfig($values)), $frontend);
    $policies = $activeCollector->collect();
    basicrum_assert_same(
        $existingPolicy,
        $activeCollector->collect([$existingPolicy])[0],
        'existing policies are passed through for Magento to merge, not replaced'
    );

    foreach (['adminhtml', 'webapi_rest', 'graphql', 'crontab', null] as $area) {
        $nonFrontendCollector = new BeaconPolicyCollector(
            new Config(new BasicrumTestScopeConfig($values)),
            new State($area)
        );
        basicrum_assert_same(
            [$existingPolicy],
            $nonFrontendCollector->collect([$existingPolicy]),
            'non-storefront and unset areas must not add collector policies'
        );
    }

    basicrum_assert_same(2, count($policies), 'collector must add both Boomerang fetch directives');
    basicrum_assert_same('connect-src', $policies[0]->getId(), 'XHR and sendBeacon directive');
    basicrum_assert_same('img-src', $policies[1]->getId(), 'image fallback directive');
    basicrum_assert_same(
        ['https://collector.test:8443'],
        $policies[0]->getHostSources(),
        'runtime HTTPS normalization and origin-only policy'
    );
    basicrum_assert_same(
        ['https://collector.test:8443'],
        $policies[1]->getHostSources(),
        'query and path must not enter the image policy'
    );
    basicrum_assert_false($policies[0]->isNoneAllowed(), 'collector origin must be usable');

    $values[basicrum_test_key($default, 0, Config::XML_PATH_DEVELOPMENT_MODE)] = '1';
    $values[basicrum_test_key($default, 0, Config::XML_PATH_BEACON_ENDPOINT)] =
        'http://127.0.0.1:8080/beacon';
    $developmentPolicies = (
        new BeaconPolicyCollector(new Config(new BasicrumTestScopeConfig($values)), $frontend)
    )->collect();
    basicrum_assert_same(
        ['http://127.0.0.1:8080'],
        $developmentPolicies[0]->getHostSources(),
        'explicit development HTTP exception must carry into CSP'
    );
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
