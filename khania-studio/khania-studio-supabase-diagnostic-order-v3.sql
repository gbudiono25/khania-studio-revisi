-- KHANIA STUDIO — SUPABASE ORDER DIAGNOSTIC V3
-- READ-ONLY. Jalankan seluruh query ini sebagai SATU query.
-- Hasil dibuat ringkas agar seluruh informasi penting terlihat dalam satu result.

WITH
table_columns AS (
  SELECT
    table_name,
    string_agg(
      column_name || ' [' || data_type ||
      CASE WHEN udt_name IS NOT NULL THEN ', ' || udt_name ELSE '' END ||
      CASE WHEN is_nullable = 'NO' THEN ', NOT NULL' ELSE '' END || ']',
      E'\n' ORDER BY ordinal_position
    ) AS details
  FROM information_schema.columns
  WHERE table_schema = 'public'
    AND table_name IN ('packages','clients','orders','vouchers')
  GROUP BY table_name
),
rpc AS (
  SELECT
    CASE WHEN COUNT(*) = 0 THEN
      'NOT FOUND'
    ELSE
      string_agg(
        'FUNCTION: ' || n.nspname || '.' || p.proname ||
        '(' || pg_get_function_identity_arguments(p.oid) || ')' ||
        E'\nSECURITY DEFINER: ' || p.prosecdef::text ||
        E'\nRETURN: ' || pg_get_function_result(p.oid) ||
        E'\n\nDEFINITION:\n' || pg_get_functiondef(p.oid),
        E'\n\n------------------------------\n\n'
        ORDER BY p.oid
      )
    END AS details
  FROM pg_proc p
  JOIN pg_namespace n ON n.oid = p.pronamespace
  WHERE n.nspname = 'public'
    AND p.proname = 'create_public_order'
),
trigger_info AS (
  SELECT
    CASE WHEN COUNT(*) = 0 THEN
      'No user trigger found on public.orders'
    ELSE
      string_agg(
        tg.tgname || E'\n' || pg_get_triggerdef(tg.oid),
        E'\n\n'
        ORDER BY tg.tgname
      )
    END AS details
  FROM pg_trigger tg
  JOIN pg_class c ON c.oid = tg.tgrelid
  JOIN pg_namespace n ON n.oid = c.relnamespace
  WHERE n.nspname = 'public'
    AND c.relname = 'orders'
    AND NOT tg.tgisinternal
),
rls_info AS (
  SELECT
    string_agg(
      c.relname ||
      ' | RLS=' || c.relrowsecurity::text ||
      ' | FORCE_RLS=' || c.relforcerowsecurity::text,
      E'\n' ORDER BY c.relname
    ) AS details
  FROM pg_class c
  JOIN pg_namespace n ON n.oid = c.relnamespace
  WHERE n.nspname = 'public'
    AND c.relname IN ('packages','clients','orders','vouchers','website_briefs','brief_scope_reviews')
),
policy_info AS (
  SELECT
    CASE WHEN COUNT(*) = 0 THEN 'No policies found for requested tables'
    ELSE
      string_agg(
        tablename || ' | ' || policyname ||
        ' | roles=' || COALESCE(array_to_string(roles, ','), '') ||
        ' | cmd=' || cmd ||
        E'\nUSING=' || COALESCE(qual, '') ||
        E'\nWITH CHECK=' || COALESCE(with_check, ''),
        E'\n\n'
        ORDER BY tablename, policyname
      )
    END AS details
  FROM pg_policies
  WHERE schemaname = 'public'
    AND tablename IN ('packages','clients','orders','vouchers','website_briefs','brief_scope_reviews')
),
privilege_info AS (
  SELECT
    CASE WHEN COUNT(*) = 0 THEN 'No EXECUTE privilege rows found'
    ELSE
      string_agg(
        routine_name || ' | ' || grantee || ' | ' || privilege_type,
        E'\n' ORDER BY grantee
      )
    END AS details
  FROM information_schema.routine_privileges
  WHERE routine_schema = 'public'
    AND routine_name = 'create_public_order'
),
active_columns AS (
  SELECT
    CASE WHEN COUNT(*) = 0 THEN 'No active/is_active columns found'
    ELSE
      string_agg(table_name || '.' || column_name, E'\n' ORDER BY table_name, column_name)
    END AS details
  FROM information_schema.columns
  WHERE table_schema = 'public'
    AND table_name IN ('packages','vouchers')
    AND column_name IN ('active','is_active')
),
project_code_fn AS (
  SELECT
    CASE WHEN COUNT(*) = 0 THEN 'NOT FOUND'
    ELSE
      string_agg(
        n.nspname || '.' || p.proname ||
        '(' || pg_get_function_identity_arguments(p.oid) || ')' ||
        E'\nSECURITY DEFINER: ' || p.prosecdef::text ||
        E'\nDEFINITION:\n' || pg_get_functiondef(p.oid),
        E'\n\n------------------------------\n\n'
        ORDER BY p.oid
      )
    END AS details
  FROM pg_proc p
  JOIN pg_namespace n ON n.oid = p.pronamespace
  WHERE n.nspname = 'public'
    AND p.proname = 'orders_ensure_client_project_code'
)
SELECT '1 — PACKAGES' AS section, COALESCE((SELECT details FROM table_columns WHERE table_name='packages'),'TABLE NOT FOUND') AS result
UNION ALL
SELECT '2 — CLIENTS', COALESCE((SELECT details FROM table_columns WHERE table_name='clients'),'TABLE NOT FOUND')
UNION ALL
SELECT '3 — ORDERS', COALESCE((SELECT details FROM table_columns WHERE table_name='orders'),'TABLE NOT FOUND')
UNION ALL
SELECT '4 — VOUCHERS', COALESCE((SELECT details FROM table_columns WHERE table_name='vouchers'),'TABLE NOT FOUND')
UNION ALL
SELECT '5 — create_public_order RPC', details FROM rpc
UNION ALL
SELECT '6 — orders triggers', details FROM trigger_info
UNION ALL
SELECT '7 — RLS', details FROM rls_info
UNION ALL
SELECT '8 — POLICIES', details FROM policy_info
UNION ALL
SELECT '9 — RPC EXECUTE PRIVILEGES', details FROM privilege_info
UNION ALL
SELECT '10 — active / is_active', details FROM active_columns
UNION ALL
SELECT '11 — orders_ensure_client_project_code()', details FROM project_code_fn
ORDER BY section;
