<?php
// Temporary syntax-check helper: recursively lints every PHP file in the project.
$root = dirname(__DIR__);
$php  = PHP_BINARY;
$bad  = 0;

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $out = [];
    $code = 0;
    exec(escapeshellarg($php) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $bad++;
        echo $file->getPathname() . ' :: ' . implode(' | ', $out) . PHP_EOL;
    }
}

echo "LINT-DONE errors={$bad}" . PHP_EOL;
