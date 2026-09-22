-- KHANIA STUDIO
-- Diagnostic Verifikasi Pembayaran V2
-- READ-ONLY: tidak mengubah data atau struktur database.

-- 1. Tipe kolom status pada payments dan orders
SELECT
  c.table_schema,
  c.table_name,
  c.column_name,
  c.data_type,
  c.udt_schema,
  c.udt_name,
  c.is_nullable
FROM information_schema.columns c
WHERE c.table_schema = 'public'
  AND c.table_name IN ('payments','orders')
  AND c.column_name = 'status'
ORDER BY c.table_name;

-- 2. Nilai enum payment_status
SELECT
  n.nspname AS enum_schema,
  t.typname AS enum_name,
  e.enumsortorder,
  e.enumlabel
FROM pg_type t
JOIN pg_enum e ON e.enumtypid = t.oid
JOIN pg_namespace n ON n.oid = t.typnamespace
WHERE n.nspname = 'public'
  AND t.typname = 'payment_status'
ORDER BY e.enumsortorder;

-- 3. Definisi admin_verify_payment() yang AKTIF
SELECT
  n.nspname AS schema_name,
  p.proname AS function_name,
  pg_get_function_identity_arguments(p.oid) AS function_signature,
  pg_get_function_result(p.oid) AS return_type,
  p.prosecdef AS security_definer,
  pg_get_userbyid(p.proowner) AS owner,
  pg_get_functiondef(p.oid) AS function_definition
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
  AND p.proname = 'admin_verify_payment';

-- 4. EXECUTE privilege
SELECT
  routine_schema,
  routine_name,
  privilege_type,
  grantee
FROM information_schema.routine_privileges
WHERE routine_schema = 'public'
  AND routine_name = 'admin_verify_payment'
ORDER BY grantee, privilege_type;

-- 5. Struktur lengkap tabel payments
SELECT
  ordinal_position,
  column_name,
  data_type,
  udt_schema,
  udt_name,
  is_nullable
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'payments'
ORDER BY ordinal_position;

-- 6. Kolom orders yang relevan dengan verifikasi
SELECT
  ordinal_position,
  column_name,
  data_type,
  udt_schema,
  udt_name,
  is_nullable
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'orders'
  AND column_name IN (
    'id','order_number','status','updated_at','verified_at','verified_by'
  )
ORDER BY ordinal_position;

-- 7. RLS pada orders/payments
SELECT
  schemaname,
  tablename,
  rowsecurity
FROM pg_tables
WHERE schemaname = 'public'
  AND tablename IN ('orders','payments')
ORDER BY tablename;

-- 8. Policy orders/payments
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
  AND tablename IN ('orders','payments')
ORDER BY tablename, policyname;

-- 9. Helper function is_admin()
SELECT
  n.nspname AS schema_name,
  p.proname AS function_name,
  pg_get_function_identity_arguments(p.oid) AS function_signature,
  pg_get_function_result(p.oid) AS return_type,
  p.prosecdef AS security_definer,
  pg_get_userbyid(p.proowner) AS owner
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
  AND p.proname = 'is_admin';

-- 10. Trigger pada orders/payments
SELECT
  event_object_schema,
  event_object_table,
  trigger_name,
  event_manipulation,
  action_timing,
  action_statement
FROM information_schema.triggers
WHERE event_object_schema = 'public'
  AND event_object_table IN ('orders','payments')
ORDER BY event_object_table, trigger_name;

-- 11. Kondisi order/payment saat ini
SELECT
  o.id,
  o.order_number,
  o.status AS order_status,
  p.id AS payment_id,
  p.status AS payment_status,
  p.payment_date,
  p.amount,
  p.verified_at,
  p.verified_by
FROM public.orders o
LEFT JOIN public.payments p ON p.order_id = o.id
WHERE o.order_number IN (
  'KS-260922-8170',
  'KS-260922-85CA'
)
ORDER BY o.order_number;
