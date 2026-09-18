========================================================================
KHANIA STUDIO — RUMAHWEB HOSTING DEPLOYMENT & SMTP CONFIGURATION GUIDE V3
========================================================================

PETUNJUK DEPLOYMENT KE HOSTING RUMAHWEB:

1. FILE & FOLDER YANG HARUS DIUNGGAH (PUBLIC_HTML / DOMAIN ROOT):
   - formulir-permintaan-website-khania-studio-v3.html
   - formulir-permintaan-website-khania-studio-v2.html (Redirect ke V3)
   - proses-formulir.php
   - konfirmasi-formulir.html
   - /phpmailer/
       └── /src/
           ├── Exception.php
           ├── PHPMailer.php
           └── SMTP.php

2. KONFIGURASI PASSWORD SMTP (PENTING!):
   Buka file `proses-formulir.php` pada cPanel File Manager (atau sebelum upload) dan ubah baris 19:
   
   DARI:
   const SMTP_PASSWORD = 'SMTP_PASSWORD_HERE';
   
   MENJADI:
   const SMTP_PASSWORD = 'password_email_cpanel_anda';
   
   Catatan:
   - Gunakan password dari akun email cPanel `admin@khania-studio.com`.
   - JANGAN pernah membagikan atau meng-commit password ini ke repositori publik.

3. PENGATURAN EMAIL & SMTP SERVER RUMAHWEB:
   - Host: khania-studio.com (atau mail.khania-studio.com / IP server Rumahweb)
   - Port: 587 (TLS / STARTTLS)
   - Username: admin@khania-studio.com
   - Primary To: admin@khania-studio.com
   - Internal CC: gbudiono.25@gmail.com (Server-side ONLY - Aman & Tersembunyi)

4. PENGUJIAN:
   - Akses formulir: https://khania-studio.com/formulir-permintaan-website-khania-studio-v3.html
   - Isi formulir dan kirim.
   - Setelah sukses, sistem akan mengarahkan ke `konfirmasi-formulir.html?ref=YYYYMMDD-HHMMSS-RAND`.
   - Email akan masuk ke Inbox `admin@khania-studio.com` dan CC ke `gbudiono.25@gmail.com`.

========================================================================
PENAMBAHAN — SISTEM PEMESANAN WEBSITE
========================================================================
File baru:
- pemesanan.html
- proses-pemesanan.php
- konfirmasi-pemesanan.html
- css/pemesanan.css
- js/pemesanan.js
- data/vouchers.json
- data/orders.json
- data/order-proofs/
- config-pemesanan.php

ALUR:
1. Tombol "Pilih Paket" pada halaman harga membuka pemesanan.html?paket=starter|bronze|silver|gold.
2. Paket dan harga ditentukan otomatis oleh sistem.
3. Tanggal order dan batas pembayaran H+2 23:59 WIB dihitung otomatis.
4. Klien mengisi nama, bisnis, WhatsApp, email, domain, tanggal pembayaran, dan bukti transfer.
5. Data order disimpan dan bukti transfer disimpan di data/order-proofs/.
6. Email notifikasi dikirim ke ADMIN_EMAIL melalui SMTP.

KONFIGURASI:
- Isi SMTP_PASSWORD di config-pemesanan.php dengan password akun email server.
- Jangan membagikan file config tersebut ke publik.
- Folder data dan bukti transfer dilindungi agar tidak dapat diakses langsung dari browser.

CATATAN:
- Mesin voucher sudah disiapkan melalui data/vouchers.json.
- Menu admin untuk membuat/mengelola voucher merupakan tahap berikutnya agar tidak mengganggu versi visual dan alur order yang sedang diuji.
