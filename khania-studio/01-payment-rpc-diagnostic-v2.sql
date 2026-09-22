-- KHANIA STUDIO — PAYMENT RPC DIAGNOSTIC V2
-- READ-ONLY. Tidak melakukan INSERT / UPDATE / DELETE.
-- Tujuan: mengisolasi pencarian order dan dua RPC payment dalam SATU result set.

WITH
target_order AS (
    SELECT
        o.order_number,
        o.status::text AS order_status,
        o.total_amount,
        o.client_id,
        c.email AS client_email
    FROM public.orders o
    LEFT JOIN public.clients c ON c.id = o.client_id
    WHERE o.order_number = 'KS-260922-85CA'
    LIMIT 1
),
rpc_info AS (
    SELECT
        p.proname AS function_name,
        pg_get_function_identity_arguments(p.oid) AS arguments,
        p.prosecdef AS security_definer,
        has_function_privilege(
            'anon',
            p.oid,
            'EXECUTE'
        ) AS anon_can_execute
    FROM pg_proc p
    JOIN pg_namespace n ON n.oid = p.pronamespace
    WHERE n.nspname = 'public'
      AND p.proname IN (
          'get_order_for_payment',
          'submit_payment_confirmation'
      )
),
checks AS (
    SELECT
        1 AS sort_order,
        'ORDER'::text AS check_type,
        COALESCE(
            (SELECT order_number || ' | status=' || order_status ||
                    ' | total=' || COALESCE(total_amount::text,'NULL') ||
                    ' | email=' || COALESCE(client_email,'NULL')
             FROM target_order),
            'NOT FOUND'
        ) AS result

    UNION ALL

    SELECT
        2,
        'RPC get_order_for_payment',
        COALESCE(
            (SELECT
                'FOUND | args=(' || arguments || ')' ||
                ' | security_definer=' || security_definer::text ||
                ' | anon_execute=' || anon_can_execute::text
             FROM rpc_info
             WHERE function_name = 'get_order_for_payment'
             LIMIT 1),
            'NOT FOUND'
        )

    UNION ALL

    SELECT
        3,
        'RPC submit_payment_confirmation',
        COALESCE(
            (SELECT
                'FOUND | args=(' || arguments || ')' ||
                ' | security_definer=' || security_definer::text ||
                ' | anon_execute=' || anon_can_execute::text
             FROM rpc_info
             WHERE function_name = 'submit_payment_confirmation'
             LIMIT 1),
            'NOT FOUND'
        )

    UNION ALL

    SELECT
        4,
        'COUNT get_order_for_payment',
        COUNT(*)::text
    FROM rpc_info
    WHERE function_name = 'get_order_for_payment'

    UNION ALL

    SELECT
        5,
        'COUNT submit_payment_confirmation',
        COUNT(*)::text
    FROM rpc_info
    WHERE function_name = 'submit_payment_confirmation'
)
SELECT check_type, result
FROM checks
ORDER BY sort_order;
