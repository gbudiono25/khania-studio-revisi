KHANIA STUDIO — PATCH DEBUG KIRIM FORM BRIEF
==============================================

Tujuan:
Menampilkan response mentah dari api/admin-kirim-brief.php ketika terjadi
HTTP error, agar penyebab HTTP 500 dapat diketahui tanpa menggunakan
Chrome DevTools Network.

File:
- admin-pesanan-live.js

Upload file ke:
- /public_html/js/admin-pesanan-live.js

Langkah:
1. Backup file admin-pesanan-live.js yang sekarang di hosting.
2. Upload file patch ke /public_html/js/admin-pesanan-live.js dan replace.
3. Buka Admin Area.
4. Lakukan Ctrl+F5.
5. Buka order KS-260922-8170.
6. Klik Kirim Form Brief -> Oke.
7. Jika masih gagal, kotak error di Admin Area sekarang akan menampilkan:
   - HTTP status
   - response asli dari server (atau bagian awalnya jika terlalu panjang)
8. Screenshot kotak error tersebut dan kirimkan ke ChatGPT.

Catatan keamanan:
- Patch ini tidak mengubah Supabase, SQL, pembayaran, atau database.
- Patch ini tidak menampilkan access token/JWT.
- Jangan menyalin credential/secret jika muncul dalam response.
- Setelah penyebab ditemukan, patch debug dapat dikembalikan ke versi normal.

PENTING:
Hapus diagnose-admin-brief.php dari /public_html setelah proses diagnosis selesai.
