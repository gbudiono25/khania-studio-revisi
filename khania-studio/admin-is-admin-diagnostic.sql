-- KHANIA STUDIO
-- Diagnostic is_admin() + profile admin
-- READ-ONLY: tidak mengubah data atau struktur database.

-- 1. Definisi function public.is_admin()
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
  AND p.proname = 'is_admin';

-- 2. Struktur tabel profiles
SELECT
  ordinal_position,
  column_name,
  data_type,
  udt_schema,
  udt_name,
  is_nullable
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'profiles'
ORDER BY ordinal_position;

-- 3. Struktur tabel clients (untuk memastikan hubungan user/client)
SELECT
  ordinal_position,
  column_name,
  data_type,
  udt_schema,
  udt_name,
  is_nullable
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'clients'
ORDER BY ordinal_position;

-- 4. Struktur auth.users yang dapat dibaca dari SQL Editor
-- Hanya metadata identitas/role, tanpa password atau token.
SELECT
  id,
  email,
  created_at,
  last_sign_in_at,
  raw_app_meta_data ->> 'role' AS app_role,
  raw_user_meta_data ->> 'role' AS user_role
FROM auth.users
WHERE lower(email) = lower('gbudiono.25@gmail.com');

-- 5. Profile yang terkait dengan akun admin
SELECT
  p.*
FROM public.profiles p
WHERE p.id = (
  SELECT id
  FROM auth.users
  WHERE lower(email) = lower('gbudiono.25@gmail.com')
  LIMIT 1
);

-- 6. Jika profiles memiliki user_id, tampilkan profile berdasarkan user_id
SELECT
  p.*
FROM public.profiles p
WHERE EXISTS (
  SELECT 1
  FROM information_schema.columns c
  WHERE c.table_schema = 'public'
    AND c.table_name = 'profiles'
    AND c.column_name = 'user_id'
)
AND p.user_id = (
  SELECT id
  FROM auth.users
  WHERE lower(email) = lower('gbudiono.25@gmail.com')
  LIMIT 1
);

-- 7. Evaluasi is_admin() di SQL Editor.
-- Catatan: SQL Editor biasanya tidak memiliki JWT user Admin Panel,
-- sehingga hasil ini TIDAK boleh dianggap sebagai hasil login browser.
SELECT public.is_admin() AS is_admin_in_sql_editor;
