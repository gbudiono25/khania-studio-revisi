-- Verifikasi function setelah patch.
-- READ-ONLY.
SELECT
  n.nspname AS schema_name,
  p.proname AS function_name,
  pg_get_function_identity_arguments(p.oid) AS arguments,
  CASE
    WHEN pg_get_functiondef(p.oid) LIKE '%gen_random_bytes%' THEN 'MASIH MENGGUNAKAN gen_random_bytes'
    WHEN pg_get_functiondef(p.oid) LIKE '%md5(v_email || clock_timestamp()::text || random()::text)%' THEN 'SUDAH MENGGUNAKAN GENERATOR TANPA gen_random_bytes'
    ELSE 'PERLU PEMERIKSAAN'
  END AS random_id_check
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
  AND p.proname = 'create_public_order';
