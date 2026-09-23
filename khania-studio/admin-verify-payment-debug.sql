-- KHANIA STUDIO
-- DEBUG admin_verify_payment, READ-ONLY from data perspective.
-- Executes the same UPDATE shapes inside an exception block and
-- deliberately rolls them back. It returns the exact PostgreSQL
-- SQLSTATE/message if an UPDATE fails.
--
-- Run this SQL once in Supabase SQL Editor, then call the function
-- from the browser diagnostic page supplied separately.

CREATE OR REPLACE FUNCTION public.admin_verify_payment_debug(p_order_id uuid)
RETURNS TABLE(
  success boolean,
  checkpoint text,
  sqlstate text,
  message text
)
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_order_status text;
  v_payment_status text;
BEGIN
  IF NOT public.is_admin() THEN
    RETURN QUERY SELECT false, 'admin_check', NULL::text,
      'Akses ditolak. Hanya admin yang dapat memverifikasi pembayaran.';
    RETURN;
  END IF;

  SELECT status::text INTO v_order_status
  FROM public.orders
  WHERE id = p_order_id
  FOR UPDATE;

  IF v_order_status IS NULL THEN
    RETURN QUERY SELECT false, 'order_lookup', NULL::text, 'Order tidak ditemukan.';
    RETURN;
  END IF;

  IF v_order_status <> 'payment_received' THEN
    RETURN QUERY SELECT false, 'order_status_check', NULL::text,
      'Status order bukan payment_received: ' || v_order_status;
    RETURN;
  END IF;

  SELECT status::text INTO v_payment_status
  FROM public.payments
  WHERE order_id = p_order_id
  ORDER BY created_at DESC NULLS LAST
  LIMIT 1
  FOR UPDATE;

  IF v_payment_status IS NULL THEN
    RETURN QUERY SELECT false, 'payment_lookup', NULL::text, 'Payment tidak ditemukan.';
    RETURN;
  END IF;

  IF v_payment_status <> 'pending' THEN
    RETURN QUERY SELECT false, 'payment_status_check', NULL::text,
      'Status payment bukan pending: ' || v_payment_status;
    RETURN;
  END IF;

  -- Everything below is deliberately executed in a nested block.
  -- Any exception rolls back all statements in this block.
  BEGIN
    UPDATE public.orders
    SET status = 'payment_verified'::public.order_status
    WHERE id = p_order_id;

    UPDATE public.payments
    SET status = 'verified'::public.payment_status,
        verified_at = now(),
        verified_by = auth.uid()
    WHERE order_id = p_order_id;

    -- Deliberately raise a local exception so the two UPDATEs above
    -- are rolled back even when they succeed.
    RAISE EXCEPTION USING
      ERRCODE = 'P0001',
      MESSAGE = '__KHANIA_DEBUG_ROLLBACK__';
  EXCEPTION
    WHEN OTHERS THEN
      IF SQLERRM = '__KHANIA_DEBUG_ROLLBACK__' THEN
        RETURN QUERY SELECT true, 'update_test_passed', SQLSTATE, 
          'UPDATE orders + payments berhasil dijalankan dan sengaja di-ROLLBACK. Tidak ada perubahan tersimpan.';
        RETURN;
      ELSE
        RETURN QUERY SELECT false, 'update_test_failed', SQLSTATE, SQLERRM;
        RETURN;
      END IF;
  END;
END;
$$;

GRANT EXECUTE ON FUNCTION public.admin_verify_payment_debug(uuid)
TO authenticated;
