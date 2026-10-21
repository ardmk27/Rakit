<?php
// Hanya untuk pengembangan lokal: php -S 127.0.0.1:8099 -t public router_dev.php
$f = __DIR__ . '/public' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file($f)) { return false; }
require __DIR__ . '/api/index.php';
