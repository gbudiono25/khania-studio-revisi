-- KHANIA STUDIO — STAGE 2B-2
-- Admin payment verification RPC
--
-- Tujuan:
-- 1. Hanya admin authenticated yang dapat menjalankan fungsi.
-- 2. Order dipindahkan dari payment_received / Menunggu Verifikasi Pembayaran
--    menjadi payment_verified.
-- 3. Jika ada row pada public.payments untuk order tersebut, status payment
--    juga diubah menjadi verified.
-- 4. verified_at / verified_by diisi bila kolom tersebut memang tersedia.
--
-- Tidak mengubah data sampai fungsi dipanggil dari Admin Area.

create or replace function public.admin_verify_payment(
  p_order_id uuid
)
returns table (
  success boolean,
  order_id uuid,
  order_number text,
  new_order_status text,
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
  v_has_payment boolean := false;
  v_sql text;
  v_col_exists boolean;
begin
  if not public.is_admin() then
    return query
    select false, p_order_id, null::text, null::text, false,
           'Akses ditolak. Hanya admin yang dapat memverifikasi pembayaran.';
    return;
  end if;

  select *
    into v_order
  from public.orders
  where id = p_order_id
  for update;

  if not found then
    return query
    select false, p_order_id, null::text, null::text, false,
           'Order tidak ditemukan.';
    return;
  end if;

  if lower(coalesce(v_order.status::text,'')) not in
     ('payment_received','menunggu verifikasi pembayaran') then
    return query
    select false, v_order.id, v_order.order_number,
           v_order.status::text, false,
           'Order tidak berada pada status Menunggu Verifikasi Pembayaran.';
    return;
  end if;

  -- Update order status.
  update public.orders
     set status = 'payment_verified'
   where id = v_order.id;

  -- If payment row exists, update it safely.
  if to_regclass('public.payments') is not null then
    execute
      'select exists(select 1 from public.payments where order_id = $1)'
      into v_has_payment
      using v_order.id;

    if v_has_payment then
      v_payment_updated := true;

      -- status
      select exists(
        select 1 from information_schema.columns
        where table_schema='public' and table_name='payments'
          and column_name='status'
      ) into v_col_exists;

      if v_col_exists then
        execute 'update public.payments set status = $1 where order_id = $2'
        using 'verified', v_order.id;
      end if;

      -- verified_at
      select exists(
        select 1 from information_schema.columns
        where table_schema='public' and table_name='payments'
          and column_name='verified_at'
      ) into v_col_exists;

      if v_col_exists then
        execute 'update public.payments set verified_at = now() where order_id = $1'
        using v_order.id;
      end if;

      -- verified_by
      select exists(
        select 1 from information_schema.columns
        where table_schema='public' and table_name='payments'
          and column_name='verified_by'
      ) into v_col_exists;

      if v_col_exists then
        execute 'update public.payments set verified_by = auth.uid() where order_id = $1'
        using v_order.id;
      end if;

      -- updated_at
      select exists(
        select 1 from information_schema.columns
        where table_schema='public' and table_name='payments'
          and column_name='updated_at'
      ) into v_col_exists;

      if v_col_exists then
        execute 'update public.payments set updated_at = now() where order_id = $1'
        using v_order.id;
      end if;
    end if;
  end if;

  return query
  select true,
         v_order.id,
         v_order.order_number,
         'payment_verified'::text,
         v_payment_updated,
         'Pembayaran berhasil diverifikasi.';
end;
$$;

revoke all on function public.admin_verify_payment(uuid) from public;
grant execute on function public.admin_verify_payment(uuid) to authenticated;

comment on function public.admin_verify_payment(uuid)
is 'Admin-only RPC to verify a payment and move the linked order to payment_verified.';
