KHANIA STUDIO — STAGE 2E-1A PATCH
Admin Client Brief Viewer — Polish & Status Sync

Tujuan:
1. Menampilkan Client Brief berdasarkan kelompok section V3 dari brief_data.sections.
2. Menampilkan label field yang lebih mudah dibaca pada ringkasan data.
3. Tidak lagi menampilkan semua key top-level brief_data sebagai accordion terpisah.
4. Menampilkan Produk/Jasa sebagai bagian khusus.
5. Menyinkronkan status Form Brief pada Admin Pesanan dengan status website_briefs.
6. Jika status SUBMITTED/UNDER_REVIEW/NEED_CLIENT_INFO/SCOPE_CONFIRMED/READY_FOR_PRODUCTION,
   tombol berubah menjadi Review Brief dan membuka admin-form-brief.html.

FILE YANG DIGANTI:
- admin-form-brief.html
- js/admin-form-brief.js
- admin-pesanan.html
- js/admin-pesanan-live.js

UPLOAD:
Salin file dengan struktur folder ZIP ke /public_html.
Jangan mengubah Supabase SQL, Client Brief V3, proses-formulir.php, atau workflow pembayaran.

TEST:
1. Login Admin.
2. Buka Pesanan.
3. Cari KS-260922-85CA.
4. Status Form Brief seharusnya menjadi "Brief Diterima".
5. Tombol seharusnya menjadi "Review Brief".
6. Klik Review Brief.
7. Pastikan halaman menampilkan Ringkasan Data Brief lalu section B/C/D/.../R.
8. Pastikan nama field terbaca manusia, bukan key database seperti business_name.
9. Pastikan tidak ada perubahan pada data order/payment.

Catatan:
Stage 2E-1A belum menambahkan penilaian scope. Included / Extra / Need Client Info / Not Required tetap untuk Stage 2E-2.
