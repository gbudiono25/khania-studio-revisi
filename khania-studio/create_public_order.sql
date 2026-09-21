-- KHANIA STUDIO — PUBLIC ORDER RPC
-- Stage: Order -> Supabase foundation
-- Purpose: create a public website order without exposing INSERT/SELECT access
-- to clients/orders tables through the browser.
-- Run after supabase-schema.sql, supabase-schema-migration-2.sql and
-- khania-studio-supabase-stage2a.sql.

create or replace function public.create_public_order(
  p_full_name text,
  p_business_name text,
  p_whatsapp text,
  p_email text,
  p_domain text,
  p_package_code text,
  p_voucher_code text default null
)
returns table (
  success boolean,
  order_id uuid,
  order_number text,
  client_id uuid,
  client_code text,
  package_name text,
  base_price bigint,
  setup_fee bigint,
  voucher_code text,
  voucher_discount bigint,
  total_amount bigint,
  due_date timestamptz,
  status text
)
language plpgsql
security definer
set search_path = public
as $$
declare
  v_package record;
  v_voucher record;
  v_client_id uuid;
  v_client_code text;
  v_order_id uuid;
  v_order_number text;
  v_base bigint;
  v_setup bigint;
  v_subtotal bigint;
  v_discount bigint := 0;
  v_total bigint;
  v_due timestamptz;
  v_voucher_code text := nullif(trim(p_voucher_code), '');
  v_full_name text := trim(p_full_name);
  v_business_name text := trim(p_business_name);
  v_whatsapp text := trim(p_whatsapp);
  v_email text := lower(trim(p_email));
  v_domain text := nullif(trim(coalesce(p_domain, '')), '');
  v_order_date date := timezone('Asia/Jakarta', now())::date;
  v_attempt integer := 0;
begin
  if v_full_name = '' or v_business_name = '' or v_whatsapp = '' or v_email = '' then
    raise exception 'Data pemesan belum lengkap.';
  end if;

  if v_email !~* '^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$' then
    raise exception 'Alamat email tidak valid.';
  end if;

  select p.id, p.name, p.base_price, p.setup_fee
    into v_package
  from public.packages p
  where lower(p.code) = lower(trim(p_package_code))
    and p.active = true
  limit 1;

  if not found then
    raise exception 'Paket website tidak ditemukan atau tidak aktif.';
  end if;

  v_base := v_package.base_price;
  v_setup := v_package.setup_fee;
  v_subtotal := v_base + v_setup;

  if v_voucher_code is not null then
    select * into v_voucher
    from public.vouchers v
    where upper(v.code) = upper(v_voucher_code)
      and (v.active = true or v.active is null)
      and (v.valid_from is null or v.valid_from <= v_order_date)
      and (v.valid_until is null or v.valid_until >= v_order_date)
      and (v.max_uses is null or v.used_count < v.max_uses)
    limit 1;

    if not found then
      v_voucher_code := null;
    else
      if v_voucher.discount_type = 'percent' then
        v_discount := least(
          v_subtotal,
          round(v_subtotal * least(100, v_voucher.discount_value)::numeric / 100)::bigint
        );
      else
        v_discount := least(v_subtotal, greatest(0, v_voucher.discount_value::bigint));
      end if;
    end if;
  end if;

  v_total := greatest(0, v_subtotal - v_discount);
  v_due := make_timestamptz(
    extract(year from (v_order_date + 2))::integer,
    extract(month from (v_order_date + 2))::integer,
    extract(day from (v_order_date + 2))::integer,
    23, 59, 59,
    'Asia/Jakarta'
  );

  -- Reuse an existing client record by email, otherwise create one.
  select c.id
    into v_client_id
  from public.clients c
  where lower(c.email) = v_email
  order by c.created_at desc
  limit 1
  for update;

  if v_client_id is null then
    insert into public.clients (
      full_name, business_name, whatsapp, email, domain
    ) values (
      v_full_name, v_business_name, v_whatsapp, v_email, v_domain
    )
    returning id into v_client_id;
  else
    update public.clients
       set full_name = v_full_name,
           business_name = v_business_name,
           whatsapp = v_whatsapp,
           domain = v_domain,
           updated_at = now()
     where id = v_client_id;
  end if;

  -- Generate the same public Order ID format used by the website.
  loop
    v_attempt := v_attempt + 1;
    v_order_number := 'KS-' || to_char(timezone('Asia/Jakarta', now()), 'YYMMDD')
      || '-' || upper(substr(encode(gen_random_bytes(2), 'hex'), 1, 4));

    begin
      insert into public.orders (
        order_number, client_id, package_id,
        order_date, due_date,
        base_price, setup_fee,
        voucher_code, voucher_discount, total_amount,
        status, notes
      ) values (
        v_order_number, v_client_id, v_package.id,
        v_order_date, v_due,
        v_base, v_setup,
        v_voucher_code, v_discount, v_total,
        'pending_payment', 'Order via website (pemesanan.html)'
      )
      returning id into v_order_id;

      exit;
    exception when unique_violation then
      if v_attempt >= 5 then
        raise exception 'Gagal membuat nomor order unik. Silakan coba lagi.';
      end if;
    end;
  end loop;

  select c.client_code into v_client_code
  from public.clients c
  where c.id = v_client_id;

  return query
  select
    true,
    v_order_id,
    v_order_number,
    v_client_id,
    v_client_code,
    v_package.name,
    v_base,
    v_setup,
    v_voucher_code,
    v_discount,
    v_total,
    v_due,
    'pending_payment';
end;
$$;

revoke all on function public.create_public_order(text,text,text,text,text,text,text) from public;
grant execute on function public.create_public_order(text,text,text,text,text,text,text) to anon;
grant execute on function public.create_public_order(text,text,text,text,text,text,text) to authenticated;

comment on function public.create_public_order(text,text,text,text,text,text,text)
is 'Controlled public order creation for Khania Studio. Prices are read from packages; client/order tables remain protected by RLS.';
