-- KHANIA STUDIO
-- Fix admin_prepare_brief(): ambiguous reference to order_id
--
-- Root cause:
-- The function has an OUT/return column named "order_id".
-- Inside the query against public.website_briefs, the unqualified
-- "order_id" therefore becomes ambiguous with that PL/pgSQL variable.
--
-- This patch only qualifies the website_briefs table reference:
--     wb.order_id = p_order_id
--
-- No table structure, status values, or payment flow are changed.

CREATE OR REPLACE FUNCTION public.admin_prepare_brief(
  p_order_id uuid,
  p_token_hash text,
  p_token_expires_at timestamp with time zone
)
RETURNS TABLE(
  success boolean,
  brief_id uuid,
  order_id uuid,
  order_number text,
  client_code text,
  client_name text,
  business_name text,
  client_email text,
  package_name text,
  token_expires_at timestamp with time zone,
  message text
)
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path TO 'public'
AS $function$
DECLARE
  v_order public.orders%rowtype;
  v_client public.clients%rowtype;
  v_package public.packages%rowtype;
  v_brief public.website_briefs%rowtype;
BEGIN
  IF NOT public.is_admin() THEN
    RETURN QUERY
    SELECT
      false, NULL::uuid, p_order_id, NULL::text, NULL::text,
      NULL::text, NULL::text, NULL::text, NULL::text,
      p_token_expires_at,
      'Akses ditolak. Hanya admin yang dapat mengirim Form Brief.';
    RETURN;
  END IF;

  IF p_token_hash IS NULL OR length(trim(p_token_hash)) < 32 THEN
    RETURN QUERY
    SELECT
      false, NULL::uuid, p_order_id, NULL::text, NULL::text,
      NULL::text, NULL::text, NULL::text, NULL::text,
      p_token_expires_at,
      'Token brief tidak valid.';
    RETURN;
  END IF;

  SELECT *
  INTO v_order
  FROM public.orders
  WHERE id = p_order_id
  FOR UPDATE;

  IF NOT FOUND THEN
    RETURN QUERY
    SELECT
      false, NULL::uuid, p_order_id, NULL::text, NULL::text,
      NULL::text, NULL::text, NULL::text, NULL::text,
      p_token_expires_at,
      'Order tidak ditemukan.';
    RETURN;
  END IF;

  IF v_order.status <> 'payment_verified'::public.order_status THEN
    RETURN QUERY
    SELECT
      false, NULL::uuid, p_order_id, v_order.order_number,
      NULL::text, NULL::text, NULL::text, NULL::text, NULL::text,
      p_token_expires_at,
      'Form Brief hanya dapat dikirim setelah pembayaran terverifikasi.';
    RETURN;
  END IF;

  SELECT *
  INTO v_client
  FROM public.clients
  WHERE id = v_order.client_id;

  SELECT *
  INTO v_package
  FROM public.packages
  WHERE id = v_order.package_id;

  IF NOT FOUND THEN
    RETURN QUERY
    SELECT
      false, NULL::uuid, p_order_id, v_order.order_number,
      NULL::text, NULL::text, NULL::text, NULL::text, NULL::text,
      p_token_expires_at,
      'Data paket tidak ditemukan.';
    RETURN;
  END IF;

  -- IMPORTANT FIX:
  -- Qualify order_id with the website_briefs table alias.
  SELECT wb.*
  INTO v_brief
  FROM public.website_briefs AS wb
  WHERE wb.order_id = p_order_id
  ORDER BY wb.created_at DESC
  LIMIT 1
  FOR UPDATE;

  IF FOUND THEN
    UPDATE public.website_briefs
       SET brief_token_hash = p_token_hash,
           brief_token_expires_at = p_token_expires_at,
           updated_at = now()
     WHERE id = v_brief.id
     RETURNING * INTO v_brief;
  ELSE
    INSERT INTO public.website_briefs (
      order_id,
      client_id,
      client_code,
      version,
      brief_data,
      status,
      brief_token_hash,
      brief_token_expires_at
    )
    VALUES (
      v_order.id,
      v_order.client_id,
      v_client.client_code,
      1,
      '{}'::jsonb,
      'DRAFT',
      p_token_hash,
      p_token_expires_at
    )
    RETURNING * INTO v_brief;
  END IF;

  RETURN QUERY
  SELECT
    true,
    v_brief.id,
    v_order.id,
    v_order.order_number,
    v_client.client_code,
    v_client.full_name,
    v_client.business_name,
    v_client.email,
    v_package.name,
    p_token_expires_at,
    'Form Brief siap dikirim.';
END;
$function$;

GRANT EXECUTE ON FUNCTION public.admin_prepare_brief(
  uuid,
  text,
  timestamp with time zone
) TO authenticated;

-- Optional read-only verification after execution:
-- SELECT
--   p.oid::regprocedure AS function_signature,
--   pg_get_functiondef(p.oid) AS function_definition
-- FROM pg_proc p
-- JOIN pg_namespace n ON n.oid = p.pronamespace
-- WHERE n.nspname = 'public'
--   AND p.proname = 'admin_prepare_brief';
