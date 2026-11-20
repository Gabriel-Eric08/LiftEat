<?php

declare(strict_types=1);

/*
 * Checa a sintaxe de todos os arquivos PHP do projeto (fora vendor/).
 * Uso: composer lint
 */

$root = dirname(__DIR__);
$skip = ['vendor', '.git', 'storage', 'node_modules'];

$files = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static fn (SplFileInfo $f) => !($f->isDir() && in_array($f->getFilename(), $skip, true)),
    ),
);

$errors = 0;
$count = 0;
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $count++;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $errors++;
        echo implode(PHP_EOL, $out), PHP_EOL;
    }
    $out = [];
}

echo $errors === 0 ? "Lint ok: {$count} arquivos." : "Lint: {$errors} arquivo(s) com erro.", PHP_EOL;
exit($errors === 0 ? 0 : 1);
