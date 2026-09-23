<?php
declare(strict_types=1);

/** Run Git without a shell; object contents, not working-tree files, define the candidate. */
function basicrum_candidate_git(string $root, array $arguments): string
{
    $process = proc_open(['git', '-C', $root, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to inspect the candidate commit.');
    }
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Unable to inspect the candidate commit: ' . trim($error));
    }
    return $output;
}

/** Hash only committed production blobs. Ignored or untracked disk files cannot become expected files. */
function basicrum_candidate_files(string $root): array
{
    $commit = trim(basicrum_candidate_git($root, ['rev-parse', '--verify', 'HEAD']));
    $metadata = json_decode(basicrum_candidate_git($root, ['show', $commit . ':composer.json']), true, 512, JSON_THROW_ON_ERROR);
    $excluded = array_map(
        static fn (string $path): string => ltrim($path, '/'),
        $metadata['archive']['exclude']
    );
    $files = [];
    foreach (explode("\0", rtrim(basicrum_candidate_git($root, ['ls-tree', '-rz', $commit]), "\0")) as $entry) {
        [$object, $path] = explode("\t", $entry, 2);
        if (in_array(explode('/', $path)[0], $excluded, true)) {
            continue;
        }
        [$mode, $type, $id] = explode(' ', $object);
        if ($type !== 'blob' || !in_array($mode, ['100644', '100755'], true)) {
            throw new RuntimeException('Unsupported candidate file: ' . $path);
        }
        $files[$path] = hash('sha256', basicrum_candidate_git($root, ['cat-file', 'blob', $id]));
    }
    if (!isset($files['registration.php'], $files['composer.json'])) {
        throw new RuntimeException('The candidate commit must contain registration.php and composer.json.');
    }
    ksort($files);
    return $files;
}

/** A distribution installation has no development-file exceptions, even for hidden files. */
function basicrum_installed_files(string $root): array
{
    $files = [];
    $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
    foreach (new RecursiveIteratorIterator($directory) as $file) {
        $relative = substr($file->getPathname(), strlen($root) + 1);
        // The module root may be a symlink; links inside the package are never supported.
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
    $installed = basicrum_installed_files($installedRoot);
    foreach (array_unique(array_merge(array_keys($candidate), array_keys($installed))) as $file) {
        if (($candidate[$file] ?? null) !== ($installed[$file] ?? null)) {
            throw new RuntimeException('Installed Basicrum module differs from the candidate: ' . $file);
        }
    }
}
