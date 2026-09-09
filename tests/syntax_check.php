<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$failed = [];
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path);
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        $failed[] = implode(PHP_EOL, $output);
    }
}

if ($failed) {
    echo implode(PHP_EOL, $failed) . PHP_EOL;
    exit(1);
}

echo "All PHP syntax checks passed\n";
