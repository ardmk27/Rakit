# Deploy Rakit ke Vercel

Vercel tidak punya PHP bawaan, jadi proyek ini memakai runtime komunitas `vercel-php` (PHP 8.3).
Karena Vercel serverless, dua hal dipindah ke database: **sesi login** dan **foto unggahan**.

## Struktur
```
api/index.php      front controller: meneruskan /login, /upload.php, dst ke app/
api/php.ini        batas unggahan (4 MB) dan zona waktu
app/               seluruh halaman PHP + includes/, config/, services/, database/
public/assets/     CSS, JS, gambar (disajikan statis)
vercel.json        runtime, maxDuration 60 dtk, routing
extras/            tes, eval, docs, Dockerfile (tidak ikut di-deploy)
```

## Langkah
### 1. Siapkan database MySQL (wajib, di luar Vercel)
Contoh gratis: **TiDB Cloud Starter** (kompatibel MySQL). Alternatif: Aiven, Railway, PlanetScale, atau MySQL/MariaDB mana pun yang bisa diakses dari internet dengan TLS.
Buat database, lalu jalankan seluruh isi `app/database/schema_hosted.sql` (lewat SQL editor penyedia atau klien `mysql`).

### 2. Push ke GitHub dan impor di Vercel
Push folder ini ke repo, lalu *Add New → Project* di Vercel. Framework Preset: **Other**. Biarkan Build Command/Output kosong.

### 3. Environment Variables (Project → Settings)
| Nama | Isi |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | dari penyedia database |
| `DB_SSL` | `true` |
| `OCR_MODE` | `gemini` (atau `mock` untuk demo) |
| `GEMINI_API_KEY` | kunci dari Google AI Studio (jika `OCR_MODE=gemini`) |
| `GEMINI_MODEL` | opsional, mis. `gemini-2.5-flash` |
| `MAX_UPLOAD_MB` | `4` |
| `DEMO_SEED_ENABLED` | `true` hanya untuk instans demo |

Lalu **Deploy**. Kamera browser butuh HTTPS, dan domain `*.vercel.app` sudah HTTPS.

### 4. Cek
Buka `https://<proyek>.vercel.app/` → Daftarkan Posyandu → simpan ID dan kode akses → masuk → unggah foto.

## Catatan penting
- **Batas unggahan 4,5 MB** pada Vercel Functions. Browser sudah mengecilkan foto ke 2000 px (biasanya < 1 MB), jadi aman.
- **Waktu proses**: pembacaan Gemini bisa lama; fungsi diberi batas 60 detik di `vercel.json`. Mode `OCR_TWO_PASS=true` melipatgandakan waktu, tidak disarankan di paket Hobby.
- **Foto tersimpan di database** (tabel `sesi_foto_data`), bukan di disk. Pantau ukuran database; tombol "Hapus foto sumber" tetap berfungsi.
- **Sesi login** disimpan di tabel `php_sessions` (otomatis aktif bila berjalan di Vercel).
- Pembatasan percobaan login memakai IP asli dari header Vercel.
- Database lama (dari versi sebelumnya)? Jalankan `php app/database/migrate.php` dengan `.env` yang menunjuk ke database itu untuk menambah dua tabel baru.
- Data anak sungguhan: pastikan ada izin dan kebijakan privasi sebelum dipakai di luar demo.

## Menguji lokal tanpa Vercel
```
VERCEL=1 DB_HOST=127.0.0.1 DB_NAME=... DB_USER=... DB_PASS=... OCR_MODE=mock \
php -S 127.0.0.1:8099 -c api/php.ini -t public router_dev.php
```
(`router_dev.php`: sajikan berkas dari `public/` bila ada, selain itu `require 'api/index.php'`.)
