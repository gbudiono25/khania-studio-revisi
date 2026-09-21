-- KHANIA STUDIO — SUPABASE STAGE 2A
-- Form Brief V3 Integrated — database architecture
--
-- Tujuan:
-- 1. Memperkuat relasi Client -> Project Code -> Order -> Website Brief.
-- 2. Meng-upgrade website_briefs yang sudah ada tanpa menghapus data lama.
-- 3. Menambahkan brief_scope_reviews untuk review internal Khania Studio.
-- 4. Menyiapkan status proses Brief yang akan dipakai Client Area/Admin.
--
-- CATATAN:
-- - Migration ini TIDAK mengubah alur order/payment yang sudah diuji.
-- - Jalankan setelah schema Stage 1/2/3 yang sekarang sudah terpasang.
-- - Jangan masukkan service_role/secret key ke SQL ini.
-- - Sebelum menjalankan di production, backup database/project.

create extension if not exists pgcrypto;

-- =========================================================
-- 1. CLIENT CODE / PROJECT CODE
-- Format: NNN-PP-MM-YYYY
-- Contoh: 001-ST-10-2026
-- =========================================================

alter table public.clients
  add column if not exists client_code text;

create unique index if not exists clients_client_code_unique_idx
  on public.clients(client_code)
  where client_code is not null;

create sequence if not exists public.client_project_seq
  start with 1
  increment by 1
  minvalue 1;

-- Package code yang tampil di Client/Project Code.
create or replace function public.package_project_code(p_package_id uuid)
returns text
language sql
stable
security definer
set search_path = public
as $$
  select case lower(code)
    when 'starter' then 'ST'
    when 'bronze' then 'BR'
    when 'silver' then 'SV'
    when 'gold' then 'GD'
    else upper(left(code, 2))
  end
  from public.packages
  where id = p_package_id
  limit 1;
$$;

-- Generate Kode Klien/Project saat order pertama client tersebut dibuat.
-- Kode tidak berubah pada order berikutnya dari client yang sama.
create or replace function public.ensure_client_project_code()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
declare
  v_code text;
  v_seq bigint;
  v_package_code text;
  v_month text;
  v_year text;
begin
  select client_code
    into v_code
  from public.clients
  where id = new.client_id
  for update;

  if v_code is null then
    v_seq := nextval('public.client_project_seq');

    v_package_code := coalesce(
      public.package_project_code(new.package_id),
      'XX'
    );

    v_month := to_char(coalesce(new.order_date, current_date), 'MM');
    v_year  := to_char(coalesce(new.order_date, current_date), 'YYYY');

    v_code := lpad(v_seq::text, 3, '0')
      || '-' || v_package_code
      || '-' || v_month
      || '-' || v_year;

    update public.clients
       set client_code = v_code,
           updated_at = now()
     where id = new.client_id;
  end if;

  return new;
end;
$$;

drop trigger if exists orders_ensure_client_project_code on public.orders;

create trigger orders_ensure_client_project_code
before insert on public.orders
for each row
execute function public.ensure_client_project_code();

-- =========================================================
-- 2. UPGRADE EXISTING WEBSITE_BRIEFS
-- Existing Stage 1 already has public.website_briefs.
-- We alter it instead of dropping/recreating it.
-- =========================================================

alter table public.website_briefs
  add column if not exists client_code text,
  add column if not exists version integer not null default 1;

-- Rename old JSON column only if it still exists as "data".
do $$
begin
  if exists (
    select 1
    from information_schema.columns
    where table_schema = 'public'
      and table_name = 'website_briefs'
      and column_name = 'data'
  )
  and not exists (
    select 1
    from information_schema.columns
    where table_schema = 'public'
      and table_name = 'website_briefs'
      and column_name = 'brief_data'
  ) then
    alter table public.website_briefs
      rename column data to brief_data;
  end if;
end $$;

-- Safety: create brief_data if a non-standard previous schema did not have it.
alter table public.website_briefs
  add column if not exists brief_data jsonb not null default '{}'::jsonb;

-- Populate client_code from the linked client.
update public.website_briefs wb
set client_code = c.client_code
from public.clients c
where wb.client_id = c.id
  and wb.client_code is null;

-- Existing records may have no client code because they predate this migration.
-- Keep them usable; future orders will receive a code automatically.

-- Normalize old brief status values into the new Stage 2A lifecycle.
update public.website_briefs
set status = case lower(status)
  when 'draft' then 'DRAFT'
  when 'submitted' then 'SUBMITTED'
  when 'reviewed' then 'UNDER_REVIEW'
  when 'in_progress' then 'READY_FOR_PRODUCTION'
  when 'completed' then 'READY_FOR_PRODUCTION'
  else 'DRAFT'
end;

alter table public.website_briefs
  drop constraint if exists website_briefs_status_check;

alter table public.website_briefs
  add constraint website_briefs_status_check
  check (
    status in (
      'DRAFT',
      'SUBMITTED',
      'UNDER_REVIEW',
      'NEED_CLIENT_INFO',
      'SCOPE_CONFIRMED',
      'READY_FOR_PRODUCTION'
    )
  );

alter table public.website_briefs
  alter column status set default 'DRAFT';

-- Keep brief client_code synchronized from the linked client on insert.
create or replace function public.sync_website_brief_client_code()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
begin
  select client_code
    into new.client_code
  from public.clients
  where id = new.client_id;

  return new;
end;
$$;

drop trigger if exists website_briefs_sync_client_code on public.website_briefs;

create trigger website_briefs_sync_client_code
before insert or update of client_id on public.website_briefs
for each row
execute function public.sync_website_brief_client_code();

-- =========================================================
-- 3. INTERNAL SCOPE REVIEW
-- Client requests are recorded in the Brief.
-- Khania Studio decides internally whether each request is:
-- INCLUDED / ADDITIONAL_COST / NOT_INCLUDED / NEED_CLARIFICATION
-- =========================================================

create table if not exists public.brief_scope_reviews (
  id uuid primary key default gen_random_uuid(),

  brief_id uuid not null
    references public.website_briefs(id)
    on delete cascade,

  requested_item text not null,

  decision text not null default 'NEED_CLARIFICATION'
    check (
      decision in (
        'INCLUDED',
        'ADDITIONAL_COST',
        'NOT_INCLUDED',
        'NEED_CLARIFICATION'
      )
    ),

  additional_fee numeric(12,2) not null default 0
    check (additional_fee >= 0),

  admin_notes text,

  status text not null default 'OPEN'
    check (status in ('OPEN','RESOLVED','CANCELLED')),

  reviewed_by uuid
    references auth.users(id)
    on delete set null,

  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create index if not exists brief_scope_reviews_brief_id_idx
  on public.brief_scope_reviews(brief_id);

create index if not exists brief_scope_reviews_status_idx
  on public.brief_scope_reviews(status);

-- =========================================================
-- 4. INDEXES
-- =========================================================

create index if not exists website_briefs_client_id_idx
  on public.website_briefs(client_id);

create index if not exists website_briefs_order_id_idx
  on public.website_briefs(order_id);

create index if not exists website_briefs_status_idx
  on public.website_briefs(status);

create index if not exists orders_client_id_idx
  on public.orders(client_id);

-- =========================================================
-- 5. UPDATED_AT TRIGGER FOR SCOPE REVIEWS
-- Reuse Stage 1 helper if it already exists.
-- =========================================================

create or replace function public.set_updated_at()
returns trigger
language plpgsql
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

drop trigger if exists brief_scope_reviews_updated_at
  on public.brief_scope_reviews;

create trigger brief_scope_reviews_updated_at
before update on public.brief_scope_reviews
for each row
execute function public.set_updated_at();

-- =========================================================
-- 6. RLS
-- Client:
--   - can access only their own website brief
--   - cannot access internal scope review rows
-- Admin:
--   - full access to website briefs and scope reviews
-- =========================================================

alter table public.website_briefs enable row level security;
alter table public.brief_scope_reviews enable row level security;

drop policy if exists briefs_client_all on public.website_briefs;

create policy briefs_client_all
on public.website_briefs
for all
using (
  client_id = public.my_client_id()
  or public.is_admin()
)
with check (
  client_id = public.my_client_id()
  or public.is_admin()
);

drop policy if exists brief_scope_reviews_admin_all
  on public.brief_scope_reviews;

create policy brief_scope_reviews_admin_all
on public.brief_scope_reviews
for all
using (public.is_admin())
with check (public.is_admin());

-- Explicitly do NOT create a client policy on brief_scope_reviews.
-- Internal scope decisions must remain private to Khania Studio.

-- =========================================================
-- 7. DOCUMENTATION COMMENTS
-- =========================================================

comment on column public.clients.client_code is
  'Kode Klien/Project format NNN-PP-MM-YYYY, generated by system.';

comment on column public.website_briefs.client_code is
  'Snapshot of the linked clients.client_code for this brief.';

comment on column public.website_briefs.version is
  'Brief version number. Stage 2A starts at version 1.';

comment on column public.website_briefs.brief_data is
  'JSONB payload for integrated Form Brief V3 sections A-R.';

comment on table public.brief_scope_reviews is
  'Internal Khania Studio review of additional requests/scope items from a website brief.';

-- =========================================================
-- 8. POST-MIGRATION CHECK
-- =========================================================
-- Jalankan query berikut setelah migration untuk memeriksa:
--
-- select column_name, data_type
-- from information_schema.columns
-- where table_schema='public'
--   and table_name in ('clients','website_briefs','brief_scope_reviews')
-- order by table_name, ordinal_position;
--
-- select id, client_code, full_name, business_name
-- from public.clients
-- order by created_at desc;
--
-- select id, order_id, client_id, client_code, version, status
-- from public.website_briefs
-- order by created_at desc;
