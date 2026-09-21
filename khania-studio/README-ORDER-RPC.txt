KHANIA STUDIO — ORDER RPC PATCH
Stage: Public Order -> Supabase foundation

FILES
1. create_public_order.sql
2. proses-pemesanan.php
3. lib/SupabaseClient.php

TUJUAN
Memastikan pemesanan website yang dibuat dari halaman publik benar-benar tersimpan
ke Supabase melalui RPC terkontrol, tanpa membuka akses SELECT/INSERT langsung
ke tabel clients/orders untuk browser.

URUTAN IMPLEMENTASI
1. Buka Supabase Dashboard -> SQL Editor pada project Khania Studio.
2. Jalankan create_public_order.sql.
3. Pastikan query selesai tanpa error.
4. Upload proses-pemesanan.php ke public_html.
5. Upload lib/SupabaseClient.php ke public_html/lib/.
6. Jangan mengubah .env yang sudah berfungsi.
7. Jangan mengubah service_role key atau password email.

PERUBAHAN PENTING
- Order dibuat oleh fungsi public.create_public_order().
- Harga paket dihitung dari tabel packages di Supabase.
- Diskon voucher dihitung ulang di server/database.
- Nomor Order dibuat oleh database.
- Kode Klien/Project tetap dibuat oleh trigger Stage 2A.
- Jika Supabase sudah terkonfigurasi tetapi RPC gagal, sistem TIDAK lagi diam-diam
  menyimpan order ke data/orders.json. Sistem akan menampilkan error server.
- Fallback JSON hanya dipakai jika Supabase memang tidak terkonfigurasi.

KEAMANAN
- create_public_order memakai SECURITY DEFINER.
- Fungsi hanya mengembalikan data order yang baru dibuat.
- Tidak memberikan akses SELECT umum ke clients/orders.
- Jangan pernah menaruh service_role key di HTML/JS/browser.

TEST SETELAH UPLOAD
1. Buat 1 pemesanan baru dari website.
2. Pastikan halaman konfirmasi pemesanan tampil.
3. Buka Supabase -> clients: harus bertambah 1 client baru (atau update client lama
   bila email yang sama digunakan).
4. Buka Supabase -> orders: harus muncul 1 order baru dengan status pending_payment.
5. Pastikan client_code terisi, contoh 001-ST-09-2026 sesuai bulan/tahun order.
6. Pastikan nomor order berbentuk KS-YYMMDD-XXXX.
7. Pastikan email invoice tetap terkirim.

JANGAN HAPUS FILE DATA LEGACY TERLEBIH DAHULU.
Setelah alur Supabase terbukti stabil, fallback JSON dapat dinonaktifkan/dihapus
pada tahap cleanup berikutnya.
