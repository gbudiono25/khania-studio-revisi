-- KHANIA STUDIO — SUPABASE MIGRATION 3
-- Stage: Separate order creation from payment confirmation.
-- Run AFTER supabase-schema.sql and supabase-schema-migration-2.sql.
-- These SECURITY DEFINER RPCs let the public payment form identify an order
-- by order number + customer email and submit a payment confirmation without
-- exposing the orders/payments tables to anonymous SELECT/UPDATE access.

create or replace function public.get_order_for_payment(
    p_order_number text,
    p_email text
)
returns table (
    id uuid,
    order_number text,
    package text,
    total_amount bigint,
    customer_name text,
    business_name text,
    email text,
    status text
)
language sql
security definer
set search_path = public
as $$
    select
        o.id,
        o.order_number,
        p.name as package,
        o.total_amount,
        c.full_name as customer_name,
        c.business_name,
        c.email,
        o.status::text as status
    from public.orders o
    join public.clients c on c.id = o.client_id
    join public.packages p on p.id = o.package_id
    where o.order_number = p_order_number
      and lower(c.email) = lower(p_email)
    limit 1;
$$;

grant execute on function public.get_order_for_payment(text, text) to anon;
grant execute on function public.get_order_for_payment(text, text) to authenticated;

create or replace function public.submit_payment_confirmation(
    p_order_number text,
    p_email text,
    p_payment_date date,
    p_amount bigint,
    p_proof_path text
)
returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_order record;
    v_payment_id uuid;
begin
    select o.id, o.total_amount, o.status, c.email
      into v_order
      from public.orders o
      join public.clients c on c.id = o.client_id
     where o.order_number = p_order_number
       and lower(c.email) = lower(p_email)
     limit 1;

    if not found then
        return jsonb_build_object('success', false, 'message', 'Order tidak ditemukan.');
    end if;

    if v_order.status::text <> 'pending_payment' then
        return jsonb_build_object('success', false, 'message', 'Order tidak berada pada status menunggu pembayaran.');
    end if;

    if p_amount <> v_order.total_amount then
        return jsonb_build_object('success', false, 'message', 'Jumlah pembayaran tidak sesuai dengan total order.');
    end if;

    if exists (
        select 1 from public.payments py
         where py.order_id = v_order.id
           and py.status in ('pending','verified')
    ) then
        return jsonb_build_object('success', false, 'message', 'Konfirmasi pembayaran untuk order ini sudah pernah dikirim.');
    end if;

    insert into public.payments(order_id, payment_date, amount, proof_path, status)
    values(v_order.id, p_payment_date, p_amount, p_proof_path, 'pending')
    returning id into v_payment_id;

    update public.orders
       set status = 'payment_received', updated_at = now()
     where id = v_order.id;

    return jsonb_build_object(
        'success', true,
        'payment_id', v_payment_id,
        'order_id', v_order.id
    );
end;
$$;

grant execute on function public.submit_payment_confirmation(text, text, date, bigint, text) to anon;
grant execute on function public.submit_payment_confirmation(text, text, date, bigint, text) to authenticated;
