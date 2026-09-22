KHANIA STUDIO — DIAGNOSTIC PAYMENT CONFIRMATION V1.1

V1.0 berhenti pada query RLS karena kolom forcerowsecurity tidak tersedia
pada view pg_tables di project ini. V1.1 memperbaiki query tersebut.

Query tetap READ-ONLY. Tidak ada INSERT, UPDATE, DELETE.

Langkah:
1. Di Supabase SQL Editor project PRODUCTION.
2. Hapus query V1.0 sebelumnya.
3. Paste 01-diagnostic-payment-confirmation-v1.1.sql.
4. Run.
5. Kirim screenshot hasil atau export CSV.

Order yang diperiksa: KS-260922-85CA.

Jangan menjalankan RPC submit_payment_confirmation secara manual.
