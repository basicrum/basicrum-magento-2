<?php
declare(strict_types=1);

// Inspect the generated map without loading registration.php or test doubles.
$root = dirname(__DIR__, 2);
$classmap = require $root . '/vendor/composer/autoload_classmap.php';
if (!isset($classmap['Basicrum\\Analytics\\Model\\Config'])) {
    throw new RuntimeException('Production module classes must remain in the optimized classmap.');
}
foreach ($classmap as $class => $file) {
    if (str_starts_with($class, 'BasicrumTest')
        || str_starts_with(str_replace('\\', '/', $file), $root . '/tests/')
    ) {
        throw new RuntimeException('Test code leaked into the production classmap: ' . $class);
    }
}
echo "PASS: optimized classmap contains production classes, not test doubles.\n";
