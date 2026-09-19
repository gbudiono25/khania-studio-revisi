-- KHANIA STUDIO — SUPABASE MIGRATION 2
-- Stage: Backend INSERT Policies
-- Run AFTER supabase-schema.sql.
-- These policies allow the anon role (from PHP backend) to INSERT new records.
-- SELECT/UPDATE/DELETE remain restricted by the existing policies.
-- If you set SUPABASE_SERVICE_ROLE_KEY in .env, these policies are bypassed
-- (service_role key ignores RLS). They serve as fallback for anon key usage.

-- =========================
-- INSERT POLICIES (anons can create, clients can read their own)
-- =========================

-- Clients: allow creating new client records (for order & brief submission)
drop policy if exists clients_anon_insert on public.clients;
create policy clients_anon_insert on public.clients
for insert with check (true);

-- Orders: allow creating new orders (for order & brief submission)
drop policy if exists orders_anon_insert on public.orders;
create policy orders_anon_insert on public.orders
for insert with check (true);

-- Payments: allow creating new payment records (for order submission)
drop policy if exists payments_anon_insert on public.payments;
create policy payments_anon_insert on public.payments
for insert with check (true);

-- Briefs: allow submitting new website briefs
drop policy if exists briefs_anon_insert on public.website_briefs;
create policy briefs_anon_insert on public.website_briefs
for insert with check (true);

-- Messages: allow inserting new messages (for contact/communication)
drop policy if exists messages_anon_insert on public.messages;
create policy messages_anon_insert on public.messages
for insert with check (true);

-- Audit logs: allow inserting audit entries
drop policy if exists audit_anon_insert on public.audit_logs;
create policy audit_anon_insert on public.audit_logs
for insert with check (true);

-- =========================
-- RPC FUNCTION: Secure Voucher Validation
-- Allows the anon role to validate vouchers without direct table SELECT access.
-- This keeps voucher rules hidden from public clients.
-- =========================

create or replace function public.validate_voucher(p_code text, p_package_code text, p_amount bigint)
returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_record  record;
    v_discount bigint;
    v_today  date := current_date;
begin
    -- Look up voucher by code (case-insensitive)
    select * into v_record
    from public.vouchers
    where upper(code) = upper(p_code)
      and (active = true or active is null)
      and (valid_from is null or valid_from <= v_today)
      and (valid_until is null or valid_until >= v_today)
      and (max_uses is null or used_count < max_uses);

    if not found then
        return jsonb_build_object(
            'valid', false,
            'message', 'Kode voucher tidak valid atau tidak tersedia.'
        );
    end if;

    -- Calculate discount
    if (v_record.discount_type = 'percent') then
        v_discount := least(
            p_amount,
            round(p_amount * least(100, v_record.discount_value)::numeric / 100)::bigint
        );
    else
        v_discount := least(p_amount, v_record.discount_value::bigint);
    end if;

    return jsonb_build_object(
        'valid', true,
        'code', v_record.code,
        'discount_type', v_record.discount_type,
        'discount_value', v_record.discount_value,
        'discount_amount', v_discount,
        'message', 'Voucher berhasil digunakan.'
    );
end;
$$;

-- Grant anon role access to call this RPC function
grant execute on function public.validate_voucher(text, text, bigint) to anon;
-- If using authenticated roles:
grant execute on function public.validate_voucher(text, text, bigint) to authenticated;

-- =========================
-- RPC FUNCTION: Get active packages (safe for public read)
-- =========================

create or replace function public.get_active_packages()
returns table (
    id          uuid,
    code        text,
    name        text,
    base_price  bigint,
    setup_fee   bigint
)
language plpgsql
security definer
set search_path = public
as $$
begin
    return query
    select p.id, p.code, p.name, p.base_price, p.setup_fee
    from public.packages p
    where p.active = true
    order by p.name;
end;
$$;

grant execute on function public.get_active_packages() to anon;
grant execute on function public.get_active_packages() to authenticated;

-- =========================
-- RPC FUNCTION: Get single package by code (safe for public read)
-- =========================

create or replace function public.get_package_by_code(p_code text)
returns table (
    id          uuid,
    code        text,
    name        text,
    base_price  bigint,
    setup_fee   bigint
)
language plpgsql
security definer
set search_path = public
as $$
begin
    return query
    select p.id, p.code, p.name, p.base_price, p.setup_fee
    from public.packages p
    where p.code = p_code
      and p.active = true;
end;
$$;

grant execute on function public.get_package_by_code(text) to anon;
grant execute on function public.get_package_by_code(text) to authenticated;

-- =========================
-- ANNOUNCEMENTS: allow public READ of published announcements
-- =========================
drop policy if exists announcements_public_read on public.announcements;
create policy announcements_public_read on public.announcements
for select using (published = true);
