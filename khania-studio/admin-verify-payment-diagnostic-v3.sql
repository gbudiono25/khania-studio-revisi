-- KHANIA STUDIO
-- Diagnostic Verifikasi Pembayaran V3
-- READ-ONLY. Hasil sengaja dibuat menjadi SATU result set agar mudah Export CSV.

WITH
fn AS (
  SELECT
    COALESCE(
      string_agg(pg_get_functiondef(p.oid), E'\n\n'),
      'FUNCTION_NOT_FOUND'
    ) AS value
  FROM pg_proc p
  JOIN pg_namespace n ON n.oid=p.pronamespace
  WHERE n.nspname='public' AND p.proname='admin_verify_payment'
),
fn_meta AS (
  SELECT
    COALESCE(
      string_agg(
        'signature=' || pg_get_function_identity_arguments(p.oid)
        || '; return=' || pg_get_function_result(p.oid)
        || '; security_definer=' || p.prosecdef::text
        || '; owner=' || pg_get_userbyid(p.proowner),
        E'\n'
      ),
      'FUNCTION_NOT_FOUND'
    ) AS value
  FROM pg_proc p
  JOIN pg_namespace n ON n.oid=p.pronamespace
  WHERE n.nspname='public' AND p.proname='admin_verify_payment'
),
priv AS (
  SELECT COALESCE(
    string_agg(grantee || ':' || privilege_type, ', ' ORDER BY grantee, privilege_type),
    'NO_PRIVILEGE_ROW'
  ) AS value
  FROM information_schema.routine_privileges
  WHERE routine_schema='public' AND routine_name='admin_verify_payment'
),
pay_status AS (
  SELECT COALESCE(
    string_agg(e.enumlabel, ', ' ORDER BY e.enumsortorder),
    'ENUM_NOT_FOUND'
  ) AS value
  FROM pg_type t
  JOIN pg_enum e ON e.enumtypid=t.oid
  JOIN pg_namespace n ON n.oid=t.typnamespace
  WHERE n.nspname='public' AND t.typname='payment_status'
),
cols AS (
  SELECT COALESCE(
    string_agg(
      table_name || '.' || column_name
      || ' [' || data_type || ', udt=' || udt_schema || '.' || udt_name || ']',
      E'\n' ORDER BY table_name, ordinal_position
    ),
    'NO_STATUS_COLUMNS'
  ) AS value
  FROM information_schema.columns
  WHERE table_schema='public'
    AND table_name IN ('orders','payments')
    AND column_name='status'
),
pay_cols AS (
  SELECT COALESCE(
    string_agg(
      column_name || ' [' || data_type || ', udt=' || udt_schema || '.' || udt_name || ']',
      E'\n' ORDER BY ordinal_position
    ),
    'NO_PAYMENTS_COLUMNS'
  ) AS value
  FROM information_schema.columns
  WHERE table_schema='public' AND table_name='payments'
),
admin_fn AS (
  SELECT COALESCE(
    string_agg(
      'signature=' || pg_get_function_identity_arguments(p.oid)
      || '; return=' || pg_get_function_result(p.oid)
      || '; security_definer=' || p.prosecdef::text
      || '; owner=' || pg_get_userbyid(p.proowner),
      E'\n'
    ),
    'IS_ADMIN_NOT_FOUND'
  ) AS value
  FROM pg_proc p
  JOIN pg_namespace n ON n.oid=p.pronamespace
  WHERE n.nspname='public' AND p.proname='is_admin'
),
policies AS (
  SELECT COALESCE(
    string_agg(
      tablename || '.' || policyname
      || ' | roles=' || COALESCE(array_to_string(roles, ','),'')
      || ' | cmd=' || cmd
      || ' | qual=' || COALESCE(qual,'NULL')
      || ' | with_check=' || COALESCE(with_check,'NULL'),
      E'\n' ORDER BY tablename, policyname
    ),
    'NO_POLICIES'
  ) AS value
  FROM pg_policies
  WHERE schemaname='public' AND tablename IN ('orders','payments')
),
rls AS (
  SELECT COALESCE(
    string_agg(tablename || ': rowsecurity=' || rowsecurity::text, ', ' ORDER BY tablename),
    'NO_RLS_ROWS'
  ) AS value
  FROM pg_tables
  WHERE schemaname='public' AND tablename IN ('orders','payments')
),
triggers AS (
  SELECT COALESCE(
    string_agg(
      event_object_table || '.' || trigger_name
      || ' | ' || action_timing || ' ' || event_manipulation
      || ' | ' || action_statement,
      E'\n' ORDER BY event_object_table, trigger_name
    ),
    'NO_TRIGGERS'
  ) AS value
  FROM information_schema.triggers
  WHERE event_object_schema='public'
    AND event_object_table IN ('orders','payments')
),
orders_now AS (
  SELECT COALESCE(
    string_agg(
      o.order_number
      || ' | order_status=' || COALESCE(o.status::text,'NULL')
      || ' | payment_status=' || COALESCE(p.status::text,'NULL')
      || ' | payment_date=' || COALESCE(p.payment_date::text,'NULL')
      || ' | amount=' || COALESCE(p.amount::text,'NULL')
      || ' | verified_at=' || COALESCE(p.verified_at::text,'NULL')
      || ' | verified_by=' || COALESCE(p.verified_by::text,'NULL'),
      E'\n' ORDER BY o.order_number
    ),
    'TARGET_ORDERS_NOT_FOUND'
  ) AS value
  FROM public.orders o
  LEFT JOIN public.payments p ON p.order_id=o.id
  WHERE o.order_number IN ('KS-260922-8170','KS-260922-85CA')
)
SELECT section, value
FROM (
  SELECT 1 AS ord, 'FUNCTION_DEFINITION' AS section, value FROM fn
  UNION ALL SELECT 2, 'FUNCTION_META', value FROM fn_meta
  UNION ALL SELECT 3, 'FUNCTION_PRIVILEGES', value FROM priv
  UNION ALL SELECT 4, 'PAYMENT_STATUS_ENUM_VALUES', value FROM pay_status
  UNION ALL SELECT 5, 'ORDERS_PAYMENTS_STATUS_COLUMNS', value FROM cols
  UNION ALL SELECT 6, 'PAYMENTS_COLUMNS', value FROM pay_cols
  UNION ALL SELECT 7, 'IS_ADMIN_META', value FROM admin_fn
  UNION ALL SELECT 8, 'ORDERS_PAYMENTS_POLICIES', value FROM policies
  UNION ALL SELECT 9, 'ORDERS_PAYMENTS_RLS', value FROM rls
  UNION ALL SELECT 10, 'ORDERS_PAYMENTS_TRIGGERS', value FROM triggers
  UNION ALL SELECT 11, 'TARGET_ORDERS_CURRENT_STATE', value FROM orders_now
) x
ORDER BY ord;
