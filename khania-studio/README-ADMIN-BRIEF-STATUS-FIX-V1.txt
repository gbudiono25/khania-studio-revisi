KHANIA STUDIO — ADMIN BRIEF STATUS FIX V1

Tujuan:
Membuat Admin -> Pesanan membaca website_briefs secara aman baik saat
Supabase mengembalikan relasi sebagai array maupun object.

Perubahan:
1. Menambahkan helper briefRecord().
2. Jika brief_sent_at terisi, label menjadi "Terkirim".
3. Tombol detail berubah menjadi "Kirim Ulang Form Brief" jika sudah pernah terkirim.
4. Detail order menampilkan waktu "Form Brief Dikirim".
5. Fungsi kirim ulang memakai record brief yang sama untuk mendeteksi pengiriman sebelumnya.

Tidak mengubah:
- Supabase SQL/RPC
- database
- pembayaran
- admin-kirim-brief.php
- alur email

Upload:
 /public_html/js/admin-pesanan-live.js

Test:
1. Backup file lama.
2. Upload/replace file.
3. Ctrl+F5.
4. Buka Admin -> Pesanan.
5. Cari KS-260922-8170.
6. Harapannya kolom Form Brief berubah dari "Belum Dikirim" menjadi "Terkirim".
7. Detail order menampilkan waktu Form Brief Dikirim.
8. Tombol berubah menjadi "Kirim Ulang Form Brief".

Catatan:
Jika setelah Ctrl+F5 tetap "Belum Dikirim", jangan klik Kirim Form Brief lagi.
Kirim screenshot hasilnya; kita akan cek data relasi Supabase sebelum mengirim ulang.
