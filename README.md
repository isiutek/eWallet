# eWallet: Aplikasi Dompet Digital (Laravel 11)

Aplikasi dompet digital berbasis web + REST API dengan fitur:

1. **Transaksi masuk & keluar**: transfer antar pengguna (no. dompet / email / no. HP), saldo dicatat dengan ledger pencatatan ganda.
2. **Isi saldo**: mode **manual** (upload bukti, verifikasi admin), **otomatis** via **Xendit Invoice** (VA, QRIS, e-wallet, kartu), atau **keduanya**. Diatur admin di menu *Admin → Pengaturan*.
3. **Laporan transaksi**: filter tanggal, jenis, arah, pencarian, ringkasan masuk/keluar, ekspor CSV (pengguna & admin).
4. **Dashboard**: saldo, ringkasan bulan ini, batas harian, grafik arus kas 14 hari, transaksi terbaru; dashboard admin terpisah.
5. **Keamanan berlapis** (lihat bagian di bawah).
6. **Transaksi QR Code**: QR statis (nominal diisi pembayar) dan QR dinamis (nominal tetap, sekali pakai, 15 menit). Bayar dengan memindai lewat kamera, unggah gambar, atau tempel kode.

## Kebutuhan

- PHP 8.2+ (ekstensi `pdo_pgsql`, `gd`), Composer
- PostgreSQL 13+ (MySQL 8 juga bisa, ganti `DB_CONNECTION`)
- Node.js 18+ (hanya untuk build aset)

## Instalasi

```bash
composer install
cp .env.example .env
php artisan key:generate
# isi DB_* di .env, lalu:
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Akun awal dari seeder:

| Peran | Email | Password | PIN |
|---|---|---|---|
| Admin | admin@ewallet.test | Admin12345 | (buat sendiri) |
| Demo (hanya `APP_ENV=local`) | budi@ewallet.test / siti@ewallet.test | Password123 | 246810 |

**Segera ganti password admin di production.**

## Pasang di VPS (Ubuntu 22.04 / 24.04)

Arahkan DNS domain ke IP VPS, lalu di VPS sebagai root:

```bash
curl -fsSL https://raw.githubusercontent.com/isiutek/eWallet/main/deploy/install.sh -o install.sh
sudo bash install.sh dompet.domainanda.com email@anda.com
```

Skrip memasang Nginx, PHP 8.3-FPM, PostgreSQL, Node.js, membuat database dengan password acak, build aplikasi, memasang HTTPS (Let's Encrypt), firewall, dan menampilkan **password admin acak** di akhir. Belum punya domain? Pakai IP VPS sebagai argumen pertama (tanpa HTTPS, hanya untuk uji coba).

Memperbarui ke versi terbaru: `sudo bash /var/www/ewallet/deploy/update.sh`

## Mengaktifkan Xendit (isi saldo otomatis)

1. Di dashboard Xendit, ambil **Secret API Key** (Settings → API Keys, izin *Money-in: Write*).
2. Di Settings → Webhooks, set URL **Invoices paid** ke `https://domain-anda/webhooks/xendit/invoice`, lalu salin **Verification Token**.
3. Isi `.env`:
   ```
   XENDIT_SECRET_KEY=xnd_development_...
   XENDIT_CALLBACK_TOKEN=token-verifikasi
   XENDIT_VERIFY_WITH_API=true
   ```
4. Di *Admin → Pengaturan*, pilih mode isi saldo "Otomatis" atau "Keduanya".

Alur: pengguna memilih nominal → diarahkan ke halaman bayar Xendit → Xendit memanggil webhook → aplikasi memverifikasi token, **memeriksa ulang status invoice ke API Xendit**, mencocokkan nominal, lalu menambah saldo (idempoten, webhook ganda tidak menambah saldo dua kali).

## Keamanan berlapis

| Lapisan | Implementasi |
|---|---|
| Login | Password kuat (huruf besar/kecil + angka), rate limit per email+IP, akun terkunci 15 menit setelah 5 gagal |
| 2FA | TOTP (Google Authenticator/Authy), secret terenkripsi di database |
| PIN transaksi | 6 digit terpisah dari password (di-hash bcrypt), wajib untuk transfer & bayar QR, terkunci 30 menit setelah 5 salah, PIN lemah ditolak |
| Integritas saldo | DB transaction + `SELECT ... FOR UPDATE`, urutan lock konsisten (anti deadlock), saldo tidak bisa minus, ledger dengan saldo sebelum/sesudah |
| Anti transaksi ganda | Idempotency key di setiap form/permintaan API |
| Batas transaksi | Minimal transfer & batas total keluar harian (diatur admin), rate limit 10 transaksi/menit |
| QR | Payload ditandatangani HMAC-SHA256, QR dinamis sekali pakai dengan masa berlaku |
| Webhook | Verifikasi token (`hash_equals`), verifikasi ulang ke API Xendit, cocokkan ID invoice & nominal |
| Sesi & HTTP | Sesi terenkripsi, CSRF, header keamanan (X-Frame-Options, nosniff, HSTS, dll.) |
| Otorisasi | Pengguna hanya bisa melihat transaksinya sendiri; area admin dilindungi middleware; admin bisa membekukan akun |
| Audit | Semua aktivitas penting (login, gagal PIN, transfer, top up, perubahan pengaturan) tercatat dengan IP & user agent |
| Berkas | Bukti transfer disimpan di disk privat, hanya admin yang bisa membuka |

Rekomendasi production: jalankan di HTTPS, `APP_DEBUG=false`, backup database rutin, dan batasi akses ke `/admin` (mis. VPN/IP allowlist).

## REST API (untuk aplikasi mobile)

Basis URL `/api/v1`, autentikasi `Authorization: Bearer <token>`. Endpoint yang memindahkan uang wajib header `Idempotency-Key` (8–100 karakter unik per transaksi).

| Metode | Endpoint | Keterangan |
|---|---|---|
| POST | `/login` | `email`, `password`, `device_name`, `otp` (jika 2FA aktif) → `token` |
| POST | `/logout` | Cabut token |
| GET | `/me` | Profil & saldo |
| GET | `/transactions` | Riwayat + ringkasan (`from`, `to`, `type`, `direction`) |
| POST | `/transfers` | `to` (no. dompet), `amount`, `pin`, `note` |
| GET/POST | `/topups` | Riwayat / buat isi saldo (`method`: manual\|xendit) |
| GET | `/qr/static` | Payload QR statis milik pengguna |
| POST | `/qr/dynamic` | `amount`, `description` → payload QR dinamis |
| POST | `/qr/resolve` | `payload` → info penerima & nominal |
| POST | `/qr/pay` | `payload`, `pin`, `amount` (untuk QR statis) |

## Tes

```bash
php artisan test
```

21 tes fitur mencakup transfer, saldo kurang, idempotensi, penguncian PIN, batas harian, top up manual & webhook Xendit (termasuk webhook palsu), QR (sekali pakai, kedaluwarsa, dimanipulasi), 2FA, penguncian login, otorisasi admin, laporan CSV, dan API.

## Struktur penting

```
app/Services/WalletService.php   # satu-satunya pintu perubahan saldo (ledger)
app/Services/TopupService.php    # isi saldo manual & Xendit
app/Services/QrService.php       # pembuatan & verifikasi QR
app/Services/PinService.php      # PIN transaksi
app/Services/AuthService.php     # login + penguncian akun
app/Http/Controllers/Api/        # REST API
app/Http/Controllers/Admin/      # panel admin
```
