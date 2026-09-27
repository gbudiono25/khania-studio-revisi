KHANIA STUDIO — STAGE 2E-1
ADMIN REVIEW CLIENT BRIEF — VIEWER

Tujuan:
Menambahkan viewer Admin untuk membaca Client Brief V3 yang sudah tersimpan di Supabase.

File:
- admin-form-brief.html
- js/admin-form-brief.js
- css/admin-form-brief.css
- js/admin-pesanan-live.js (patch navigasi Review Brief)
- admin-pesanan.html (baseline copy)

Cara upload:
1. Backup js/admin-pesanan-live.js yang sedang aktif.
2. Upload admin-form-brief.html ke /public_html/
3. Upload js/admin-form-brief.js ke /public_html/js/
4. Upload css/admin-form-brief.css ke /public_html/css/
5. Upload js/admin-pesanan-live.js ke /public_html/js/ menggantikan versi aktif.
6. Tidak perlu mengganti admin-pesanan.html jika versi hosting saat ini sama dengan baseline ZIP ini.

Fungsi:
- Admin login tetap memakai Supabase Auth + role admin.
- Viewer mengambil order berdasarkan order_id pada URL.
- Viewer mengambil website_briefs terbaru untuk order tersebut.
- brief_data ditampilkan per section dengan accordion.
- Stage ini BELUM mengubah status brief dan BELUM membuat penilaian scope.
- Tidak ada biaya tambahan yang dihitung.

Tombol pada Pesanan:
- DRAFT/Form Brief Dikirim: tetap untuk Kirim/Kirim Ulang Brief.
- SUBMITTED atau status review berikutnya: berubah menjadi Review Brief dan membuka viewer.

Catatan keamanan:
- Viewer hanya dapat dibuka oleh user yang lolos Supabase Auth dan profile.role = admin.
- Jangan menaruh service_role key di file frontend.
