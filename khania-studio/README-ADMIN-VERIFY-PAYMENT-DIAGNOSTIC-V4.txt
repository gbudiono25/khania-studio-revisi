KHANIA STUDIO — DIAGNOSTIC VERIFIKASI PEMBAYARAN V4
=======================================================

Tujuan:
Memastikan nilai enum public.order_status yang digunakan oleh
admin_verify_payment().

Diagnostic ini READ-ONLY.
Tidak melakukan perubahan data atau struktur database.

LANGKAH:
1. Buka Supabase > SQL Editor.
2. Buat query baru.
3. Copy seluruh isi admin-verify-payment-diagnostic-v4.sql.
4. Klik Run.
5. Kirim screenshot hasilnya atau Export > CSV.

Yang kita cari:
- Apakah payment_verified benar-benar ada dalam enum order_status.
- Status order KS-260922-8170 dan KS-260922-85CA saat ini.

Jangan klik Verifikasi di website sebelum hasil diagnostic diperiksa.
Jangan kirim service_role key, password database, atau credential.
