KHANIA STUDIO — STAGE 2C
Kirim Form Brief secara aman dari Admin → Pesanan

TUJUAN
- Admin hanya dapat mengirim Form Brief setelah pembayaran terverifikasi.
- Sistem membuat token akses acak 64 karakter hex dan menyimpan hanya SHA-256 hash di Supabase.
- Link berlaku 14 hari.
- Email dikirim server-side memakai konfigurasi SMTP yang SUDAH ADA di hosting.
- Form Brief V3 membaca konteks order/client/package melalui token; client tidak perlu mengetik ulang data order.
- Form submission memperbarui website_briefs yang sudah dibuat, bukan membuat order baru.

FILE PATCH
1. sql/admin-brief-token-stage2c.sql
2. api/admin-kirim-brief.php
3. api/brief-context.php
4. admin-pesanan-live.js
5. formulir-permintaan-website-khania-studio-v3.html
6. proses-formulir.php

URUTAN DEPLOY
A. Supabase SQL
1. Jalankan sql/admin-brief-token-stage2c.sql di SQL Editor.
2. Pastikan tiga function muncul: admin_prepare_brief, admin_mark_brief_sent, get_brief_context.

B. Hosting
Upload sesuai path:
- admin-pesanan-live.js → /public_html/js/admin-pesanan-live.js
- admin-kirim-brief.php → /public_html/api/admin-kirim-brief.php
- brief-context.php → /public_html/api/brief-context.php
- formulir-permintaan-website-khania-studio-v3.html → /public_html/...
- proses-formulir.php → /public_html/proses-formulir.php

CATATAN
- Jangan mengganti config-pemesanan.php atau .env.
- Endpoint admin menggunakan access token login admin; tidak ada service_role key di browser.
- SMTP password tetap hanya di server dan tidak dicantumkan di patch.
- Link brief tidak memakai order_id/client data di URL, hanya token acak.

TEST
1. Buka Admin → Pesanan.
2. Pilih KS-260922-8170 yang sudah Pembayaran Terverifikasi.
3. Klik Kirim Brief.
4. Pastikan email masuk ke email klien.
5. Buka link dari email.
6. Pastikan Data Order & Pemesan terisi otomatis.
7. Isi form dan submit.
8. Pastikan website_briefs berubah menjadi SUBMITTED dan TIDAK ada order baru.
9. Kembali Admin → Pesanan → Refresh. Status Form Brief harus Brief Diterima.

JANGAN TEST DENGAN ORDER YANG MENUNGGU PEMBAYARAN.
