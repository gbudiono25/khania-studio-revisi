KHANIA STUDIO — SUPABASE STAGE 2A
Form Brief V3 Integrated — Database Architecture

FILE
khania-studio-supabase-stage2a.sql

FOKUS TAHAP INI
1. Kode Klien/Project:
   NNN-PP-MM-YYYY
   Contoh: 001-ST-10-2026

2. Relasi:
   Client -> Project Code -> Order -> Website Brief

3. Website Brief:
   - memakai tabel website_briefs yang sudah ada
   - tidak membuat tabel lama menjadi duplikat
   - data formulir disiapkan untuk JSONB brief_data
   - status baru:
     DRAFT
     SUBMITTED
     UNDER_REVIEW
     NEED_CLIENT_INFO
     SCOPE_CONFIRMED
     READY_FOR_PRODUCTION

4. Internal Scope Review:
   tabel brief_scope_reviews
   Keputusan internal:
   INCLUDED
   ADDITIONAL_COST
   NOT_INCLUDED
   NEED_CLARIFICATION

5. Keamanan:
   - client hanya dapat melihat/mengubah brief miliknya sesuai RLS
   - client TIDAK mendapat akses ke brief_scope_reviews
   - admin dapat mengelola keduanya
   - tidak ada service_role/secret key di file ini

PENTING
- Migration ini tidak mengubah alur order/payment yang sudah diuji.
- Jalankan di Supabase SQL Editor pada project Khania Studio yang benar.
- Sebaiknya lakukan backup/snapshot sebelum migration.
- Setelah migration berhasil, cek query pemeriksaan di bagian akhir SQL.
- Tahap ini BELUM mengubah HTML Form Brief.
- Tahap berikutnya baru mengintegrasikan Form Brief V3 dengan order/payment verified dan menambahkan Section R.
