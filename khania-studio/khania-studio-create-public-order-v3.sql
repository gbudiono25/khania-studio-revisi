-- KHANIA STUDIO — create_public_order() V3
-- Tujuan:
-- 1) Mencegah order baru mengubah data client lama hanya karena email sama.
-- 2) Jika email sama tetapi identitas order berbeda, buat client record baru.
-- 3) Jika email + nama + bisnis + WhatsApp sama persis, gunakan client yang sama
--    tanpa mengubah data client.
-- 4) Mempertahankan kontrak RPC yang sudah dipakai oleh website.
--
-- Catatan:
-- - Tidak mengubah tabel/schema.
-- - Tidak mengubah alur Payment Verification.
-- - Harga tetap diambil dari public.packages.
-- - Voucher tetap divalidasi di server.
-- - Client code dibuat oleh trigger orders_ensure_client_project_code
--   yang sudah ada pada Stage 2A.

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
  v_full_name text;
  v_business_name text;
  v_whatsapp text;
  v_email text;
  v_domain text;
begin
  v_full_name := trim(coalesce(p_full_name, ''));
  v_business_name := trim(coalesce(p_business_name, ''));
  v_whatsapp := trim(coalesce(p_whatsapp, ''));
  v_email := lower(trim(coalesce(p_email, '')));
  v_domain := nullif(trim(coalesce(p_domain, '')), '');
  v_package_code := lower(trim(coalesce(p_package_code, '')));
  v_voucher_code := nullif(upper(trim(coalesce(p_voucher_code, ''))), '');

  if v_full_name = ''
     or v_business_name = ''
     or v_whatsapp = ''
     or v_email = ''
     or v_package_code = '' then
    raise exception using
      errcode = '22023',
      message = 'Data order wajib belum lengkap.';
  end if;

  -- Paket tetap authoritative di database.
  select p.id, p.code, p.name, p.base_price, p.setup_fee
    into v_package
  from public.packages p
  where lower(p.code) = v_package_code
    and p.active = true
  limit 1;

  if not found then
    raise exception using
      errcode = '22023',
      message = 'Paket website tidak ditemukan atau tidak aktif.';
  end if;

  v_base_price := coalesce(v_package.base_price, 0);
  v_setup_fee := coalesce(v_package.setup_fee, 0);
  v_total_amount := v_base_price + v_setup_fee;

  -- Voucher tetap divalidasi di server.
  if v_voucher_code is not null then
    select v.code, v.active, v.valid_from, v.valid_until, v.max_uses,
           v.used_count, v.discount_type, v.discount_value
      into v_voucher
    from public.vouchers v
    where upper(v.code) = v_voucher_code
      and v.active = true
    limit 1;

    if not found then
      raise exception using
        errcode = '22023',
        message = 'Voucher tidak ditemukan atau tidak aktif.';
    end if;

    v_today := current_date;

    if v_voucher.valid_from is not null
       and v_today < v_voucher.valid_from then
      raise exception using
        errcode = '22023',
        message = 'Voucher belum mulai berlaku.';
    end if;

    if v_voucher.valid_until is not null
       and v_today > v_voucher.valid_until then
      raise exception using
        errcode = '22023',
        message = 'Masa berlaku voucher sudah berakhir.';
    end if;

    if v_voucher.max_uses is not null
       and coalesce(v_voucher.used_count, 0) >= v_voucher.max_uses then
      raise exception using
        errcode = '22023',
        message = 'Batas penggunaan voucher sudah tercapai.';
    end if;

    if lower(coalesce(v_voucher.discount_type, 'amount')) = 'percent' then
      v_discount_raw :=
        v_total_amount
        * least(100, greatest(0, coalesce(v_voucher.discount_value, 0)))
        / 100;
    else
      v_discount_raw := greatest(0, coalesce(v_voucher.discount_value, 0));
    end if;

    v_voucher_discount :=
      least(v_total_amount, greatest(0, round(v_discount_raw)::bigint));

    v_total_amount := greatest(0, v_total_amount - v_voucher_discount);
  end if;

  -- H+2 23:59 WIB.
  v_due_date :=
    (((now() at time zone 'Asia/Jakarta')::date + 2)::timestamp
      + time '23:59:59')::timestamptz;

  /*
   * CLIENT MATCHING — V3
   *
   * Email saja TIDAK lagi menjadi kunci identitas.
   *
   * Jika email sama tetapi nama/bisnis/WhatsApp berbeda:
   *   -> buat client baru
   *   -> jangan update client lama.
   *
   * Jika keempat identitas cocok persis:
   *   -> gunakan client yang sama
   *   -> jangan mengubah record client.
   */
  select c.id, c.client_code
    into v_client_id, v_client_code
  from public.clients c
  where lower(trim(c.email)) = v_email
    and lower(trim(c.full_name)) = lower(v_full_name)
    and lower(trim(c.business_name)) = lower(v_business_name)
    and trim(c.whatsapp) = v_whatsapp
  order by c.created_at asc
  limit 1;

  if v_client_id is null then
    insert into public.clients (
      full_name,
      business_name,
      whatsapp,
      email,
      domain
    )
    values (
      v_full_name,
      v_business_name,
      v_whatsapp,
      v_email,
      v_domain
    )
    returning id, client_code
      into v_client_id, v_client_code;
  end if;

  -- Order number: KS-YYMMDD-XXXX.
  -- Tidak menggunakan gen_random_bytes().
  loop
    v_order_number :=
      'KS-' ||
      to_char((now() at time zone 'Asia/Jakarta')::date, 'YYMMDD') ||
      '-' ||
      upper(substr(
        md5(random()::text || clock_timestamp()::text || v_email),
        1,
        4
      ));

    exit when not exists (
      select 1
      from public.orders o
      where o.order_number = v_order_number
    );
  end loop;

  insert into public.orders (
    order_number,
    client_id,
    package_id,
    order_date,
    due_date,
    base_price,
    setup_fee,
    voucher_code,
    voucher_discount,
    total_amount,
    status,
    notes
  )
  values (
    v_order_number,
    v_client_id,
    v_package.id,
    (now() at time zone 'Asia/Jakarta')::date,
    v_due_date,
    v_base_price,
    v_setup_fee,
    case
      when v_voucher_code is null then null
      else v_voucher_code
    end,
    v_voucher_discount,
    v_total_amount,
    'pending_payment',
    'Public website order via Khania Studio.'
  )
  returning id into v_order_id;

  if v_voucher_code is not null then
    update public.vouchers
       set used_count = coalesce(used_count, 0) + 1,
           updated_at = now()
     where upper(code) = v_voucher_code;
  end if;

  -- Trigger pada orders seharusnya sudah mengisi client_code
  -- untuk client baru. Ambil nilai final dari tabel clients.
  select c.client_code
    into v_client_code
  from public.clients c
  where c.id = v_client_id;

  return query
  select
    true,
    v_order_id,
    v_order_number,
    v_client_id,
    v_client_code,
    v_package.name::text,
    v_base_price,
    v_setup_fee,
    v_voucher_code,
    v_voucher_discount,
    v_total_amount,
    v_due_date,
    'pending_payment'::text;
end;
$$;

revoke all on function public.create_public_order(
  text, text, text, text, text, text, text
) from public;

grant execute on function public.create_public_order(
  text, text, text, text, text, text, text
) to anon;

grant execute on function public.create_public_order(
  text, text, text, text, text, text, text
) to authenticated;

-- Verifikasi bahwa fungsi V3 terdaftar dengan kontrak RPC yang sama.
select
  n.nspname as schema_name,
  p.oid::regprocedure as function_signature,
  pg_get_function_result(p.oid) as return_type,
  p.prosecdef as security_definer,
  pg_get_userbyid(p.proowner) as owner
from pg_proc p
join pg_namespace n
  on n.oid = p.pronamespace
where n.nspname = 'public'
  and p.proname = 'create_public_order';
