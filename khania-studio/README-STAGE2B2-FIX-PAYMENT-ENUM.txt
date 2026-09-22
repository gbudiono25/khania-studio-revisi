KHANIA STUDIO — FIX STAGE 2B-2
================================

Masalah yang terlihat saat Verifikasi Pembayaran:
Verifikasi gagal: column "status" is of type payment_status but expression is of type text

Penyebab:
Kolom public.payments.status menggunakan enum public.payment_status, sedangkan RPC sebelumnya mengirim string text "verified" tanpa cast.

Perbaikan:
RPC public.admin_verify_payment(uuid) diperbarui agar nilai status payment di-cast ke public.payment_status:

  $1::public.payment_status

Tidak ada perubahan pada HTML Admin Pesanan atau proses order/payment.

CARA MEMPERBAIKI
1. Buka Supabase → SQL Editor.
2. Jalankan seluruh isi file admin-verify-payment-fix.sql.
3. Pastikan query selesai tanpa error.
4. Kembali ke Admin → Pesanan.
5. Refresh halaman.
6. Klik Verifikasi pada order KS-260922-8170.
7. Klik Oke.

HASIL YANG DIHARAPKAN
- Tidak ada pesan "payment_status ... expression is of type text".
- Status order berubah menjadi Pembayaran Terverifikasi.
- Status payment berubah menjadi verified.
- verified_at dan verified_by terisi bila kolom tersedia.
- Angka Pembayaran Terverifikasi menjadi 1.
- Tombol Kirim Brief menjadi aktif.

CATATAN
File ini hanya mengganti fungsi RPC admin_verify_payment. Tidak perlu upload ulang admin-pesanan.html atau admin-pesanan-live.js untuk perbaikan error ini.
