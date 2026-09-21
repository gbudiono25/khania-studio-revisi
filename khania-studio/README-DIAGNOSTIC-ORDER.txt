KHANIA STUDIO — DIAGNOSTIC RPC ORDER

Tujuan
------
File SQL ini hanya membaca metadata Supabase untuk mencari penyebab order
gagal tersimpan. Tidak ada INSERT, UPDATE, DELETE, ALTER, DROP, atau perubahan
data/struktur.

Langkah
-------
1. Buka Supabase Dashboard project Khania Studio.
2. Masuk ke SQL Editor.
3. Buat query baru.
4. Salin seluruh isi file:
   khania-studio-supabase-diagnostic-order.sql
5. Jalankan Run.
6. Simpan/screenshot seluruh hasil query.

Yang perlu diperiksa
--------------------
- Struktur aktual tabel packages, clients, orders, vouchers.
- Apakah function create_public_order() benar-benar ada.
- Definisi function create_public_order().
- Trigger orders pada tabel orders.
- Apakah orders_ensure_client_project_code() ada.
- RLS dan policy tabel terkait.
- Apakah kolom status paket/voucher menggunakan active atau is_active.

Penting
-------
Jangan kirim service_role key, database password, atau secret lain.
Hasil query ini tidak meminta atau menampilkan credential.

Setelah selesai, kirim screenshot hasil SQL Editor kepada ChatGPT.
Dari hasil tersebut kita akan membuat RPC v2 yang mengikuti struktur database
AKTUAL, bukan berdasarkan asumsi.
