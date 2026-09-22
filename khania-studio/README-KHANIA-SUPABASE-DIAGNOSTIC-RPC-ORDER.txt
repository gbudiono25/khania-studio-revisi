KHANIA STUDIO — DIAGNOSTIC RPC ORDER

Tujuan
------
Memeriksa struktur database Supabase yang sedang digunakan oleh proses pemesanan
website sebelum kita memperbaiki function create_public_order().

File
----
1. khania-studio-supabase-diagnostic-rpc-order.sql

Cara menjalankan
----------------
1. Buka Supabase project Khania Studio.
2. Buka SQL Editor.
3. Buat query baru.
4. Copy seluruh isi file SQL ini ke SQL Editor.
5. Jalankan sekali.
6. Jangan mengubah isi query.

Keamanan
--------
- Script ini READ-ONLY.
- Tidak ada INSERT, UPDATE, DELETE, ALTER, DROP, atau CREATE.
- Jangan memasukkan service_role key, password database, atau secret lainnya.

Yang perlu dikirim kembali
--------------------------
Setelah selesai, kirim screenshot hasil query atau salin hasilnya ke chat.
Kita terutama perlu melihat:
- kolom tabel packages, clients, orders, vouchers
- definisi create_public_order()
- trigger pada orders
- function helper yang tersedia
- status RLS
- policies
- hak EXECUTE untuk anon/authenticated

Catatan
-------
Diagnostic ini sengaja dibuat sebelum kita membuat RPC v2. Tujuannya agar revisi
berdasarkan schema Supabase yang benar-benar sedang aktif, bukan berdasarkan asumsi
dari ZIP/versi schema sebelumnya.
