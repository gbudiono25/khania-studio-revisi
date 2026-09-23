KHANIA STUDIO — DIAGNOSTIC is_admin() + PROFILE ADMIN
===========================================================

Tujuan:
Memastikan bagaimana public.is_admin() menentukan seorang admin dan
apakah akun admin Khania Studio memiliki role/profile yang sesuai.

Diagnostic ini READ-ONLY:
- tidak INSERT
- tidak UPDATE
- tidak DELETE
- tidak ALTER
- tidak DROP
- tidak CREATE

LANGKAH:
1. Buka Supabase > SQL Editor.
2. Buat query baru.
3. Copy seluruh isi admin-is-admin-diagnostic.sql.
4. Klik Run.
5. Export hasil sebagai CSV jika memungkinkan.
6. Kirim CSV tersebut kepada ChatGPT.

PENTING:
- Query hanya mengambil metadata akun: id, email, timestamp login,
  dan role metadata. Tidak mengambil password/token.
- Jangan kirim service_role key atau database password.
- Hasil "is_admin_in_sql_editor" dapat FALSE walaupun akun Admin Panel
  benar-benar admin, karena SQL Editor tidak menggunakan JWT login
  browser. Yang paling penting adalah definisi function dan data profile/role.

Setelah hasil diperiksa, baru kita tentukan patch berikutnya.
Jangan mengubah function admin_verify_payment() dulu.
