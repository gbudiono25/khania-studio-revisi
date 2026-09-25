KHANIA STUDIO — STAGE 2D-2 PATCH
Client Brief V3 Integrated

File yang diganti di hosting:
  proses-formulir.php

Perubahan:
1. Memperbaiki filter query Order agar sesuai dengan SupabaseClient.php:
   ['order_number' => $orderId]
2. HTTP status pada halaman error sekarang benar-benar dikirim oleh PHP.
3. Credential SMTP TIDAK lagi disimpan di source PHP.
   proses-formulir.php sekarang membaca:
     SMTP_HOST
     SMTP_PORT
     SMTP_USERNAME
     SMTP_PASSWORD
   dari .env melalui loader yang sudah digunakan SupabaseClient.
4. Tidak mengubah alur Order/Payment.
5. Tidak membuat Order baru saat Client Brief dikirim.
6. Tetap menggunakan token -> verified order -> existing website_briefs -> SUBMITTED.

PENTING — .env di hosting:
Pastikan .env memiliki empat variable berikut dengan nilai SMTP yang benar:

SMTP_HOST=...
SMTP_PORT=587
SMTP_USERNAME=...
SMTP_PASSWORD=...

Jangan menaruh nilai password di file PHP atau di file yang akan di-upload ke publik.
Jangan mengirim password SMTP ke ChatGPT.

Setelah upload:
- Upload HANYA proses-formulir.php dari patch ini ke /public_html/
- Jangan mengganti HTML Form Brief V3.
- Jangan mengganti SupabaseClient.php.
- Jangan mengubah SQL pada tahap ini.

Pengujian berikutnya:
1. Admin -> Pesanan
2. Pilih order yang statusnya Pembayaran Terverifikasi.
3. Klik Kirim Form Brief.
4. Pastikan email Form Brief diterima.
5. Buka link Form Brief dari email.
6. Pastikan DATA ORDER dan DATA PEMESAN otomatis tampil.
7. Isi form sampai bagian Q/R.
8. Kirim Client Brief.
9. Pastikan status website_briefs menjadi SUBMITTED.
10. Pastikan tidak ada Order baru yang tercipta.

Catatan keamanan:
Password SMTP lama yang pernah tersimpan di source sebaiknya di-rotate setelah migrasi ke .env selesai.
