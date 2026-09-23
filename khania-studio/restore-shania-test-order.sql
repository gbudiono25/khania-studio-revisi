-- KHANIA STUDIO — RESTORE SHANIA TEST ORDER
-- Scope: ONLY order KS-260922-8170 and its client relation.
-- No changes to Nunik's order KS-260923-6971.
-- Run in Supabase SQL Editor.
--
-- This script is deliberately guarded:
-- 1) It checks the target order exists.
-- 2) It checks the target order is currently attached to the shared client.
-- 3) It creates a new Shania client record.
-- 4) It moves only the target order to that new client.
-- 5) It verifies both orders afterwards.
--
-- It does NOT change payment records or order amounts/statuses.

begin;

do $$
declare
  v_shania_order_id uuid;
  v_current_client_id uuid;
  v_nunik_order_client_id uuid;
  v_new_client_id uuid;
  v_new_client_code text;
begin
  select o.id, o.client_id
    into v_shania_order_id, v_current_client_id
  from public.orders o
  where o.order_number = 'KS-260922-8170'
  for update;

  if v_shania_order_id is null then
    raise exception 'Order KS-260922-8170 tidak ditemukan.';
  end if;

  select o.client_id
    into v_nunik_order_client_id
  from public.orders o
  where o.order_number = 'KS-260923-6971';

  if v_nunik_order_client_id is null then
    raise exception 'Order KS-260923-6971 tidak ditemukan. Pemulihan dihentikan.';
  end if;

  if v_current_client_id <> v_nunik_order_client_id then
    raise exception
      'Guard gagal: kedua order tidak lagi menggunakan client_id yang sama. Tidak ada perubahan dilakukan.';
  end if;

  -- Jangan membuat duplikat Shania bila client identitas asli sudah ada.
  select c.id, c.client_code
    into v_new_client_id, v_new_client_code
  from public.clients c
  where lower(trim(c.email)) = lower('shania@gmail.com')
    and lower(trim(c.full_name)) = lower('Shania Rhiana Zafirah')
    and lower(trim(c.business_name)) = lower('D''Celup Chicken Xtra')
    and trim(c.whatsapp) = '083872100238'
  order by c.created_at asc
  limit 1;

  if v_new_client_id is null then
    insert into public.clients (
      full_name,
      business_name,
      whatsapp,
      email
    )
    values (
      'Shania Rhiana Zafirah',
      'D''Celup Chicken Xtra',
      '083872100238',
      'shania@gmail.com'
    )
    returning id, client_code
      into v_new_client_id, v_new_client_code;
  end if;

  if v_new_client_id = v_nunik_order_client_id then
    raise exception 'Guard gagal: client Shania sama dengan client Nunik. Tidak ada perubahan dilakukan.';
  end if;

  update public.orders
     set client_id = v_new_client_id,
         updated_at = now()
   where id = v_shania_order_id
     and order_number = 'KS-260922-8170'
     and client_id = v_nunik_order_client_id;

  if not found then
    raise exception 'Order Shania gagal dipindahkan. Transaksi dibatalkan.';
  end if;

  -- Final guard: order Nunik harus tetap pada client semula.
  if exists (
    select 1
    from public.orders
    where order_number = 'KS-260923-6971'
      and client_id <> v_nunik_order_client_id
  ) then
    raise exception 'Guard akhir gagal: order Nunik berubah. Transaksi dibatalkan.';
  end if;

  raise notice 'Pemulihan berhasil. Shania client_id=%, client_code=%',
    v_new_client_id, v_new_client_code;
end;
$$;

commit;

-- Verifikasi akhir.
select
    o.order_number,
    o.client_id,
    c.client_code,
    c.full_name,
    c.business_name,
    c.email,
    c.whatsapp,
    o.status,
    o.total_amount
from public.orders o
left join public.clients c on c.id = o.client_id
where o.order_number in (
    'KS-260922-8170',
    'KS-260923-6971'
)
order by o.order_number;
