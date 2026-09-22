KHANIA STUDIO — ADMIN AREA STAGE 2B-1
Live Supabase connection: Admin → Pesanan

Tujuan:
- Login admin memakai Supabase Auth.
- Hanya profile dengan role = admin yang boleh masuk.
- Halaman Admin → Pesanan membaca data aktual Supabase.
- Search/filter/detail tetap tersedia.
- Tombol Kirim Form Brief hanya aktif bila pembayaran terverifikasi.
- PENGIRIMAN EMAIL BELUM DIIMPLEMENTASIKAN pada tahap ini.

File:
1. admin-login.html
2. admin-pesanan.html
3. api/supabase-config.php
4. js/admin-auth.js
5. js/admin-pesanan-live.js
6. css/admin-stage2b1.css

PENTING:
- Jangan hapus .env.
- api/supabase-config.php hanya mengeluarkan SUPABASE_URL + SUPABASE_ANON_KEY.
- Tidak ada service_role/secret key di frontend.
- Supabase RLS tetap menjadi pengaman data.
- File ini mengandalkan schema foundation yang sudah memiliki:
  profiles.role (admin/client)
  public.is_admin()
  RLS orders/payments/website_briefs.

SETUP SEBELUM TEST:
1. Pastikan akun admin sudah ada di Supabase Authentication.
2. Pastikan profile akun tersebut memiliki role = admin.
3. Jika akun baru dibuat melalui Supabase Auth, trigger handle_new_user seharusnya membuat profile otomatis.
4. Jika role masih client, ubah profile tersebut menjadi admin melalui SQL Editor menggunakan email akun admin. Contoh pola:
   update public.profiles p
   set role = 'admin', updated_at = now()
   from auth.users u
   where p.id = u.id
     and lower(u.email) = lower('EMAIL_ADMIN_ANDA');
   Jangan menaruh password/secret di SQL.
5. Upload file sesuai struktur ZIP ke hosting.
6. Buka /admin-login.html.
7. Login dengan akun admin.
8. Setelah masuk, Admin → Pesanan harus menampilkan data nyata dari Supabase.

Jika muncul "Gagal membaca data pesanan":
- jangan ubah RLS secara sembarang;
- kirim screenshot pesan errornya;
- kita periksa policy/schema yang aktif.

Tahap berikutnya (2B-2):
- tombol Kirim Form Brief benar-benar bekerja;
- membuat/mengambil website_briefs berdasarkan order;
- secure token;
- email SMTP;
- sent_at/sent_to/sent_by;
- status Form Brief Dikirim;
- Kirim Ulang;
- audit log.

Prototype order + payment yang sudah berhasil tidak perlu diubah oleh tahap 2B-1.
