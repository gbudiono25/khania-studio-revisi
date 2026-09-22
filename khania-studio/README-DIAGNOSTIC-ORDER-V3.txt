KHANIA STUDIO — DIAGNOSTIC ORDER V3

Versi ini dibuat agar seluruh hasil penting muncul dalam SATU result set.
Query bersifat READ-ONLY dan tidak mengubah database.

Langkah:
1. Supabase > SQL Editor > New query.
2. Hapus query sebelumnya.
3. Paste seluruh isi khania-studio-supabase-diagnostic-order-v3.sql.
4. Klik Run.
5. Kirim screenshot hasilnya. Jika kolom RESULT terlalu panjang, boleh kirim
   beberapa screenshot dengan scroll vertikal.

Informasi yang dicari:
- kolom aktual packages/clients/orders/vouchers
- definisi create_public_order()
- trigger orders
- RLS dan policies
- hak EXECUTE RPC
- active vs is_active
- function orders_ensure_client_project_code()

Jangan kirim service_role key, database password, SMTP password, atau secret.
