-- KHANIA STUDIO — create_public_order() FIX V2
-- Root cause confirmed by production diagnostic: gen_random_bytes(integer) does not exist.
-- This version keeps the RPC contract and removes only that dependency.

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
  v_base_price bigint := 0;
  v_setup_fee bigint := 0;
  v_voucher_discount bigint := 0;
  v_total_amount bigint := 0;
  v_due_date timestamptz;
  v_today date;
  v_package_code text;
  v_voucher_code text;
  v_discount_raw numeric := 0;
begin
  v_package_code := lower(trim(coalesce(p_package_code, '')));
  v_voucher_code := nullif(upper(trim(coalesce(p_voucher_code, ''))), '');

  if nullif(trim(coalesce(p_full_name, '')), '') is null
     or nullif(trim(coalesce(p_business_name, '')), '') is null
     or nullif(trim(coalesce(p_whatsapp, '')), '') is null
     or nullif(trim(coalesce(p_email, '')), '') is null
     or v_package_code = '' then
    raise exception using errcode = '22023', message = 'Data order wajib belum lengkap.';
  end if;

  select p.id, p.code, p.name, p.base_price, p.setup_fee
    into v_package
  from public.packages p
  where lower(p.code) = v_package_code
    and p.active = true
  limit 1;

  if not found then
    raise exception using errcode = '22023', message = 'Paket website tidak ditemukan atau tidak aktif.';
  end if;

  v_base_price := coalesce(v_package.base_price, 0);
  v_setup_fee := coalesce(v_package.setup_fee, 0);
  v_total_amount := v_base_price + v_setup_fee;

  if v_voucher_code is not null then
    select v.code, v.active, v.valid_from, v.valid_until, v.max_uses,
           v.used_count, v.discount_type, v.discount_value
      into v_voucher
    from public.vouchers v
    where upper(v.code) = v_voucher_code
      and v.active = true
    limit 1;

    if not found then
      raise exception using errcode = '22023', message = 'Voucher tidak ditemukan atau tidak aktif.';
    end if;

    v_today := current_date;

    if v_voucher.valid_from is not null and v_today < v_voucher.valid_from then
      raise exception using errcode = '22023', message = 'Voucher belum mulai berlaku.';
    end if;

    if v_voucher.valid_until is not null and v_today > v_voucher.valid_until then
      raise exception using errcode = '22023', message = 'Masa berlaku voucher sudah berakhir.';
    end if;

    if v_voucher.max_uses is not null and coalesce(v_voucher.used_count, 0) >= v_voucher.max_uses then
      raise exception using errcode = '22023', message = 'Batas penggunaan voucher sudah tercapai.';
    end if;

    if lower(coalesce(v_voucher.discount_type, 'amount')) = 'percent' then
      v_discount_raw := v_total_amount * least(100, greatest(0, coalesce(v_voucher.discount_value, 0))) / 100;
    else
      v_discount_raw := greatest(0, coalesce(v_voucher.discount_value, 0));
    end if;

    v_voucher_discount := least(v_total_amount, greatest(0, round(v_discount_raw)::bigint));
    v_total_amount := greatest(0, v_total_amount - v_voucher_discount);
  end if;

  v_due_date := (((now() at time zone 'Asia/Jakarta')::date + 2)::timestamp + time '23:59:59')::timestamptz;

  select c.id, c.client_code
    into v_client_id, v_client_code
  from public.clients c
  where lower(trim(c.email)) = lower(trim(p_email))
  limit 1;

  if v_client_id is null then
    insert into public.clients (full_name, business_name, whatsapp, email, domain)
    values (trim(p_full_name), trim(p_business_name), trim(p_whatsapp), lower(trim(p_email)), nullif(trim(coalesce(p_domain, '')), ''))
    returning id, client_code into v_client_id, v_client_code;
  else
    update public.clients
       set full_name = trim(p_full_name),
           business_name = trim(p_business_name),
           whatsapp = trim(p_whatsapp),
           domain = nullif(trim(coalesce(p_domain, '')), ''),
           updated_at = now()
     where id = v_client_id;

    select c.client_code into v_client_code
    from public.clients c where c.id = v_client_id;
  end if;

  -- Built-in PostgreSQL generator: no gen_random_bytes() dependency.
  loop
    v_order_number := 'KS-' ||
      to_char((now() at time zone 'Asia/Jakarta')::date, 'YYMMDD') || '-' ||
      upper(substr(md5(random()::text || clock_timestamp()::text || coalesce(p_email, '')), 1, 4));

    exit when not exists (
      select 1 from public.orders o where o.order_number = v_order_number
    );
  end loop;

  insert into public.orders (
    order_number, client_id, package_id, order_date, due_date,
    base_price, setup_fee, voucher_code, voucher_discount,
    total_amount, status, notes
  )
  values (
    v_order_number, v_client_id, v_package.id,
    (now() at time zone 'Asia/Jakarta')::date,
    v_due_date, v_base_price, v_setup_fee,
    case when v_voucher_code is null then null else v_voucher_code end,
    v_voucher_discount, v_total_amount, 'pending_payment',
    'Public website order via Khania Studio.'
  )
  returning id into v_order_id;

  if v_voucher_code is not null then
    update public.vouchers
       set used_count = coalesce(used_count, 0) + 1,
           updated_at = now()
     where upper(code) = v_voucher_code;
  end if;

  select c.client_code into v_client_code
  from public.clients c where c.id = v_client_id;

  return query
  select true, v_order_id, v_order_number, v_client_id, v_client_code,
         v_package.name::text, v_base_price, v_setup_fee, v_voucher_code,
         v_voucher_discount, v_total_amount, v_due_date, 'pending_payment'::text;
end;
$$;

revoke all on function public.create_public_order(text,text,text,text,text,text,text) from public;
grant execute on function public.create_public_order(text,text,text,text,text,text,text) to anon;
grant execute on function public.create_public_order(text,text,text,text,text,text,text) to authenticated;

select n.nspname as schema_name,
       p.oid::regprocedure as function_signature,
       pg_get_function_result(p.oid) as return_type
from pg_proc p
join pg_namespace n on n.oid = p.pronamespace
where n.nspname = 'public' and p.proname = 'create_public_order';
