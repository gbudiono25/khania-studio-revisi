KHANIA STUDIO — create_public_order V4
===========================================

TUJUAN
------
Memperbaiki error PostgreSQL:

  ERROR 42702: column reference "client_code" is ambiguous

Penyebab
--------
Pada V3 terdapat:

  returning id, client_code
    into v_client_id, v_client_code;

Dalam PL/pgSQL, client_code juga merupakan output variable dari
RETURNS TABLE. Saat INSERT client baru dijalankan, PostgreSQL
menganggap client_code ambigu antara nama kolom dan variable.

PERBAIKAN V4
------------
Bagian tersebut diubah menjadi:

  returning id
    into v_client_id;

client_code tetap diambil sesudah order dibuat:

  select c.client_code
    into v_client_code
  from public.clients c
  where c.id = v_client_id;

Tidak ada perubahan pada logika V3 lainnya:
- client matching menggunakan email + nama + bisnis + WhatsApp
- client lama tidak di-update
- client baru dibuat bila identitas tidak cocok
- package tetap authoritative dari database
- voucher tetap divalidasi server-side
- H+2 23:59 WIB tetap
- order number tetap KS-YYMMDD-XXXX
- status awal tetap pending_payment
- client_code tetap diambil dari client setelah order dibuat

LANGKAH PEMASANGAN
-------------------
1. Buka Supabase > SQL Editor.
2. Buat query baru.
3. Copy seluruh isi:
   khania-studio-create-public-order-v4.sql
4. Jalankan sekali.
5. Pastikan tidak ada error.

PENTING
-------
Jangan melakukan real-order test terlebih dahulu.

SETELAH V4 TERPASANG
--------------------
Lakukan diagnostic transaction berikut:

BEGIN;

SELECT *
FROM public.create_public_order(
    'Test Client 04',
    'Test Bisnis Khania',
    '083871082222',
    'test123@gmail.com',
    'test-bisnis.my.id',
    'silver',
    NULL
);

ROLLBACK;

Tujuan:
- memastikan client baru dapat dibuat
- memastikan client_code menjadi 004-SV-09-2026
- memastikan package Silver
- memastikan total Rp1.500.000
- memastikan status pending_payment
- tanpa menyimpan data karena ROLLBACK

JIKA DIAGNOSTIC BERHASIL
------------------------
Kirim hasil CSV/screenshot kepada ChatGPT.

Jangan lakukan real order sebelum hasil diagnostic diperiksa.

KEAMANAN
--------
Script ini tidak meminta service_role key, password database,
atau secret apa pun.
