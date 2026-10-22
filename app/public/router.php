<?php
// Local PHP server: match Apache behavior without exposing files outside public/.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath(__DIR__.$path);
if (str_starts_with($path,'/uploads/') && getenv('APP_MODE') === 'fixed') { http_response_code(403); exit; }
if ($file && str_starts_with($file,__DIR__.'/') && is_file($file) && $path!=='/router.php') return false;
require __DIR__.'/index.php';
