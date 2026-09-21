KHANIA STUDIO — ADMIN AREA STAGE 1
===================================

Scope:
- Admin login via Supabase Auth email/password.
- Role check: public.profiles.role must be 'admin'.
- Dashboard summary.
- Pesanan: read-only list of orders from Supabase.
- Filter status.
- No changes to public order/payment files.
- No service_role key is sent to browser.

Files:
admin/login.html
admin/index.html
admin/admin.css
admin/admin.js
api/admin/_bootstrap.php
api/admin/auth.php
api/admin/session.php
api/admin/logout.php
api/admin/orders.php

IMPORTANT — BEFORE UPLOAD
1. Pastikan Stage 2A SQL sudah diterapkan (user confirmed this).
2. Buat akun admin di Supabase Authentication > Users.
3. Setelah akun dibuat, ambil User ID akun tersebut.
4. Jalankan SQL di Supabase SQL Editor:
   update public.profiles
   set role = 'admin', full_name = 'Gembong Budiono'
   where id = 'd0f9a3e2-e362-422f-95f2-9c71d2ee4534'
*/   where id = 'USER_UUID_ADMIN';
*/d0f9a3e2-e362-422f-95f2-9c71d2ee4534

5. Jangan memasukkan service_role key atau password database ke file ini.
6. File PHP membaca SUPABASE_URL dan SUPABASE_ANON_KEY dari .env yang sudah ada di server.

URL setelah upload:
https://www.khania-studio.com/admin/login.html

CATATAN:
- Tahap ini read-only untuk Pesanan. Tombol Kirim Form Brief belum dibuat.
- Tahap berikutnya akan menambahkan Form Brief invitation/link generator.
- Admin Area Stage 1 tidak mengubah proses Order/Payment yang sudah diuji.
