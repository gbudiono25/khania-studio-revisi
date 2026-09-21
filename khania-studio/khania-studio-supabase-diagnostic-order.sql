-- KHANIA STUDIO — READ-ONLY SUPABASE DIAGNOSTIC
-- Tujuan: memeriksa struktur aktual database sebelum memperbaiki RPC create_public_order().
-- File ini HANYA membaca metadata/function/policy. Tidak mengubah data atau struktur.

-- 1. Kolom aktual tabel packages
SELECT
  table_name,
  column_name,
  data_type,
  udt_name,
  is_nullable
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'packages'
ORDER BY ordinal_position;

-- 2. Kolom aktual tabel clients
SELECT
  table_name,
  column_name,
  data_type,
  udt_name,
  is_nullable
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'clients'
ORDER BY ordinal_position;

-- 3. Kolom aktual tabel orders
SELECT
  table_name,
  column_name,
  data_type,
  udt_name,
  is_nullable
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'orders'
ORDER BY ordinal_position;

-- 4. Kolom aktual tabel vouchers
SELECT
  table_name,
  column_name,
  data_type,
  udt_name,
  is_nullable
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'vouchers'
ORDER BY ordinal_position;

-- 5. Cek apakah RPC create_public_order benar-benar ada
SELECT
  n.nspname AS schema_name,
  p.proname AS function_name,
  pg_get_function_identity_arguments(p.oid) AS arguments,
  pg_get_function_result(p.oid) AS return_type,
  p.prosecdef AS security_definer
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
  AND p.proname = 'create_public_order';

-- 6. Tampilkan definisi RPC jika ada
SELECT
  pg_get_functiondef(p.oid) AS function_definition
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
  AND p.proname = 'create_public_order';

-- 7. Cek trigger untuk client/project code pada orders
SELECT
  tg.tgname AS trigger_name,
  pg_get_triggerdef(tg.oid) AS trigger_definition
FROM pg_trigger tg
JOIN pg_class c ON c.oid = tg.tgrelid
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public'
  AND c.relname = 'orders'
  AND NOT tg.tgisinternal
ORDER BY tg.tgname;

-- 8. Cek apakah function orders_ensure_client_project_code ada
SELECT
  n.nspname AS schema_name,
  p.proname AS function_name,
  pg_get_function_identity_arguments(p.oid) AS arguments,
  p.prosecdef AS security_definer
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
  AND p.proname = 'orders_ensure_client_project_code';

-- 9. Cek RLS pada tabel terkait
SELECT
  schemaname,
  tablename,
  rowsecurity,
  forcerowsecurity
FROM pg_tables
WHERE schemaname = 'public'
  AND tablename IN ('packages','clients','orders','vouchers','website_briefs','brief_scope_reviews')
ORDER BY tablename;

-- 10. Cek policy tabel terkait
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
  AND tablename IN ('packages','clients','orders','vouchers','website_briefs','brief_scope_reviews')
ORDER BY tablename, policyname;

-- 11. Cek apakah kolom yang paling dicurigai memang bernama is_active/active
SELECT
  table_name,
  column_name
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name IN ('packages','vouchers')
  AND column_name IN ('active','is_active')
ORDER BY table_name, column_name;
