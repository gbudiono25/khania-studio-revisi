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
A. Pak Gembong meninjau schema.sql. [SELESAI]
B. Setelah struktur disetujui, kita hubungkan website dengan Supabase. [SELESAI — Stage 2]
C. Baru dibuat Login/Register/Forgot Password.
D. Setelah itu Admin Panel dan Client Area.

=== STAGE 2 INTEGRATION (SELESAI) ===
Stage 2 sudah selesai. Berkas-berkas berikut telah ditambahkan/modifikasi:
- lib/env.php              : Loader .env untuk PHP
- lib/SupabaseClient.php   : Klien PHP REST API + Storage API + RPC untuk Supabase
- api/packages.php         : API ambil daftar paket dari Supabase
- api/validate-voucher.php : API validasi voucher via RPC (aman, tidak expose tabel)
- proses-pemesanan.php     : Simpan order/client/payment ke Supabase + upload bukti transfer ke Storage
- proses-formulir.php      : Simpan client brief ke Supabase + upload lampiran ke Storage
- js/pemesanan.js          : Fetch paket & validasi voucher via API (bukan JSON lokal)
- supabase-schema-migration-2.sql : INSERT policies + RPC functions (validate_voucher, get_package_by_code, get_active_packages)
- README-SUPABASE-STAGE2.md : Panduan lengkap setup & testing

Lihat README-SUPABASE-STAGE2.md untuk langkah-langkah penempatan schema, pembuatan bucket Storage, dan testing.

CATATAN KEAMANAN PENTING:
Versi pemesanan lama masih menerima beberapa nilai harga melalui hidden input HTML. Untuk produksi, harga final harus selalu dihitung ulang di server berdasarkan package_id/code yang tersimpan di database. Browser tidak boleh dipercaya untuk menentukan harga.
