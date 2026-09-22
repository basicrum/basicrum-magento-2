<?php
declare(strict_types=1);

use Magento\AdvancedSearch\Model\Client\ClientResolver;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\ResourceConnection;

require __DIR__ . '/baseline.php';
require __DIR__ . '/bootstrap.php';

$expected = parse_ini_file(__DIR__ . '/baseline.env', false, INI_SCANNER_RAW);
$metadata = $objectManager->get(ProductMetadataInterface::class);
$actual = ['MAGENTO_VERSION' => $metadata->getVersion(), 'PHP_VERSION' => PHP_VERSION];
if ($metadata->getEdition() !== 'Community') {
    throw new RuntimeException('The declared baseline is Magento Open Source (Community edition).');
}
// Refuse incompatible core runtimes before connecting to optional services.
foreach (basicrum_baseline_errors($expected, $actual) as $key => $error) {
    if (isset($actual[$key])) {
        throw new RuntimeException($error);
    }
}

$pipes = [];
$process = proc_open(['composer', '--no-ansi', '--version'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($process)) {
    throw new RuntimeException('Composer is required on PATH.');
}
$composerOutput = stream_get_contents($pipes[1]);
stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
if (proc_close($process) !== 0 || !preg_match('/Composer version (\d+\.\d+\.\d+)/', $composerOutput, $matches)) {
    throw new RuntimeException('Unable to identify the installed Composer version.');
}
$actual['COMPOSER_VERSION'] = $matches[1];
$databaseVersion = (string) $objectManager->get(ResourceConnection::class)->getConnection()->fetchOne('SELECT VERSION()');
if (stripos($databaseVersion, 'MariaDB') === false) {
    throw new RuntimeException('The declared baseline requires MariaDB.');
}
$actual['MARIADB_VERSION'] = $databaseVersion;
$resolver = $objectManager->get(ClientResolver::class);
if ($resolver->getCurrentEngine() !== 'opensearch') {
    throw new RuntimeException('The declared baseline requires the configured OpenSearch engine.');
}
$info = $resolver->create()->getOpenSearchClient()->info();
if (($info['version']['distribution'] ?? '') !== 'opensearch') {
    throw new RuntimeException('The configured search server did not identify itself as OpenSearch.');
}
$actual['OPENSEARCH_VERSION'] = $info['version']['number'] ?? '';
$errors = basicrum_baseline_errors($expected, $actual);
if ($errors !== []) {
    throw new RuntimeException(implode("\n", $errors));
}
echo 'PASS: installed Magento/PHP/Composer/MariaDB/OpenSearch match baseline.env.' . PHP_EOL;
