<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/bootstrap.php';
$cmd = $argv[1] ?? 'seed';
if (!in_array($cmd, ['seed', 'reset'], true)) { fwrite(STDERR, "Gunakan seed atau reset\n"); exit(1); }
if ($cmd === 'reset') {
    // Only owned lab paths; no recursive traversal or user-selected filesystem paths.
    foreach ([LAB_DATA.'/sessions', PRIVATE_UPLOADS, PUBLIC_UPLOADS] as $dir) {
        foreach (glob($dir.'/*') ?: [] as $path) if (is_file($path) || is_link($path)) unlink($path);
    }
    foreach (['lab.sqlite', 'lab.sqlite-wal', 'lab.sqlite-shm', 'lab.sqlite-journal'] as $file) {
        if (is_file(LAB_DATA.'/'.$file)) unlink(LAB_DATA.'/'.$file);
    }
}
seed();
echo 'Lab ' . LAB_MODE . ' siap (' . $cmd . ").\n";
