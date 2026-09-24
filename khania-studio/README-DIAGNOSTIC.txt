DIAGNOSTIC ADMIN → KIRIM FORM BRIEF

File: diagnose-admin-brief.php

Tujuan:
Menguji jalur yang sama dengan Admin Area menggunakan session/JWT admin untuk mencari penyebab HTTP 500 pada api/admin-kirim-brief.php.

PENTING:
- File ini TEMPORER.
- Jangan dibiarkan permanen di public_html setelah diagnosis selesai.
- Jangan bagikan URL diagnostic kepada orang lain.
- Diagnostic memanggil RPC admin_prepare_brief() dengan token diagnostic. Jika RPC berhasil, website_briefs dapat dibuat/diperbarui. Karena itu gunakan hanya untuk order test yang memang boleh diproses.

Penempatan:
/public_html/diagnose-admin-brief.php

Cara uji:
1. Upload file ke public_html.
2. Login terlebih dahulu di Admin Area pada browser yang sama.
3. Buka halaman Admin → Pesanan.
4. Pilih order yang sudah berstatus Pembayaran Terverifikasi.
5. Catat UUID order (bukan nomor KS-...). UUID dapat dilihat dari detail jika tersedia atau dari Supabase.
6. Buka URL:
   https://khania-studio.com/diagnose-admin-brief.php?order_id=UUID_ORDER

Hasil akan tampil sebagai JSON.

Setelah diagnosis selesai, HAPUS diagnose-admin-brief.php dari hosting.
