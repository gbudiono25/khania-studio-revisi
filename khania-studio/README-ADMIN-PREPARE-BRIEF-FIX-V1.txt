KHANIA STUDIO — FIX admin_prepare_brief V1

HASIL DIAGNOSIS
---------------
Screenshot Admin Area menunjukkan error:

Pengiriman Form Brief gagal (HTTP 500).
column reference "order_id" is ambiguous

PENYEBAB
--------
Fungsi public.admin_prepare_brief() memiliki kolom output/return bernama
"order_id". Di dalam query public.website_briefs, referensi:

    WHERE order_id = p_order_id

menjadi ambigu antara kolom tabel dan variabel output PL/pgSQL.

PERBAIKAN
---------
Query tersebut diubah menjadi:

    FROM public.website_briefs AS wb
    WHERE wb.order_id = p_order_id

Tidak ada perubahan pada:
- orders
- payments
- status pembayaran
- harga
- client
- struktur tabel
- alur pembayaran

CARA MEMASANG
-------------
1. Buka Supabase SQL Editor.
2. Buka file:
   admin_prepare_brief_fix_v1.sql
3. Copy seluruh isi SQL.
4. Run / Execute.
5. Pastikan tidak ada error.

SETELAH SQL BERHASIL
--------------------
Jangan melakukan perubahan lain.

Lakukan test:
1. Buka Admin Area.
2. Menu Pesanan.
3. Order: KS-260922-8170 (Shania Rhiana Zafirah).
4. Pastikan status: Pembayaran Terverifikasi.
5. Klik Detail.
6. Klik Kirim Form Brief.
7. Klik Oke.

Jika berhasil, kemungkinan berikutnya:
- email Form Brief dikirim,
- website_briefs dibuat/diupdate,
- status Form Brief berubah.

Jika masih gagal, kirim screenshot pesan merah yang muncul.

CATATAN
-------
Patch ini hanya memperbaiki error yang sudah teridentifikasi dari server.
Tidak perlu mengubah admin-kirim-brief.php atau admin-pesanan-live.js lagi
sebelum hasil test SQL ini diketahui.
