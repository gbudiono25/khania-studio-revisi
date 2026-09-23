-- KHANIA STUDIO
-- FINAL PATCH V2: admin_verify_payment()
--
-- Error sebelumnya:
-- 42P13 cannot change return type of existing function
--
-- Penyebab:
-- PostgreSQL tidak mengizinkan CREATE OR REPLACE FUNCTION jika
-- OUT/return table type berbeda dari function yang sudah ada.
--
-- Solusi:
-- DROP FUNCTION admin_verify_payment(uuid), kemudian CREATE FUNCTION
-- dengan return type yang dipakai Admin Panel.

drop function if exists public.admin_verify_payment(uuid);

create function public.admin_verify_payment(p_order_id uuid)
returns table (
  success boolean,
  order_id uuid,
  payment_updated boolean,
  message text
)
language plpgsql
security definer
set search_path = public
as $$
declare
  v_order public.orders%rowtype;
  v_payment_id uuid;
  v_payment_updated boolean := false;
begin
  -- 1. Hanya admin yang boleh melakukan verifikasi.
  if not public.is_admin() then
    return query
    select
      false,
      p_order_id,
      false,
      'Akses ditolak. Hanya admin yang dapat memverifikasi pembayaran.';
    return;
  end if;

  -- 2. Ambil order dan lock baris selama transaksi.
  select *
    into v_order
  from public.orders
  where id = p_order_id
  for update;

  if not found then
    return query
    select
      false,
      p_order_id,
      false,
      'Order tidak ditemukan.';
    return;
  end if;

  -- 3. Hanya order payment_received yang boleh diverifikasi.
  if v_order.status <> 'payment_received'::public.order_status then
    return query
    select
      false,
      p_order_id,
      false,
      'Order tidak sedang menunggu verifikasi pembayaran.';
    return;
  end if;

  -- 4. Cari payment pending.
  select p.id
    into v_payment_id
  from public.payments p
  where p.order_id = p_order_id
    and p.status = 'pending'::public.payment_status
  order by p.created_at desc nulls last
  limit 1
  for update;

  if v_payment_id is null then
    return query
    select
      false,
      p_order_id,
      false,
      'Data pembayaran pending untuk order ini tidak ditemukan.';
    return;
  end if;

  -- 5. Verifikasi payment.
  update public.payments
     set status = 'verified'::public.payment_status,
         verified_at = now(),
         verified_by = auth.uid()
   where id = v_payment_id;

  v_payment_updated := found;

  if not v_payment_updated then
    return query
    select
      false,
      p_order_id,
      false,
      'Data pembayaran gagal diperbarui.';
    return;
  end if;

  -- 6. Verifikasi order.
  update public.orders
     set status = 'payment_verified'::public.order_status
   where id = p_order_id;

  if not found then
    raise exception 'Order gagal diperbarui setelah payment berhasil diperbarui.';
  end if;

  -- 7. Respons yang dibaca oleh admin-pesanan-live.js.
  return query
  select
    true,
    p_order_id,
    true,
    'Pembayaran berhasil diverifikasi.';
end;
$$;

revoke all on function public.admin_verify_payment(uuid) from public;
grant execute on function public.admin_verify_payment(uuid) to authenticated;

-- CHECK
select
  n.nspname as schema_name,
  p.oid::regprocedure as function_signature,
  pg_get_function_result(p.oid) as return_type,
  p.prosecdef as security_definer,
  pg_get_userbyid(p.proowner) as owner
from pg_proc p
join pg_namespace n on n.oid = p.pronamespace
where n.nspname = 'public'
  and p.proname = 'admin_verify_payment';
