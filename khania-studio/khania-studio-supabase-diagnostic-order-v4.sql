-- KHANIA STUDIO — SUPABASE ORDER DIAGNOSTIC V4
-- READ-ONLY. Tidak mengubah database.
-- Fokus: definisi RPC, trigger, function terkait, dan kolom yang dipakai.

-- ============================================================
-- 1. DEFINISI LENGKAP create_public_order()
-- ============================================================
SELECT
  n.nspname AS schema_name,
  p.proname AS function_name,
  pg_get_function_identity_arguments(p.oid) AS arguments,
  pg_get_function_result(p.oid) AS return_type,
  p.prosecdef AS security_definer,
  pg_get_functiondef(p.oid) AS full_definition
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
  AND p.proname = 'create_public_order';

-- ============================================================
-- 2. DEFINISI LENGKAP SEMUA TRIGGER USER PADA orders
-- ============================================================
SELECT
  tg.tgname AS trigger_name,
  pg_get_triggerdef(tg.oid, true) AS trigger_definition
FROM pg_trigger tg
JOIN pg_class c ON c.oid = tg.tgrelid
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public'
  AND c.relname = 'orders'
  AND NOT tg.tgisinternal
ORDER BY tg.tgname;

-- ============================================================
-- 3. CARI FUNCTION YANG NAMA-NYA BERKAITAN DENGAN
--    client_project_code / orders
-- ============================================================
SELECT
  n.nspname AS schema_name,
  p.proname AS function_name,
  pg_get_function_identity_arguments(p.oid) AS arguments,
  p.prosecdef AS security_definer,
  pg_get_functiondef(p.oid) AS full_definition
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
  AND (
    p.proname ILIKE '%client%project%'
    OR p.proname ILIKE '%project%code%'
    OR p.proname ILIKE '%order%'
  )
ORDER BY p.proname;

-- ============================================================
-- 4. STRUKTUR LENGKAP packages
-- ============================================================
SELECT
  ordinal_position,
  column_name,
  data_type,
  udt_name,
  is_nullable,
  column_default
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'packages'
ORDER BY ordinal_position;

-- ============================================================
-- 5. STRUKTUR LENGKAP clients
-- ============================================================
SELECT
  ordinal_position,
  column_name,
  data_type,
  udt_name,
  is_nullable,
  column_default
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'clients'
ORDER BY ordinal_position;

-- ============================================================
-- 6. STRUKTUR LENGKAP orders
-- ============================================================
SELECT
  ordinal_position,
  column_name,
  data_type,
  udt_name,
  is_nullable,
  column_default
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'orders'
ORDER BY ordinal_position;

-- ============================================================
-- 7. STRUKTUR LENGKAP vouchers
-- ============================================================
SELECT
  ordinal_position,
  column_name,
  data_type,
  udt_name,
  is_nullable,
  column_default
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'vouchers'
ORDER BY ordinal_position;

-- ============================================================
-- 8. INDEXES / CONSTRAINTS PENTING PADA orders
-- ============================================================
SELECT
  con.conname AS constraint_name,
  con.contype AS constraint_type,
  pg_get_constraintdef(con.oid, true) AS definition
FROM pg_constraint con
JOIN pg_class c ON c.oid = con.conrelid
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public'
  AND c.relname = 'orders'
ORDER BY con.contype, con.conname;

-- ============================================================
-- 9. CEK TYPE ENUM YANG DIPAKAI orders.status / voucher
-- ============================================================
SELECT
  table_name,
  column_name,
  udt_schema,
  udt_name
FROM information_schema.columns
WHERE table_schema = 'public'
  AND (
    (table_name = 'orders' AND column_name = 'status')
    OR table_name = 'vouchers'
  )
ORDER BY table_name, column_name;
