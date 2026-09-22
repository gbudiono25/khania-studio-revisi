KHANIA STUDIO — DIAGNOSTIC PAYMENT CONFIRMATION V1

Tujuan:
Menentukan titik kegagalan Konfirmasi Pembayaran untuk Order
KS-260922-85CA tanpa mengubah data.

Query ini READ-ONLY. Tidak ada INSERT, UPDATE, DELETE.

Yang diperiksa:
A. status order KS-260922-85CA
B. struktur tabel payments
C. enum payment_status
D. enum order_status
E. function get_order_for_payment dan submit_payment_confirmation
F. hak EXECUTE kedua RPC
G. RLS orders/payments
H. policies orders/payments

Langkah:
1. Buka Supabase SQL Editor pada project Khania Studio / web-khania-studio / PRODUCTION.
2. Paste isi 01-diagnostic-payment-confirmation.sql.
3. Run.
4. Jika hasil terlalu banyak, export hasil sebagai CSV atau kirim screenshot bagian A, B, C, D, E, dan F.

Jangan menjalankan function submit_payment_confirmation secara manual.
