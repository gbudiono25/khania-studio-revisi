-- KHANIA STUDIO — SUPABASE ORDER DIAGNOSTIC V5
-- READ-ONLY. Fokus agar definisi RPC/trigger bisa dibaca baris demi baris.

-- ============================================================
-- A. DEFINISI create_public_order() — SATU BARIS PER BARIS
-- ============================================================
WITH f AS (
  SELECT pg_get_functiondef(p.oid) AS def
  FROM pg_proc p
  JOIN pg_namespace n ON n.oid = p.pronamespace
  WHERE n.nspname = 'public'
    AND p.proname = 'create_public_order'
  ORDER BY p.oid
  LIMIT 1
)
SELECT
  row_number() OVER () AS line_no,
  line
FROM f,
LATERAL regexp_split_to_table(f.def, E'\n') AS line;

-- ============================================================
-- B. TRIGGER PADA orders
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
-- C. FUNCTION YANG DIPANGGIL TRIGGER orders
--    Menampilkan function yang terkait dengan trigger secara
--    langsung melalui tgfoid.
-- ============================================================
SELECT
  tg.tgname AS trigger_name,
  pn.nspname AS function_schema,
  pp.proname AS function_name,
  pg_get_function_identity_arguments(pp.oid) AS arguments,
  pg_get_functiondef(pp.oid) AS function_definition
FROM pg_trigger tg
JOIN pg_class c ON c.oid = tg.tgrelid
JOIN pg_namespace tn ON tn.oid = c.relnamespace
JOIN pg_proc pp ON pp.oid = tg.tgfoid
JOIN pg_namespace pn ON pn.oid = pp.pronamespace
WHERE tn.nspname = 'public'
  AND c.relname = 'orders'
  AND NOT tg.tgisinternal
ORDER BY tg.tgname;

-- ============================================================
-- D. NILAI ENUM orders.status
-- ============================================================
SELECT
  n.nspname AS enum_schema,
  t.typname AS enum_type,
  e.enumsortorder,
  e.enumlabel AS allowed_status
FROM pg_type t
JOIN pg_namespace n ON n.oid = t.typnamespace
JOIN pg_enum e ON e.enumtypid = t.oid
WHERE t.typname = (
  SELECT c.udt_name
  FROM information_schema.columns c
  WHERE c.table_schema = 'public'
    AND c.table_name = 'orders'
    AND c.column_name = 'status'
  LIMIT 1
)
ORDER BY e.enumsortorder;

-- ============================================================
-- E. KOLOM orders YANG RELEVAN UNTUK RPC
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
  AND column_name IN (
    'id','order_number','client_id','package_id','order_date',
    'due_date','base_price','setup_fee','voucher_code',
    'voucher_discount','total_amount','status','notes',
    'client_code'
  )
ORDER BY ordinal_position;

-- ============================================================
-- F. KOLOM packages YANG RELEVAN UNTUK RPC
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
  AND column_name IN (
    'id','code','name','base_price','setup_fee','active'
  )
ORDER BY ordinal_position;

-- ============================================================
-- G. KOLOM vouchers YANG RELEVAN UNTUK RPC
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
  AND column_name IN (
    'id','code','discount_type','discount_value','active',
    'valid_from','valid_until','max_uses','used_count'
  )
ORDER BY ordinal_position;
