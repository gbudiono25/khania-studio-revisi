KHANIA STUDIO — FINAL PATCH ADMIN VERIFY PAYMENT
===================================================

Patch ini mengganti function public.admin_verify_payment(uuid).

DASAR PATCH
-----------
Precheck dan debug browser sudah membuktikan:
- sesi admin valid;
- is_admin() = true;
- order dapat dibaca;
- payment dapat dibaca;
- UPDATE orders -> payment_verified berhasil;
- UPDATE payments -> verified berhasil.

Karena itu patch ini mempertahankan pemeriksaan admin dan menggunakan
alur UPDATE sederhana yang sudah terbukti berhasil.

RETURN FORMAT
-------------
success boolean
order_id uuid
payment_updated boolean
message text

LANGKAH
-------
1. Buka Supabase > SQL Editor.
2. Buat query baru.
3. Copy seluruh isi admin-verify-payment-final-patch.sql.
4. Klik Run.
5. Pastikan query terakhir menampilkan function signature:
   admin_verify_payment(uuid)
   dan return type TABLE(...).
6. Setelah berhasil, kembali ke Admin Panel.
7. Refresh halaman dengan Ctrl+F5.
8. Pilih order KS-260922-8170.
9. Klik Verifikasi.
10. Konfirmasi.

HASIL YANG DIHARAPKAN
---------------------
Order:
payment_received -> payment_verified

Payment:
pending -> verified

verified_at:
terisi timestamp

verified_by:
terisi UUID admin yang sedang login

Admin Panel:
Pembayaran Terverifikasi
dan tombol Kirim Brief menjadi aktif.

PENTING
-------
- Jangan menjalankan function debug lagi untuk test produksi.
- Jangan mengubah admin-pesanan-live.js pada tahap ini.
- Jika test berhasil, diagnostic HTML yang sudah dibuat sebelumnya
  sebaiknya dihapus dari hosting.
- Jika test gagal, kirim screenshot error dari Admin Panel; jangan
  mengubah patch ini lagi sebelum error diperiksa.
