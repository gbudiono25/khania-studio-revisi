KHANIA STUDIO — STAGE 2B-2
ADMIN VERIFIKASI PEMBAYARAN

Perubahan:
1. Tambahkan SQL RPC:
   sql/admin-verify-payment.sql
2. Ganti js/admin-pesanan-live.js dengan versi pada ZIP ini.

Fungsi:
- Tombol "Verifikasi" muncul di daftar pesanan.
- Tombol hanya aktif untuk status "Menunggu Verifikasi Pembayaran".
- Detail order memiliki tombol "Verifikasi Pembayaran".
- Admin diminta konfirmasi sebelum perubahan.
- RPC memeriksa public.is_admin().
- RPC mengubah orders.status menjadi payment_verified.
- Jika ada payment row, status diubah menjadi verified dan verified_at/verified_by
  diisi bila kolom tersedia.
- Setelah berhasil, Admin Area langsung menampilkan "Pembayaran Terverifikasi"
  dan tombol "Kirim Brief" menjadi aktif.

LANGKAH:
A. Supabase:
1. Buka SQL Editor project Khania Studio.
2. Jalankan sql/admin-verify-payment.sql.
3. Pastikan query selesai tanpa error.

B. Hosting:
1. Backup js/admin-pesanan-live.js lama.
2. Upload file JS dari ZIP ke:
   /js/admin-pesanan-live.js
3. Tidak perlu mengubah admin-pesanan.html.
4. Tidak perlu mengubah Order/Payment production files.

C. Test:
1. Login Admin.
2. Buka Admin -> Pesanan.
3. Pilih order yang berstatus "Menunggu Verifikasi Pembayaran".
4. Pastikan tombol "Verifikasi" aktif.
5. Klik Verifikasi.
6. Baca konfirmasi.
7. Pastikan status berubah menjadi "Pembayaran Terverifikasi".
8. Pastikan tombol "Kirim Brief" aktif.
9. Refresh halaman dan pastikan status tetap tersimpan di Supabase.

PENTING:
- Verifikasi hanya dapat dijalankan oleh authenticated admin.
- Jangan memasukkan service_role key/password ke frontend.
- Jangan mengubah RLS secara manual untuk mengakali error.
- Tahap ini belum mengirim email verifikasi kepada klien.
  Email pemberitahuan akan dibuat setelah fungsi verifikasi database
  terbukti stabil.
