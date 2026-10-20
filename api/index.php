<?php
declare(strict_types=1);

/**
 * Front controller untuk Vercel (runtime vercel-php).
 * Semua request non-statis masuk ke sini, lalu diteruskan ke halaman PHP di folder app/.
 * Hanya berkas .php tepat di dalam app/ yang bisa dipanggil (bukan includes/, config/, dst).
 */
$appDir = realpath(__DIR__ . '/../app');

$path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = trim(rawurldecode($path), '/');
if ($path === '') {
    $path = 'index';
}
if (!str_ends_with($path, '.php')) {
    $path .= '.php';
}

if (!preg_match('/^[A-Za-z0-9_]+\.php$/', $path) || !is_file($appDir . '/' . $path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Halaman tidak ditemukan.');
}

// Di belakang proxy Vercel koneksi ke PHP berupa HTTP biasa; asalnya HTTPS.
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
$_SERVER['SCRIPT_NAME'] = '/' . $path;
$_SERVER['PHP_SELF'] = '/' . $path;
$_SERVER['SCRIPT_FILENAME'] = $appDir . '/' . $path;

chdir($appDir);
require $appDir . '/' . $path;
