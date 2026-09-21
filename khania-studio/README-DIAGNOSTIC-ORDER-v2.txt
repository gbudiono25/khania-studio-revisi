KHANIA STUDIO — DIAGNOSTIC ORDER v2

Diagnostic v1 berhenti pada bagian RLS karena kolom `forcerowsecurity`
tidak tersedia melalui view `pg_tables` pada database ini.

v2 sudah memperbaiki pemeriksaan RLS dengan membaca `pg_class`, dan
menambahkan pemeriksaan hak EXECUTE RPC.

LANGKAH:
1. Supabase > SQL Editor > New query.
2. Hapus query diagnostic v1 sebelumnya.
3. Salin seluruh isi `khania-studio-supabase-diagnostic-order-v2.sql`.
4. Klik Run.
5. Jika hasil terlalu panjang, kirim screenshot bertahap dari hasil A-L.
6. Jangan mengubah query.
7. Jangan kirim service_role key, database password, atau secret.

TUJUAN:
- memastikan nama kolom aktual packages/clients/orders/vouchers;
- memastikan create_public_order benar-benar ada;
- membaca definisi RPC aktual;
- memeriksa trigger project code;
- memeriksa RLS/policies;
- memastikan apakah package/voucher memakai active atau is_active;
- memastikan RPC punya EXECUTE untuk anon/authenticated.

Setelah hasil v2 diterima, baru kita susun RPC v2 yang sesuai database aktual.
