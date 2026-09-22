KHANIA STUDIO — PAYMENT RPC DIAGNOSTIC V2

Tujuan:
Mengisolasi masalah Konfirmasi Pembayaran dalam SATU result set supaya hasil
Supabase mudah di-screenshot atau diekspor CSV.

READ-ONLY:
Tidak ada INSERT, UPDATE, DELETE, dan tidak memanggil submit_payment_confirmation.

Order yang diperiksa:
KS-260922-85CA

Cara:
1. Supabase > SQL Editor > project PRODUCTION.
2. Buat query baru.
3. Paste seluruh isi 01-payment-rpc-diagnostic-v2.sql.
4. Klik Run.
5. Hasil seharusnya hanya 5 baris.
6. Kirim screenshot Results kepada ChatGPT.

Yang dicek:
- order ditemukan + status + total + email
- get_order_for_payment: ada/tidak, signature, SECURITY DEFINER, hak EXECUTE anon
- submit_payment_confirmation: ada/tidak, signature, SECURITY DEFINER, hak EXECUTE anon
- jumlah overload masing-masing RPC

Catatan:
Email ditampilkan hanya untuk memastikan pasangan Order ID + email yang digunakan
form memang cocok. Jangan kirim API key, service_role, password database, atau
password email.
