KHANIA STUDIO — DIAGNOSTIC VERIFIKASI PEMBAYARAN
====================================================

Tujuan:
Mencari penyebab error:
"Verifikasi gagal: TypeError: Failed to fetch"

Diagnostic ini READ-ONLY.

Tidak melakukan:
- INSERT
- UPDATE
- DELETE
- ALTER
- DROP
- CREATE
- perubahan status order/payment

LANGKAH:
1. Buka Supabase > SQL Editor.
2. Buka file: admin-verify-payment-diagnostic.sql
3. Copy seluruh SQL.
4. Jalankan Run.
5. Simpan/export hasil query sebagai CSV atau screenshot.
6. Kirim hasilnya kepada ChatGPT.

YANG AKAN DICEK:
- tipe kolom payments.status
- daftar nilai enum payment_status
- definisi function admin_verify_payment() yang benar-benar aktif
- security definer dan owner function
- EXECUTE privilege
- struktur tabel payments/orders
- RLS dan policy
- function is_admin()
- trigger orders/payments
- kondisi order KS-260922-8170 dan KS-260922-85CA

PENTING:
Jangan menjalankan SQL lain dari tahap perbaikan sebelum hasil diagnostic diperiksa.
Jangan kirim service_role key, password database, atau credential lain.
