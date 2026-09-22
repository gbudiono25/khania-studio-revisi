KHANIA STUDIO — DIAGNOSTIC VERIFIKASI PEMBAYARAN V3
=======================================================

V2 berhasil dijalankan, tetapi CSV yang dikirim hanya berisi result set
terakhir (kondisi order/payment). Karena itu kita belum memperoleh
definisi function admin_verify_payment() dan informasi struktur lainnya.

V3 sengaja menggabungkan semua informasi menjadi SATU result set
dengan dua kolom:
- section
- value

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
3. Copy seluruh admin-verify-payment-diagnostic-v3.sql.
4. Run.
5. Pastikan hasil menampilkan sekitar 11 section.
6. Klik Export > CSV pada hasil.
7. Kirim CSV tersebut ke ChatGPT.

Jangan klik Verifikasi di website dulu.
Jangan kirim service_role key, database password, atau credential.
