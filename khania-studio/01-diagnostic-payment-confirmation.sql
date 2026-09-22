-- KHANIA STUDIO — DIAGNOSTIC PAYMENT CONFIRMATION V1
-- READ-ONLY: tidak INSERT / UPDATE / DELETE.
-- Jalankan di Supabase SQL Editor pada project PRODUCTION.

-- A. Status order yang sedang diuji
SELECT
  order_number,
  status::text AS status,
  total_amount,
  client_id,
  package_id
FROM public.orders
WHERE order_number = 'KS-260922-85CA';

-- B. Struktur tabel payments
SELECT
  ordinal_position,
  column_name,
  data_type,
  udt_name,
  is_nullable,
  column_default
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'payments'
ORDER BY ordinal_position;

-- C. Status enum payments (jika payments.status memakai enum)
SELECT
  t.typname AS type_name,
  e.enumlabel AS enum_value
FROM pg_type t
JOIN pg_enum e ON e.enumtypid = t.oid
WHERE t.typname = 'payment_status'
ORDER BY e.enumsortorder;

-- D. Status enum orders
SELECT
  t.typname AS type_name,
  e.enumlabel AS enum_value
FROM pg_type t
JOIN pg_enum e ON e.enumtypid = t.oid
WHERE t.typname = 'order_status'
ORDER BY e.enumsortorder;

-- E. Function payment RPC yang harus tersedia
SELECT
  n.nspname AS schema_name,
  p.proname AS function_name,
  pg_get_function_identity_arguments(p.oid) AS arguments,
  p.prosecdef AS security_definer,
  pg_get_functiondef(p.oid) AS function_definition
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
  AND p.proname IN ('get_order_for_payment','submit_payment_confirmation')
ORDER BY p.proname;

-- F. Hak EXECUTE RPC payment
SELECT
  routine_name,
  grantee,
  privilege_type
FROM information_schema.routine_privileges
WHERE routine_schema = 'public'
  AND routine_name IN ('get_order_for_payment','submit_payment_confirmation')
ORDER BY routine_name, grantee;

-- G. RLS pada orders/payments
SELECT
  schemaname,
  tablename,
  rowsecurity,
  forcerowsecurity
FROM pg_tables
WHERE schemaname = 'public'
  AND tablename IN ('orders','payments')
ORDER BY tablename;

-- H. Policy pada orders/payments
SELECT
  schemaname,
  tablename,
  policyname,
  roles,
  cmd
FROM pg_policies
WHERE schemaname = 'public'
  AND tablename IN ('orders','payments')
ORDER BY tablename, policyname;
