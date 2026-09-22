<?php
declare(strict_types=1);

/** Hash package files, including extra installed files, but not development/output directories. */
function basicrum_candidate_files(string $root): array
{
    if (!is_file($root . '/registration.php') || !is_file($root . '/composer.json')) {
        throw new RuntimeException('The candidate and installed module must contain registration.php and composer.json.');
    }
    $excluded = ['.git', '.github', '.test-results', 'docs', 'tests', 'node_modules', 'vendor',
        'test-results', 'playwright-report'];
    $files = [];
    $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
    $filter = new RecursiveCallbackFilterIterator($directory, static function (SplFileInfo $file) use ($root, $excluded): bool {
        return $file->getPath() !== $root || !in_array($file->getFilename(), $excluded, true);
    });
    foreach (new RecursiveIteratorIterator($filter) as $file) {
        $relative = substr($file->getPathname(), strlen($root) + 1);
        // A source symlink can change outside the clean checkout. The module root itself may be a symlink.
        if ($file->isLink() || !$file->isFile()) {
            throw new RuntimeException('Unsupported candidate file: ' . $relative);
        }
        $hash = hash_file('sha256', $file->getPathname());
        if ($hash === false) {
            throw new RuntimeException('Unable to hash candidate file: ' . $relative);
        }
        $files[$relative] = $hash;
    }
    ksort($files);
    return $files;
}

function basicrum_assert_installed_candidate(string $candidateRoot, string $installedRoot): void
{
    $candidate = basicrum_candidate_files($candidateRoot);
    $installed = basicrum_candidate_files($installedRoot);
    foreach (array_unique(array_merge(array_keys($candidate), array_keys($installed))) as $file) {
        if (($candidate[$file] ?? null) !== ($installed[$file] ?? null)) {
            throw new RuntimeException('Installed Basicrum module differs from the candidate: ' . $file);
        }
    }
}
