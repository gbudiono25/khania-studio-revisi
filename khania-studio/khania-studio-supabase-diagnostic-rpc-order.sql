-- KHANIA STUDIO
-- Read-only diagnostic for public order RPC
-- Tujuan: memeriksa schema, function, trigger, dan RLS/policy yang
-- dibutuhkan oleh create_public_order().
--
-- PENTING:
-- 1. Script ini READ-ONLY. Tidak melakukan INSERT/UPDATE/DELETE/ALTER.
-- 2. Jalankan di Supabase SQL Editor pada project Khania Studio.
-- 3. Jangan memasukkan service_role key, password database, atau secret apa pun.

-- ============================================================
-- 1. Kolom tabel yang relevan
-- ============================================================
SELECT
  table_name,
  ordinal_position,
  column_name,
  data_type,
  udt_name,
  is_nullable,
  column_default
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name IN ('packages', 'clients', 'orders', 'vouchers')
ORDER BY table_name, ordinal_position;

-- ============================================================
-- 2. Definisi function create_public_order()
-- ============================================================
SELECT
  n.nspname AS schema_name,
  p.oid::regprocedure AS function_signature,
  pg_get_function_result(p.oid) AS return_type,
  pg_get_function_arguments(p.oid) AS arguments,
  pg_get_functiondef(p.oid) AS function_definition
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
  AND p.proname = 'create_public_order'
ORDER BY p.oid;

-- ============================================================
-- 3. Trigger yang berhubungan dengan orders / client project code
-- ============================================================
SELECT
  n.nspname AS schema_name,
  c.relname AS table_name,
  t.tgname AS trigger_name,
  pg_get_triggerdef(t.oid) AS trigger_definition,
  NOT t.tgenabled = 'D' AS enabled
FROM pg_trigger t
JOIN pg_class c ON c.oid = t.tgrelid
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public'
  AND c.relname IN ('orders', 'clients', 'website_briefs')
  AND NOT t.tgisinternal
ORDER BY c.relname, t.tgname;

-- ============================================================
-- 4. Function helper yang mungkin dipakai trigger/RPC
-- ============================================================
SELECT
  n.nspname AS schema_name,
  p.oid::regprocedure AS function_signature,
  pg_get_function_result(p.oid) AS return_type,
  pg_get_function_arguments(p.oid) AS arguments
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
  AND p.proname IN (
    'orders_ensure_client_project_code',
    'ensure_client_project_code',
    'package_project_code',
    'my_client_id',
    'is_admin'
  )
ORDER BY p.proname, p.oid;

-- ============================================================
-- 5. RLS status untuk tabel yang relevan
-- ============================================================
SELECT
  n.nspname AS schema_name,
  c.relname AS table_name,
  c.relrowsecurity AS rls_enabled,
  c.relforcerowsecurity AS rls_forced
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public'
  AND c.relname IN ('packages', 'clients', 'orders', 'vouchers')
ORDER BY c.relname;

-- ============================================================
-- 6. Policy pada tabel yang relevan
-- ============================================================
SELECT
  schemaname,
  tablename,
  policyname,
  permissive,
  roles,
  cmd,
  qual,
  with_check
FROM pg_policies
WHERE schemaname = 'public'
  AND tablename IN ('packages', 'clients', 'orders', 'vouchers')
ORDER BY tablename, policyname;

-- ============================================================
-- 7. Hak EXECUTE function create_public_order
-- ============================================================
SELECT
  n.nspname AS schema_name,
  p.oid::regprocedure AS function_signature,
  pg_get_userbyid(p.proowner) AS owner,
  has_function_privilege('anon', p.oid, 'EXECUTE') AS anon_can_execute,
  has_function_privilege('authenticated', p.oid, 'EXECUTE') AS authenticated_can_execute
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
  AND p.proname = 'create_public_order'
ORDER BY p.oid;

-- ============================================================
-- 8. Kolom package status yang paling penting untuk diverifikasi
-- ============================================================
SELECT
  table_name,
  column_name,
  data_type,
  is_nullable,
  column_default
FROM information_schema.columns
WHERE table_schema = 'public'
  AND (
    (table_name = 'packages' AND column_name IN ('active', 'is_active'))
    OR
    (table_name = 'vouchers' AND column_name IN ('active', 'is_active'))
  )
ORDER BY table_name, column_name;

-- ============================================================
-- 9. Signature function create_public_order secara ringkas
-- ============================================================
SELECT
  n.nspname AS schema_name,
  p.oid::regprocedure AS function_signature,
  pg_get_function_arguments(p.oid) AS arguments,
  pg_get_function_result(p.oid) AS returns
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
  AND p.proname = 'create_public_order';

-- SELESAI. Tidak ada perubahan data yang dilakukan.
