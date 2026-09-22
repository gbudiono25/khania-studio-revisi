KHANIA STUDIO — RPC ORDER FIX: gen_random_bytes

HASIL DIAGNOSTIC
----------------
Error log Supabase menunjukkan secara eksplisit:

HTTP 404
PostgreSQL code 42883
message:
function gen_random_bytes(integer) does not exist

Artinya create_public_order() gagal pada saat membuat Order ID karena function
tersebut menggunakan:

encode(gen_random_bytes(2), 'hex')

Tetapi environment database production saat ini tidak menyediakan
gen_random_bytes(integer).

PERBAIKAN
---------
Patch ini mempertahankan seluruh create_public_order() aktual yang diperoleh
dari diagnostic production. Hanya SATU ekspresi yang diubah:

DARI:
upper(substr(encode(gen_random_bytes(2), 'hex'), 1, 4))

MENJADI:
upper(substr(md5(v_email || clock_timestamp()::text || random()::text), 1, 4))

Keuntungan:
- tidak membutuhkan gen_random_bytes()
- tidak membutuhkan perubahan extension database
- tetap menghasilkan 4 karakter hex
- loop unique_violation yang sudah ada tetap menangani kemungkinan collision

PENTING
-------
SQL ini mengubah function create_public_order() di Supabase.
Sebelum menjalankan, pastikan Anda sedang berada di project:
Khania Studio / web-khania-studio / PRODUCTION.

LANGKAH
-------
1. Buka Supabase SQL Editor.
2. New query.
3. Paste isi file 01-fix-create-public-order.sql.
4. Run.
5. Jalankan 02-verify-create-public-order.sql.
6. Jika hasil menunjukkan:
   SUDAH MENGGUNAKAN GENERATOR TANPA gen_random_bytes
   maka lakukan SATU test pemesanan website.

JANGAN mengubah tabel, trigger, sequence, atau function lain.

CATATAN
-------
Patch ini dibuat berdasarkan definisi create_public_order() production yang
dikirim dalam CSV diagnostic dan error log production. Tidak ada credential
yang disertakan.
