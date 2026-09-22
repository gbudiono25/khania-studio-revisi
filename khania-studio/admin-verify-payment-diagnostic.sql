-- KHANIA STUDIO
-- Diagnostic: Admin Verify Payment / Failed to fetch
-- READ-ONLY. Tidak melakukan INSERT, UPDATE, DELETE, ALTER, DROP, atau CREATE.

-- 1. Cek tipe kolom payments.status
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

-- 2. Cek isi enum payment_status
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

-- 3. Cek definisi function admin_verify_payment yang aktif
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

-- 4. Cek EXECUTE privilege function
SELECT
  routine_schema,
  routine_name,
  privilege_type,
  grantee
FROM information_schema.routine_privileges
WHERE routine_schema = 'public'
  AND routine_name = 'admin_verify_payment'
ORDER BY grantee, privilege_type;

-- 5. Cek kolom payments yang dipakai oleh proses verifikasi
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

-- 6. Cek kolom orders yang kemungkinan disentuh fungsi verifikasi
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
    'id','status','updated_at','verified_at','verified_by'
  )
ORDER BY ordinal_position;

-- 7. Cek RLS dan policy pada orders/payments
SELECT
  schemaname,
  tablename,
  rowsecurity,
  forcerowsecurity
FROM pg_tables
WHERE schemaname = 'public'
  AND tablename IN ('orders','payments')
ORDER BY tablename;

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

-- 8. Cek helper function is_admin yang dipanggil oleh admin_verify_payment
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

-- 9. Cek trigger orders yang mungkin mengubah status setelah verifikasi
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

-- 10. Cek status/order/payment yang relevan (READ-ONLY)
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
