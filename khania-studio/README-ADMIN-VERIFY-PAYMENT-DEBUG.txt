KHANIA STUDIO — DEBUG VERIFIKASI PEMBAYARAN
===============================================

Tujuan:
Menguji bagian UPDATE dari proses verifikasi menggunakan sesi admin,
tanpa menyimpan perubahan.

PENTING:
Function ini sengaja melakukan UPDATE di dalam nested exception block
lalu memaksa ROLLBACK. Jika UPDATE berhasil, hasilnya:
update_test_passed
Jika gagal, PostgreSQL akan mengembalikan SQLSTATE + pesan error asli.

LANGKAH:
1. Jalankan admin-verify-payment-debug.sql SEKALI di Supabase SQL Editor.
2. Setelah berhasil, upload halaman browser debug yang akan diberikan.
3. Login Admin Panel.
4. Buka halaman debug.
5. Kirim hasilnya.

JANGAN gunakan function debug ini untuk produksi. Setelah selesai,
hapus function dan file diagnostic.
