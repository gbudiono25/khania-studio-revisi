KHANIA STUDIO — ZIP19 SUPABASE FOUNDATION

Tujuan versi ini:
1. Menetapkan blueprint database Supabase sebelum integrasi frontend/backend.
2. Menyiapkan role ADMIN dan CLIENT.
3. Menyiapkan data paket, voucher, order, pembayaran, brief, pesan, pengumuman, dan audit log.
4. Menyiapkan Row Level Security (RLS) agar klien hanya melihat datanya sendiri.
5. Menyiapkan Supabase Auth untuk email/password dan Google sign-in.

PENTING:
- Belum ada koneksi ke project Supabase Anda pada ZIP ini.
- Jangan masukkan service_role key ke frontend atau kirim password database.
- Supabase URL + publishable/anon key baru diperlukan saat tahap integrasi.
- OTP WhatsApp belum diaktifkan. Tahap pertama login cukup email/password + Google + forgot password + email verification.
- Bukti transfer akan menggunakan Supabase Storage private bucket pada tahap integrasi.
- Voucher tidak boleh dibaca langsung dari tabel oleh browser publik. Validasi voucher sebaiknya dilakukan server-side/Edge Function agar kode voucher dan aturan penggunaannya tidak mudah dimanipulasi.

LANGKAH BERIKUTNYA:
A. Pak Gembong meninjau schema.sql.
B. Setelah struktur disetujui, kita hubungkan website dengan Supabase.
C. Baru dibuat Login/Register/Forgot Password.
D. Setelah itu Admin Panel dan Client Area.

CATATAN KEAMANAN PENTING:
Versi pemesanan lama masih menerima beberapa nilai harga melalui hidden input HTML. Untuk produksi, harga final harus selalu dihitung ulang di server berdasarkan package_id/code yang tersimpan di database. Browser tidak boleh dipercaya untuk menentukan harga.
