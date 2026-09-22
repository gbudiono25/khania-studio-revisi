KHANIA STUDIO — DIAGNOSTIC VERIFIKASI PEMBAYARAN V2
=======================================================

Versi V2 memperbaiki kesalahan pada diagnostic sebelumnya.
Diagnostic sebelumnya berhenti karena pg_tables tidak memiliki
kolom "forcerowsecurity". Itu adalah kesalahan pada query diagnostic,
BUKAN bukti adanya masalah baru pada website.

Diagnostic V2 tetap READ-ONLY:
- tidak INSERT
- tidak UPDATE
- tidak DELETE
- tidak ALTER
- tidak DROP
- tidak CREATE

LANGKAH:
1. Buka Supabase > SQL Editor.
2. Buat query baru atau kosongkan query sebelumnya.
3. Copy seluruh isi admin-verify-payment-diagnostic-v2.sql.
4. Klik Run.
5. Tunggu sampai seluruh query selesai.
6. Export hasil sebagai CSV jika memungkinkan, atau kirim screenshot hasilnya.

PENTING:
- Jangan menjalankan fungsi verifikasi dari website lagi sebelum hasil
  diagnostic diperiksa.
- Jangan kirim service_role key, database password, atau credential.
