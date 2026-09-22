KHANIA STUDIO — DIAGNOSTIC ORDER V4

Tujuan:
Menentukan secara presisi mengapa RPC create_public_order() gagal.
Diagnostic ini READ-ONLY dan tidak mengubah data/struktur.

Jalankan:
1. Supabase > SQL Editor > New query.
2. Hapus query sebelumnya.
3. Paste seluruh isi file SQL V4.
4. Klik Run.
5. Supabase akan menghasilkan 9 result set.

Yang paling penting:
- Result #1: full_definition create_public_order()
- Result #2: trigger orders
- Result #3: function yang berkaitan dengan order/client project code
- Result #6: struktur orders
- Result #8: constraint orders
- Result #9: tipe status

Jika hasil terlalu panjang:
- kirim screenshot Result #1 terlebih dahulu;
- lalu Result #2 dan #3;
- kemudian Result #6, #8, #9.

Jangan kirim credential apa pun:
service_role key, database password, SMTP password, atau secret lainnya.
