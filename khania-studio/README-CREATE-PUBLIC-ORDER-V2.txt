KHANIA STUDIO — CREATE_PUBLIC_ORDER V2

Penyebab error sudah teridentifikasi dari diagnostic produksi:
PostgreSQL 42883 — function gen_random_bytes(integer) does not exist.

Patch ini hanya mengganti generator suffix nomor order. Format tetap:
KS-YYMMDD-XXXX

Yang diubah: function public.create_public_order(...)
Yang tidak diubah: tabel, data, RLS, policies, Storage, HTML, proses pembayaran.

CARA MENJALANKAN
1. Buka Supabase -> SQL Editor.
2. New Query.
3. Copy seluruh isi khania-studio-create-public-order-v2.sql.
4. Klik Run.
5. Pastikan tidak ada error.
6. Hasil SELECT paling bawah harus menampilkan signature function.
7. Jangan test order sebelum SQL selesai sukses.

SETELAH SQL BERHASIL
Lakukan satu kali test dari halaman pemesanan website.
Untuk test pertama gunakan Starter dan tanpa voucher.
Klik Kirim Pemesanan Website satu kali.

Jangan masukkan API key, service_role key, password, atau credential apa pun ke SQL.
