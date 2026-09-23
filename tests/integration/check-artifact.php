<?php
declare(strict_types=1);

require __DIR__ . '/candidate-files.php';

$path = $argv[1] ?? '';
$zip = new ZipArchive();
if ($path === '' || $zip->open($path) !== true) {
    throw new RuntimeException('Pass the committed distribution ZIP to verify.');
}
$actual = [];
for ($index = 0; $index < $zip->numFiles; $index++) {
    $name = $zip->getNameIndex($index);
    if (str_ends_with($name, '/')) {
        continue;
    }
    if (isset($actual[$name])) {
        throw new RuntimeException('Duplicate archive entry: ' . $name);
    }
    $actual[$name] = hash('sha256', $zip->getFromIndex($index));
}
$zip->close();
$expected = basicrum_candidate_files(dirname(__DIR__, 2));
ksort($actual);
if ($actual !== $expected) {
    throw new RuntimeException('Distribution contents differ from the candidate production manifest.');
}
echo 'PASS: distribution ZIP contains exactly ' . count($actual) . ' candidate production files; SHA-256 '
    . hash_file('sha256', $path) . PHP_EOL;
