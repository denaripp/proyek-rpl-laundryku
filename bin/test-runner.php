<?php

declare(strict_types=1);

$php = escapeshellarg(PHP_BINARY);
$artisan = escapeshellarg(__DIR__ . '/../artisan');

// Forward optional arguments (e.g. --filter, --testsuite)
$extraArgs = array_slice($argv, 1);
$forwardedArgs = '';
if (!empty($extraArgs)) {
    $forwardedArgs = ' ' . implode(' ', array_map('escapeshellarg', $extraArgs));
}

echo "==> [Test Runner] Membersihkan config cache sebelum pengujian...\n";
passthru("{$php} {$artisan} config:clear", $clearStatus);
if ($clearStatus !== 0) {
    fwrite(STDERR, "ERROR: Gagal membersihkan config cache (exit code: {$clearStatus})\n");
    exit($clearStatus);
}

$testExitCode = 1;
try {
    echo "==> [Test Runner] Menjalankan test suite...\n";
    passthru("{$php} {$artisan} test{$forwardedArgs}", $testExitCode);
} finally {
    echo "==> [Test Runner] Memulihkan config cache...\n";
    passthru("{$php} {$artisan} config:cache", $cacheStatus);
    if ($cacheStatus !== 0) {
        fwrite(STDERR, "FATAL: Gagal memulihkan config cache (exit code: {$cacheStatus})!\n");
        if ($testExitCode === 0) {
            $testExitCode = $cacheStatus;
        }
    } else {
        echo "==> [Test Runner] Config cache berhasil dipulihkan.\n";
    }
}

exit($testExitCode);
