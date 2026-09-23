<?php
declare(strict_types=1);

// Use the same validating VCS import path as Packagist, not just root validation.
// Run with Composer 2.10's PHAR path as the only argument; no network is needed.
Phar::loadPhar($argv[1], 'composer.phar');
require 'phar://composer.phar/vendor/autoload.php';

use Composer\Config;
use Composer\IO\BufferIO;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Loader\ValidatingArrayLoader;
use Composer\Repository\VcsRepository;
use Composer\Util\Filesystem;
use Composer\Util\HttpDownloader;
use Composer\Util\ProcessExecutor;

$candidate = json_decode(
    file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$oldName = 'basicrum/basicrum-analytics';
$newName = 'basicrum/basicrum-magento-2';
$fixture = sys_get_temp_dir() . '/basicrum-composer-import-' . bin2hex(random_bytes(8));
if (!mkdir($fixture, 0700)) {
    throw new RuntimeException('Cannot create the disposable VCS fixture.');
}
$process = new ProcessExecutor();
$git = static function (array $args) use ($fixture, $process): void {
    $command = array_merge(['git', '-C', $fixture], $args);
    if ($process->execute($command, $output) !== 0) {
        throw new RuntimeException('Fixture Git command failed: ' . $process->getErrorOutput());
    }
};
$writeManifest = static function (array $data) use ($fixture): void {
    file_put_contents($fixture . '/composer.json', json_encode($data, JSON_THROW_ON_ERROR));
};
$checkImport = static function (string $expectedName) use ($fixture, $process): void {
    $io = new BufferIO();
    $config = new Config(false);
    $config->merge(['config' => ['home' => $fixture, 'cache-dir' => $fixture . '/cache']]);
    $repository = new VcsRepository(
        ['type' => 'git', 'url' => $fixture],
        $io,
        $config,
        new HttpDownloader($io, $config),
        null,
        $process
    );
    $repository->setLoader(new ValidatingArrayLoader(new ArrayLoader()));
    $packages = $repository->getPackages();
    if ($repository->hadInvalidBranches()) {
        throw new RuntimeException('Invalid VCS branch under ' . $expectedName . ":\n" . $io->getOutput());
    }
    foreach ($packages as $package) {
        if ($package->getName() !== $expectedName) {
            throw new RuntimeException('VCS import did not use the default branch package name.');
        }
    }
    foreach (['dev-main', 'dev-rename', '0.0.2'] as $version) {
        if ($repository->findPackage($expectedName, $version) === null) {
            throw new RuntimeException('VCS import omitted ' . $expectedName . ' ' . $version);
        }
    }
    echo 'PASS: validated default branch, rename branch and historical tag under ' . $expectedName . ".\n";
};

try {
    $git(['init', '--initial-branch=main', '--template=']);
    $git(['config', 'user.name', 'Basicrum test']);
    $git(['config', 'user.email', 'test@example.test']);
    $git(['config', 'commit.gpgsign', 'false']);
    // A tiny synthetic historical release; the rename branch uses real metadata.
    $writeManifest(['name' => $oldName, 'description' => 'VCS import fixture', 'license' => 'MIT']);
    $git(['add', 'composer.json']);
    $git(['commit', '-m', 'Historical package']);
    $git(['tag', '0.0.2']);
    $git(['checkout', '-b', 'rename']);
    $writeManifest($candidate);
    $git(['commit', '-am', 'Candidate package metadata']);
    $git(['checkout', 'main']);
    $checkImport($oldName);

    // Model the same repository after the naming change reaches its default branch.
    $writeManifest($candidate);
    $git(['commit', '-am', 'Rename on default branch']);
    $checkImport($newName);
} finally {
    // This random directory was created above and contains only this test's fixture.
    (new Filesystem())->removeDirectory($fixture);
}
