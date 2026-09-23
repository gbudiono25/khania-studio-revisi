-- KHANIA STUDIO — STAGE 2C — SECURE FORM BRIEF DELIVERY
-- Creates one-time brief access tokens, admin preparation RPC, and public context RPC.
-- Safe to run after Stage 2A. Does not alter order/payment flow.

create extension if not exists pgcrypto;

alter table public.website_briefs
  add column if not exists brief_token_hash text,
  add column if not exists brief_token_expires_at timestamptz,
  add column if not exists brief_sent_at timestamptz,
  add column if not exists brief_accessed_at timestamptz;

create index if not exists website_briefs_token_hash_idx
  on public.website_briefs(brief_token_hash)
  where brief_token_hash is not null;

-- Admin prepares (or refreshes) the one-time brief link.
drop function if exists public.admin_prepare_brief(uuid,text,timestamptz);
create function public.admin_prepare_brief(
  p_order_id uuid,
  p_token_hash text,
  p_token_expires_at timestamptz
)
returns table (
  success boolean,
  brief_id uuid,
  order_id uuid,
  order_number text,
  client_code text,
  client_name text,
  business_name text,
  client_email text,
  package_name text,
  token_expires_at timestamptz,
  message text
)
language plpgsql
security definer
set search_path = public
as $$
declare
  v_order public.orders%rowtype;
  v_client public.clients%rowtype;
  v_package public.packages%rowtype;
  v_brief public.website_briefs%rowtype;
begin
  if not public.is_admin() then
    return query select false, null::uuid, p_order_id, null::text, null::text,
      null::text, null::text, null::text, null::text, p_token_expires_at,
      'Akses ditolak. Hanya admin yang dapat mengirim Form Brief.';
    return;
  end if;

  if p_token_hash is null or length(trim(p_token_hash)) < 32 then
    return query select false, null::uuid, p_order_id, null::text, null::text,
      null::text, null::text, null::text, null::text, p_token_expires_at,
      'Token brief tidak valid.';
    return;
  end if;

  select * into v_order
  from public.orders
  where id = p_order_id
  for update;

  if not found then
    return query select false, null::uuid, p_order_id, null::text, null::text,
      null::text, null::text, null::text, null::text, p_token_expires_at,
      'Order tidak ditemukan.';
    return;
  end if;

  if v_order.status <> 'payment_verified'::public.order_status then
    return query select false, null::uuid, p_order_id, v_order.order_number,
      null::text, null::text, null::text, null::text, null::text, p_token_expires_at,
      'Form Brief hanya dapat dikirim setelah pembayaran terverifikasi.';
    return;
  end if;

  select * into v_client from public.clients where id = v_order.client_id;
  select * into v_package from public.packages where id = v_order.package_id;

  if not found then
    return query select false, null::uuid, p_order_id, v_order.order_number,
      null::text, null::text, null::text, null::text, null::text, p_token_expires_at,
      'Data paket tidak ditemukan.';
    return;
  end if;

  select * into v_brief
  from public.website_briefs
  where order_id = p_order_id
  order by created_at desc
  limit 1
  for update;

  if found then
    update public.website_briefs
       set brief_token_hash = p_token_hash,
           brief_token_expires_at = p_token_expires_at,
           updated_at = now()
     where id = v_brief.id
     returning * into v_brief;
  else
    insert into public.website_briefs (
      order_id, client_id, client_code, version, brief_data, status,
      brief_token_hash, brief_token_expires_at
    ) values (
      v_order.id, v_order.client_id, v_client.client_code, 1, '{}'::jsonb, 'DRAFT',
      p_token_hash, p_token_expires_at
    )
    returning * into v_brief;
  end if;

  return query
  select true,
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
end;
$$;

revoke all on function public.admin_prepare_brief(uuid,text,timestamptz) from public;
grant execute on function public.admin_prepare_brief(uuid,text,timestamptz) to authenticated;

-- Mark the brief as sent only after the SMTP email succeeds.
drop function if exists public.admin_mark_brief_sent(uuid);
create function public.admin_mark_brief_sent(p_brief_id uuid)
returns table(success boolean, brief_id uuid, sent_at timestamptz, message text)
language plpgsql
security definer
set search_path = public
as $$
begin
  if not public.is_admin() then
    return query select false, p_brief_id, null::timestamptz,
      'Akses ditolak. Hanya admin yang dapat memperbarui status pengiriman.';
    return;
  end if;

  update public.website_briefs
     set brief_sent_at = now(),
         status = case when status = 'DRAFT' then 'DRAFT' else status end,
         updated_at = now()
   where id = p_brief_id;

  if not found then
    return query select false, p_brief_id, null::timestamptz,
      'Form Brief tidak ditemukan.';
    return;
  end if;

  return query select true, p_brief_id, now(), 'Form Brief berhasil ditandai sebagai terkirim.';
end;
$$;

revoke all on function public.admin_mark_brief_sent(uuid) from public;
grant execute on function public.admin_mark_brief_sent(uuid) to authenticated;

-- Public/read-only RPC used by the form page. It returns only non-sensitive order/client context.
drop function if exists public.get_brief_context(text);
create function public.get_brief_context(p_token_hash text)
returns table (
  success boolean,
  brief_id uuid,
  order_id uuid,
  order_number text,
  client_code text,
  package_code text,
  package_name text,
  client_name text,
  business_name text,
  email text,
  whatsapp text,
  status text,
  token_expires_at timestamptz,
  message text
)
language plpgsql
security definer
set search_path = public
as $$
declare
  v_brief public.website_briefs%rowtype;
  v_order public.orders%rowtype;
  v_client public.clients%rowtype;
  v_package public.packages%rowtype;
begin
  select * into v_brief
  from public.website_briefs
  where brief_token_hash = p_token_hash
    and brief_token_expires_at is not null
    and brief_token_expires_at > now()
  order by created_at desc
  limit 1;

  if not found then
    return query select false, null::uuid, null::uuid, null::text, null::text,
      null::text, null::text, null::text, null::text, null::text, null::text,
      null::text, null::timestamptz,
      'Link Client Brief tidak valid, sudah kedaluwarsa, atau sudah tidak tersedia.';
    return;
  end if;

  if v_brief.status <> 'DRAFT' then
    return query select false, v_brief.id, v_brief.order_id, null::text, null::text,
      null::text, null::text, null::text, null::text, null::text, null::text,
      v_brief.status, v_brief.brief_token_expires_at,
      'Client Brief ini sudah dikirim sebelumnya dan tidak dapat diisi ulang melalui link lama.';
    return;
  end if;

  select * into v_order from public.orders where id = v_brief.order_id;
  select * into v_client from public.clients where id = v_brief.client_id;
  select * into v_package from public.packages where id = v_order.package_id;

  if v_order.status <> 'payment_verified'::public.order_status then
    return query select false, v_brief.id, v_order.id, v_order.order_number,
      v_client.client_code, v_package.code, v_package.name, v_client.full_name,
      v_client.business_name, v_client.email, v_client.whatsapp, v_brief.status,
      v_brief.brief_token_expires_at,
      'Pembayaran order ini belum berstatus terverifikasi.';
    return;
  end if;

  update public.website_briefs
     set brief_accessed_at = now(), updated_at = now()
   where id = v_brief.id;

  return query
  select true, v_brief.id, v_order.id, v_order.order_number,
    v_client.client_code, v_package.code, v_package.name, v_client.full_name,
    v_client.business_name, v_client.email, v_client.whatsapp, v_brief.status,
    v_brief.brief_token_expires_at,
    'OK';
end;
$$;

revoke all on function public.get_brief_context(text) from public;
grant execute on function public.get_brief_context(text) to anon, authenticated;

-- Verification queries.
select column_name, data_type
from information_schema.columns
where table_schema='public' and table_name='website_briefs'
  and column_name in ('brief_token_hash','brief_token_expires_at','brief_sent_at','brief_accessed_at')
order by column_name;

select n.nspname as schema_name, p.oid::regprocedure as function_signature,
       pg_get_function_result(p.oid) as return_type, p.prosecdef as security_definer
from pg_proc p join pg_namespace n on n.oid=p.pronamespace
where n.nspname='public'
  and p.proname in ('admin_prepare_brief','admin_mark_brief_sent','get_brief_context')
order by p.proname;
