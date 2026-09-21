<?php
require_once __DIR__ . '/_bootstrap.php';
require_admin();

if (!empty($_SESSION['sb_expires_at']) && time() >= (int)$_SESSION['sb_expires_at'] - 30) {
    refresh_access_token();
}
$token = current_access_token();

$select = 'id,order_number,order_date,due_date,base_price,setup_fee,voucher_code,voucher_discount,total_amount,status,created_at,updated_at,client_id,package_id,clients(id,client_code,full_name,business_name,whatsapp,email),packages(id,code,name)';
$path = '/rest/v1/orders?select=' . rawurlencode($select) . '&order=created_at.desc&limit=200';
$res = supabase_request('GET', $path, null, $token);

if ($res['status'] === 401 && refresh_access_token()) {
    $res = supabase_request('GET', $path, null, current_access_token());
}
if ($res['status'] < 200 || $res['status'] >= 300) {
    json_response(['ok' => false, 'message' => 'Data pesanan tidak dapat diambil dari Supabase.', 'detail' => $res['body']], 502);
}

$orders = is_array($res['body']) ? $res['body'] : [];
$counts = [
    'all' => count($orders),
    'pending_payment' => 0,
    'payment_received' => 0,
    'payment_verified' => 0,
    'brief_sent' => 0,
    'brief_received' => 0,
    'in_progress' => 0,
    'revision' => 0,
    'completed' => 0,
    'cancelled' => 0,
];
foreach ($orders as $o) {
    $s = $o['status'] ?? '';
    if (isset($counts[$s])) $counts[$s]++;
}

json_response(['ok' => true, 'orders' => $orders, 'counts' => $counts]);
