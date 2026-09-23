<?php
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

require __DIR__ . '/candidate-files.php';
$magentoRoot = getenv('MAGENTO_ROOT') ?: '';
if (!is_file($magentoRoot . '/app/bootstrap.php')) {
    throw new RuntimeException('MAGENTO_ROOT must point to an installed Magento instance.');
}
// Registration only: no area, database, configuration write or generated DI is needed.
require $magentoRoot . '/app/bootstrap.php';
$installedRoot = (new ComponentRegistrar())->getPath(ComponentRegistrar::MODULE, 'Basicrum_Analytics');
if (!$installedRoot || !is_dir($installedRoot)) {
    throw new RuntimeException('Basicrum_Analytics is not registered in this Magento installation.');
}
basicrum_assert_installed_candidate(dirname(__DIR__, 2), realpath($installedRoot));
echo 'PASS: registered Basicrum installation exactly matches the committed production files.' . PHP_EOL;
