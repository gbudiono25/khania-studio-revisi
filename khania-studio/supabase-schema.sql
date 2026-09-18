-- KHANIA STUDIO — SUPABASE FOUNDATION
-- Stage: database/auth foundation
-- Jalankan di Supabase SQL Editor setelah memastikan project yang benar.
-- Jangan menaruh service_role key di frontend.

create extension if not exists pgcrypto;

-- =========================
-- ENUMS
-- =========================
do $$ begin
  create type public.user_role as enum ('admin','client');
exception when duplicate_object then null;
end $$;

do $$ begin
  create type public.order_status as enum (
    'pending_payment',
    'payment_received',
    'payment_verified',
    'brief_sent',
    'brief_received',
    'in_progress',
    'revision',
    'completed',
    'cancelled'
  );
exception when duplicate_object then null;
end $$;

do $$ begin
  create type public.payment_status as enum ('pending','verified','rejected');
exception when duplicate_object then null;
end $$;

do $$ begin
  create type public.voucher_type as enum ('amount','percent');
exception when duplicate_object then null;
end $$;

-- =========================
-- PROFILES
-- Supabase Auth user -> one profile.
-- =========================
create table if not exists public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  role public.user_role not null default 'client',
  full_name text not null default '',
  business_name text,
  whatsapp text,
  address text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- =========================
-- CLIENTS
-- Separate business/customer record, useful even before login.
-- =========================
create table if not exists public.clients (
  id uuid primary key default gen_random_uuid(),
  auth_user_id uuid unique references auth.users(id) on delete set null,
  full_name text not null,
  business_name text not null,
  whatsapp text not null,
  email text not null,
  domain text,
  address text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- =========================
-- PACKAGES
-- Prices are server-side data, not trusted from hidden HTML fields.
-- =========================
create table if not exists public.packages (
  id uuid primary key default gen_random_uuid(),
  code text unique not null,
  name text unique not null,
  base_price bigint not null check (base_price >= 0),
  setup_fee bigint not null check (setup_fee >= 0),
  active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

insert into public.packages (code,name,base_price,setup_fee)
values
 ('starter','Starter',400000,100000),
 ('bronze','Bronze',580000,200000),
 ('silver','Silver',1100000,400000),
 ('gold','Gold',1750000,600000)
on conflict (code) do update set
  name=excluded.name,
  base_price=excluded.base_price,
  setup_fee=excluded.setup_fee,
  updated_at=now();

-- =========================
-- VOUCHERS
-- =========================
create table if not exists public.vouchers (
  id uuid primary key default gen_random_uuid(),
  code text unique not null,
  discount_type public.voucher_type not null default 'amount',
  discount_value numeric(12,2) not null check (discount_value >= 0),
  valid_from date,
  valid_until date,
  max_uses integer check (max_uses is null or max_uses >= 0),
  used_count integer not null default 0 check (used_count >= 0),
  active boolean not null default true,
  created_by uuid references auth.users(id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint voucher_dates_valid check (
    valid_until is null or valid_from is null or valid_until >= valid_from
  )
);

-- =========================
-- ORDERS
-- =========================
create table if not exists public.orders (
  id uuid primary key default gen_random_uuid(),
  order_number text unique not null,
  client_id uuid not null references public.clients(id) on delete restrict,
  package_id uuid not null references public.packages(id) on delete restrict,
  order_date date not null default current_date,
  due_date timestamptz not null,
  base_price bigint not null check (base_price >= 0),
  setup_fee bigint not null check (setup_fee >= 0),
  voucher_code text,
  voucher_discount bigint not null default 0 check (voucher_discount >= 0),
  total_amount bigint not null check (total_amount >= 0),
  status public.order_status not null default 'pending_payment',
  notes text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- =========================
-- PAYMENTS
-- =========================
create table if not exists public.payments (
  id uuid primary key default gen_random_uuid(),
  order_id uuid not null references public.orders(id) on delete cascade,
  payment_date date not null,
  amount bigint not null check (amount >= 0),
  proof_path text,
  status public.payment_status not null default 'pending',
  verified_by uuid references auth.users(id) on delete set null,
  verified_at timestamptz,
  admin_note text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- =========================
-- WEBSITE BRIEFS
-- One current brief per order, with revision history later if needed.
-- =========================
create table if not exists public.website_briefs (
  id uuid primary key default gen_random_uuid(),
  order_id uuid unique not null references public.orders(id) on delete cascade,
  client_id uuid not null references public.clients(id) on delete restrict,
  data jsonb not null default '{}'::jsonb,
  submitted_at timestamptz,
  status text not null default 'draft'
    check (status in ('draft','submitted','reviewed','in_progress','completed')),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- =========================
-- MESSAGES
-- Text-only client/admin communication.
-- Attachments can be handled by email initially.
-- =========================
create table if not exists public.messages (
  id uuid primary key default gen_random_uuid(),
  client_id uuid not null references public.clients(id) on delete cascade,
  order_id uuid references public.orders(id) on delete cascade,
  sender_user_id uuid not null references auth.users(id) on delete restrict,
  recipient_user_id uuid references auth.users(id) on delete set null,
  subject text,
  body text not null,
  is_announcement boolean not null default false,
  read_at timestamptz,
  created_at timestamptz not null default now()
);

-- =========================
-- ANNOUNCEMENTS
-- Global or targeted information.
-- =========================
create table if not exists public.announcements (
  id uuid primary key default gen_random_uuid(),
  title text not null,
  body text not null,
  published boolean not null default false,
  published_at timestamptz,
  created_by uuid references auth.users(id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.announcement_recipients (
  announcement_id uuid not null references public.announcements(id) on delete cascade,
  client_id uuid not null references public.clients(id) on delete cascade,
  read_at timestamptz,
  primary key (announcement_id, client_id)
);

-- =========================
-- AUDIT LOG
-- Useful for payment/order/admin actions.
-- =========================
create table if not exists public.audit_logs (
  id uuid primary key default gen_random_uuid(),
  actor_user_id uuid references auth.users(id) on delete set null,
  action text not null,
  entity_type text,
  entity_id uuid,
  details jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

-- =========================
-- HELPERS
-- =========================
create or replace function public.is_admin()
returns boolean
language sql
stable
security definer
set search_path = public
as $$
  select exists (
    select 1 from public.profiles
    where id = auth.uid() and role = 'admin'
  );
$$;

create or replace function public.my_client_id()
returns uuid
language sql
stable
security definer
set search_path = public
as $$
  select id from public.clients where auth_user_id = auth.uid() limit 1;
$$;

-- =========================
-- RLS
-- =========================
alter table public.profiles enable row level security;
alter table public.clients enable row level security;
alter table public.packages enable row level security;
alter table public.vouchers enable row level security;
alter table public.orders enable row level security;
alter table public.payments enable row level security;
alter table public.website_briefs enable row level security;
alter table public.messages enable row level security;
alter table public.announcements enable row level security;
alter table public.announcement_recipients enable row level security;
alter table public.audit_logs enable row level security;

-- Profiles
drop policy if exists profiles_self_select on public.profiles;
create policy profiles_self_select on public.profiles
for select using (id = auth.uid() or public.is_admin());

drop policy if exists profiles_self_update on public.profiles;
create policy profiles_self_update on public.profiles
for update using (id = auth.uid() or public.is_admin());

drop policy if exists profiles_admin_all on public.profiles;
create policy profiles_admin_all on public.profiles
for all using (public.is_admin()) with check (public.is_admin());

-- Clients
drop policy if exists clients_self_select on public.clients;
create policy clients_self_select on public.clients
for select using (auth_user_id = auth.uid() or public.is_admin());

drop policy if exists clients_self_update on public.clients;
create policy clients_self_update on public.clients
for update using (auth_user_id = auth.uid() or public.is_admin());

drop policy if exists clients_admin_insert on public.clients;
create policy clients_admin_insert on public.clients
for insert with check (public.is_admin());

drop policy if exists clients_admin_delete on public.clients;
create policy clients_admin_delete on public.clients
for delete using (public.is_admin());

-- Packages: public can read active package prices.
drop policy if exists packages_public_read on public.packages;
create policy packages_public_read on public.packages
for select using (active = true or public.is_admin());

drop policy if exists packages_admin_write on public.packages;
create policy packages_admin_write on public.packages
for all using (public.is_admin()) with check (public.is_admin());

-- Vouchers: public should NOT read the entire voucher table.
-- Validation will be performed by a controlled server-side function/API in the next stage.
drop policy if exists vouchers_admin_all on public.vouchers;
create policy vouchers_admin_all on public.vouchers
for all using (public.is_admin()) with check (public.is_admin());

-- Orders
drop policy if exists orders_client_select on public.orders;
create policy orders_client_select on public.orders
for select using (client_id = public.my_client_id() or public.is_admin());

drop policy if exists orders_admin_all on public.orders;
create policy orders_admin_all on public.orders
for all using (public.is_admin()) with check (public.is_admin());

-- Payments
drop policy if exists payments_client_select on public.payments;
create policy payments_client_select on public.payments
for select using (
  exists (
    select 1 from public.orders o
    where o.id = payments.order_id
      and o.client_id = public.my_client_id()
  ) or public.is_admin()
);

drop policy if exists payments_admin_all on public.payments;
create policy payments_admin_all on public.payments
for all using (public.is_admin()) with check (public.is_admin());

-- Briefs
drop policy if exists briefs_client_all on public.website_briefs;
create policy briefs_client_all on public.website_briefs
for all using (client_id = public.my_client_id() or public.is_admin())
with check (client_id = public.my_client_id() or public.is_admin());

-- Messages
drop policy if exists messages_client_read_write on public.messages;
create policy messages_client_read_write on public.messages
for select using (client_id = public.my_client_id() or public.is_admin());

drop policy if exists messages_client_insert on public.messages;
create policy messages_client_insert on public.messages
for insert with check (
  client_id = public.my_client_id()
  and sender_user_id = auth.uid()
  and is_announcement = false
);

drop policy if exists messages_admin_all on public.messages;
create policy messages_admin_all on public.messages
for all using (public.is_admin()) with check (public.is_admin());

-- Announcements
drop policy if exists announcements_client_read on public.announcements;
create policy announcements_client_read on public.announcements
for select using (published = true or public.is_admin());

drop policy if exists announcements_admin_all on public.announcements;
create policy announcements_admin_all on public.announcements
for all using (public.is_admin()) with check (public.is_admin());

drop policy if exists announcement_recipients_client_read on public.announcement_recipients;
create policy announcement_recipients_client_read on public.announcement_recipients
for select using (client_id = public.my_client_id() or public.is_admin());

drop policy if exists announcement_recipients_admin_all on public.announcement_recipients;
create policy announcement_recipients_admin_all on public.announcement_recipients
for all using (public.is_admin()) with check (public.is_admin());

-- Audit logs
drop policy if exists audit_admin_read on public.audit_logs;
create policy audit_admin_read on public.audit_logs
for select using (public.is_admin());

drop policy if exists audit_admin_insert on public.audit_logs;
create policy audit_admin_insert on public.audit_logs
for insert with check (public.is_admin());

-- =========================
-- UPDATED_AT TRIGGER
-- =========================
create or replace function public.set_updated_at()
returns trigger language plpgsql as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

do $$ begin
  create trigger profiles_updated_at before update on public.profiles
  for each row execute function public.set_updated_at();
exception when duplicate_object then null;
end $$;

do $$ begin
  create trigger clients_updated_at before update on public.clients
  for each row execute function public.set_updated_at();
exception when duplicate_object then null;
end $$;

do $$ begin
  create trigger packages_updated_at before update on public.packages
  for each row execute function public.set_updated_at();
exception when duplicate_object then null;
end $$;

do $$ begin
  create trigger vouchers_updated_at before update on public.vouchers
  for each row execute function public.set_updated_at();
exception when duplicate_object then null;
end $$;

do $$ begin
  create trigger orders_updated_at before update on public.orders
  for each row execute function public.set_updated_at();
exception when duplicate_object then null;
end $$;

do $$ begin
  create trigger payments_updated_at before update on public.payments
  for each row execute function public.set_updated_at();
exception when duplicate_object then null;
end $$;

do $$ begin
  create trigger website_briefs_updated_at before update on public.website_briefs
  for each row execute function public.set_updated_at();
exception when duplicate_object then null;
end $$;

do $$ begin
  create trigger announcements_updated_at before update on public.announcements
  for each row execute function public.set_updated_at();
exception when duplicate_object then null;
end $$;

-- =========================
-- AUTH PROFILE CREATION
-- Google/email signup both arrive through auth.users.
-- =========================
create or replace function public.handle_new_user()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
begin
  insert into public.profiles (id, full_name)
  values (
    new.id,
    coalesce(new.raw_user_meta_data ->> 'full_name',
             new.raw_user_meta_data ->> 'name',
             '')
  )
  on conflict (id) do nothing;
  return new;
end;
$$;

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
after insert on auth.users
for each row execute function public.handle_new_user();

-- =========================
-- STORAGE NOTES
-- Create these private buckets in Supabase Storage:
--   order-proofs
--   client-files
-- Policies should allow:
--   admin: full access
--   client: only their own files
-- This is intentionally kept out of this first migration because
-- path conventions will be finalized with the Client Area implementation.
-- =========================
