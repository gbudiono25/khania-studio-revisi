-- KHANIA STUDIO
-- Diagnostic Verifikasi Pembayaran V4
-- READ-ONLY: hanya memeriksa enum order_status.
-- Tidak ada INSERT / UPDATE / DELETE / ALTER / DROP / CREATE.

-- 1. Semua nilai enum public.order_status
SELECT
  n.nspname AS enum_schema,
  t.typname AS enum_name,
  e.enumsortorder,
  e.enumlabel
FROM pg_type t
JOIN pg_enum e ON e.enumtypid = t.oid
JOIN pg_namespace n ON n.oid = t.typnamespace
WHERE n.nspname = 'public'
  AND t.typname = 'order_status'
ORDER BY e.enumsortorder;

-- 2. Pemeriksaan langsung status yang dipakai fungsi verifikasi
SELECT
  status_value,
  CASE
    WHEN EXISTS (
      SELECT 1
      FROM pg_type t
      JOIN pg_enum e ON e.enumtypid = t.oid
      JOIN pg_namespace n ON n.oid = t.typnamespace
      WHERE n.nspname = 'public'
        AND t.typname = 'order_status'
        AND e.enumlabel = status_value
    )
    THEN 'ADA DI ENUM order_status'
    ELSE 'TIDAK ADA DI ENUM order_status'
  END AS result
FROM (
  VALUES
    ('payment_received'),
    ('payment_verified'),
    ('menunggu pembayaran'),
    ('menunggu verifikasi pembayaran'),
    ('pembayaran terverifikasi')
) AS x(status_value);

-- 3. Status aktual order yang sedang kita uji
SELECT
  o.order_number,
  o.status::text AS current_order_status
FROM public.orders o
WHERE o.order_number IN (
  'KS-260922-8170',
  'KS-260922-85CA'
)
ORDER BY o.order_number;
