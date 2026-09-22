KHANIA STUDIO — ORDER RPC DIAGNOSTIC PATCH

Tujuan
------
Patch ini TIDAK mengubah database. Patch hanya membuat PHP mempertahankan:
- HTTP status dari Supabase RPC
- response body/error dari Supabase/PostgREST
- cURL error jika ada

Kemudian proses-pemesanan.php menulis diagnostic ke server error log dengan prefix:
KHANIA_ORDER_RPC_DIAGNOSTIC

Yang TIDAK dicatat:
- API key
- service_role key
- password
- request payload pelanggan

File patch
----------
1. lib/SupabaseClient.php
2. proses-pemesanan.php

Cara pasang
-----------
Upload/replace HANYA dua file tersebut di public_html sesuai struktur folder.
Tidak perlu mengubah file lain.
Jangan mengganti .env atau config-pemesanan.php.

Cara test
---------
1. Setelah upload, lakukan satu test pemesanan website seperti biasa.
2. Jika gagal, halaman akan tetap menampilkan pesan umum.
3. Buka error log PHP/server hosting.
4. Cari baris yang mengandung:
   KHANIA_ORDER_RPC_DIAGNOSTIC
5. Kirim screenshot atau copy baris error tersebut kepada ChatGPT.

Catatan
-------
Patch ini bersifat diagnostik sementara. Setelah penyebab ditemukan, patch
dapat dikembalikan ke versi normal.

Penting:
- Jangan mengirim credential apa pun.
- Jangan mengubah SQL/database pada tahap ini.
