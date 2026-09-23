<?php
declare(strict_types=1);

/** Compare the exact Magento patch and declared major/minor platform lines. */
function basicrum_baseline_errors(array $expected, array $actual): array
{
    $errors = [];
    foreach (['MAGENTO_VERSION', 'PHP_VERSION', 'COMPOSER_VERSION', 'MARIADB_VERSION', 'OPENSEARCH_VERSION'] as $key) {
        $want = $expected[$key] ?? '';
        $found = $actual[$key] ?? '';
        $matches = $key === 'MAGENTO_VERSION'
            ? $found === $want
            : preg_match('/^' . preg_quote($want, '/') . '(?:[.-]|$)/', $found) === 1;
        if ($want === '' || $found === '' || !$matches) {
            $errors[$key] = $key . ': expected ' . $want . ', found ' . ($found ?: '(unavailable)');
        }
    }
    return $errors;
}
