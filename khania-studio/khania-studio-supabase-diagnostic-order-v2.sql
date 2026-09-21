-- KHANIA STUDIO — READ-ONLY SUPABASE ORDER DIAGNOSTIC v2
-- v2 memperbaiki query RLS dari diagnostic sebelumnya.
-- HANYA membaca metadata/function/policy. Tidak mengubah database.

-- A. STRUKTUR TABEL: PACKAGES
SELECT table_name, column_name, data_type, udt_name, is_nullable
FROM information_schema.columns
WHERE table_schema='public' AND table_name='packages'
ORDER BY ordinal_position;

-- B. STRUKTUR TABEL: CLIENTS
SELECT table_name, column_name, data_type, udt_name, is_nullable
FROM information_schema.columns
WHERE table_schema='public' AND table_name='clients'
ORDER BY ordinal_position;

-- C. STRUKTUR TABEL: ORDERS
SELECT table_name, column_name, data_type, udt_name, is_nullable
FROM information_schema.columns
WHERE table_schema='public' AND table_name='orders'
ORDER BY ordinal_position;

-- D. STRUKTUR TABEL: VOUCHERS
SELECT table_name, column_name, data_type, udt_name, is_nullable
FROM information_schema.columns
WHERE table_schema='public' AND table_name='vouchers'
ORDER BY ordinal_position;

-- E. CEK RPC create_public_order
SELECT
  n.nspname AS schema_name,
  p.proname AS function_name,
  pg_get_function_identity_arguments(p.oid) AS arguments,
  pg_get_function_result(p.oid) AS return_type,
  p.prosecdef AS security_definer
FROM pg_proc p
JOIN pg_namespace n ON n.oid=p.pronamespace
WHERE n.nspname='public' AND p.proname='create_public_order';

-- F. DEFINISI RPC create_public_order
SELECT pg_get_functiondef(p.oid) AS function_definition
FROM pg_proc p
JOIN pg_namespace n ON n.oid=p.pronamespace
WHERE n.nspname='public' AND p.proname='create_public_order';

-- G. TRIGGER PADA ORDERS
SELECT
  tg.tgname AS trigger_name,
  pg_get_triggerdef(tg.oid) AS trigger_definition
FROM pg_trigger tg
JOIN pg_class c ON c.oid=tg.tgrelid
JOIN pg_namespace n ON n.oid=c.relnamespace
WHERE n.nspname='public'
  AND c.relname='orders'
  AND NOT tg.tgisinternal
ORDER BY tg.tgname;

-- H. FUNCTION orders_ensure_client_project_code
SELECT
  n.nspname AS schema_name,
  p.proname AS function_name,
  pg_get_function_identity_arguments(p.oid) AS arguments,
  p.prosecdef AS security_definer
FROM pg_proc p
JOIN pg_namespace n ON n.oid=p.pronamespace
WHERE n.nspname='public'
  AND p.proname='orders_ensure_client_project_code';

-- I. RLS STATUS (menggunakan pg_class agar kompatibel)
SELECT
  n.nspname AS schemaname,
  c.relname AS tablename,
  c.relrowsecurity AS rowsecurity,
  c.relforcerowsecurity AS forcerowsecurity
FROM pg_class c
JOIN pg_namespace n ON n.oid=c.relnamespace
WHERE n.nspname='public'
  AND c.relkind='r'
  AND c.relname IN ('packages','clients','orders','vouchers','website_briefs','brief_scope_reviews')
ORDER BY c.relname;

-- J. POLICIES
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
WHERE schemaname='public'
  AND tablename IN ('packages','clients','orders','vouchers','website_briefs','brief_scope_reviews')
ORDER BY tablename, policyname;

-- K. KHUSUS active / is_active
SELECT table_name, column_name, data_type
FROM information_schema.columns
WHERE table_schema='public'
  AND table_name IN ('packages','vouchers')
  AND column_name IN ('active','is_active')
ORDER BY table_name, column_name;

-- L. CEK EXECUTE PRIVILEGE RPC
SELECT
  routine_schema,
  routine_name,
  grantee,
  privilege_type
FROM information_schema.routine_privileges
WHERE routine_schema='public'
  AND routine_name='create_public_order'
ORDER BY routine_name, grantee, privilege_type;
