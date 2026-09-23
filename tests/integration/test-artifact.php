<?php
declare(strict_types=1);

// Run after build-artifact.sh; mutate temporary copies, never the candidate ZIP.
$artifact = $argv[1] ?? dirname(__DIR__, 2) . '/.test-results/package/basicrum-analytics.zip';
if (!is_file($artifact)) {
    throw new RuntimeException('Build the candidate ZIP before running archive regression checks.');
}
$mutations = [
    'ignored local file' => static fn (ZipArchive $zip) => $zip->addFromString('.DS_Store', 'synthetic local data'),
    'development file' => static fn (ZipArchive $zip) => $zip->addFromString('docs/stale.md', 'stale development data'),
    'missing file' => static fn (ZipArchive $zip) => $zip->deleteName('registration.php'),
    'modified file' => static fn (ZipArchive $zip) => $zip->addFromString('registration.php', '<?php // stale'),
];
foreach ($mutations as $name => $mutate) {
    $temporary = tempnam(sys_get_temp_dir(), 'basicrum-archive-');
    try {
        if (!copy($artifact, $temporary)) {
            throw new RuntimeException('Unable to copy the candidate ZIP.');
        }
        $zip = new ZipArchive();
        if ($zip->open($temporary) !== true || !$mutate($zip) || !$zip->close()) {
            throw new RuntimeException('Unable to prepare archive regression: ' . $name);
        }
        $process = proc_open([PHP_BINARY, __DIR__ . '/check-artifact.php', $temporary],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) === 0 || !str_contains($stderr . $stdout, 'Distribution contents differ')) {
            throw new RuntimeException('Archive verification did not reject ' . $name . ': ' . $stderr . $stdout);
        }
        echo 'PASS: archive rejects ' . $name . PHP_EOL;
    } finally {
        unlink($temporary);
    }
}
