KHANIA STUDIO — CREATE PUBLIC ORDER V3

TUJUAN
------
Memperbaiki masalah ketika order baru menggunakan email yang sama dengan
client lama. Versi sebelumnya mencari client hanya berdasarkan email lalu
meng-update record client tersebut. Akibatnya order lama dapat ikut berubah
nama/bisnis.

ATURAN V3
---------
1. Email saja tidak lagi menjadi kunci identitas client.
2. Jika email + nama + nama bisnis + WhatsApp sama persis, client lama dipakai
   kembali TANPA mengubah record client.
3. Jika email sama tetapi identitas order berbeda, client baru dibuat.
4. Order baru tidak meng-update client lama.
5. Kontrak RPC create_public_order tetap sama.
6. Harga dan voucher tetap dihitung dari database.
7. Payment Verification tidak disentuh.

PENTING
-------
SQL ini hanya mengganti fungsi create_public_order(). Tidak melakukan UPDATE
atau DELETE terhadap data order/client yang sudah ada.

JANGAN memperbaiki data Shania/Nunik dengan SQL UPDATE terlebih dahulu.
Kita akan memulihkan data test setelah V3 terpasang dan berhasil diverifikasi.

LANGKAH
-------
1. Buka Supabase > SQL Editor.
2. Jalankan seluruh file SQL.
3. Pastikan query terakhir mengembalikan function:
   public.create_public_order(text,text,text,text,text,text,text)
4. Kirim hasil query terakhir/screenshot kepada ChatGPT.
5. JANGAN melakukan test order baru sebelum hasil SQL diperiksa.

CATATAN
-------
V3 mempertahankan package.active dan voucher.active sesuai schema produksi
yang digunakan oleh fungsi V2.
