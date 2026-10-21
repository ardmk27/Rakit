<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

/** Menyajikan foto sumber hanya kepada Posyandu pemiliknya (foto tidak bisa diakses langsung). */
$p = require_login();
$sesi = find_session(db(), (int)($_GET['id'] ?? 0), (int)$p['id']) ?: abort_404();
if (!$sesi['photo_path']) {
    abort_404();
}
$bytes = photo_load(db(), (int)$sesi['id']);
if ($bytes === null) {
    abort_404();
}
$mime = in_array($sesi['photo_mime'], ['image/jpeg', 'image/png'], true) ? $sesi['photo_mime'] : 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: private, max-age=300');
header('Content-Disposition: inline');
echo $bytes;
