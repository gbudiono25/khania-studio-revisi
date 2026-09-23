KHANIA STUDIO — FINAL PATCH V2 ADMIN VERIFY PAYMENT
======================================================

HASIL ERROR SEBELUMNYA
----------------------
ERROR 42P13:
cannot change return type of existing function

Penyebabnya bukan pada data order atau payment.

PostgreSQL menolak CREATE OR REPLACE FUNCTION karena function
admin_verify_payment(uuid) yang sudah ada mempunyai OUT/return
type berbeda dengan versi patch sebelumnya.

PATCH V2
--------
Patch ini melakukan:
1. DROP FUNCTION public.admin_verify_payment(uuid)
2. CREATE FUNCTION kembali dengan return type yang sesuai dengan
   JavaScript Admin Panel:
      success boolean
      order_id uuid
      payment_updated boolean
      message text

3. Memberikan EXECUTE kepada authenticated.

LANGKAH
-------
1. Buka Supabase > SQL Editor.
2. Buat query baru.
3. Copy seluruh isi admin-verify-payment-final-patch-v2.sql.
4. Klik Run.
5. Query CHECK paling bawah harus menampilkan:
   admin_verify_payment(uuid)
6. Setelah SQL berhasil, buka Admin Panel.
7. Ctrl + F5.
8. Pilih order KS-260922-8170.
9. Klik Verifikasi.
10. Konfirmasi.

HASIL YANG DIHARAPKAN
---------------------
orders.status:
payment_received -> payment_verified

payments.status:
pending -> verified

payments.verified_at:
terisi

payments.verified_by:
terisi UUID admin

Admin Panel:
Pembayaran Terverifikasi
dan tombol Kirim Brief menjadi aktif.

CATATAN KEAMANAN
----------------
DROP FUNCTION hanya menghapus function admin_verify_payment(uuid),
bukan data orders atau payments.

Jangan menghapus tabel apa pun.

Jika SQL CHECK berhasil tetapi tombol Verifikasi masih tidak muncul,
jangan ubah SQL lagi. Itu berarti kita perlu kembali memeriksa file
JavaScript/cache browser.

Jika tombol muncul tetapi proses verifikasi menghasilkan error,
kirim screenshot error tersebut sebelum melakukan perubahan lain.
