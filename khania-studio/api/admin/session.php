<?php
require_once __DIR__ . '/_bootstrap.php';

if (empty($_SESSION['sb_access_token'])) {
    json_response(['ok' => true, 'authenticated' => false]);
}
if (!empty($_SESSION['sb_expires_at']) && time() >= (int)$_SESSION['sb_expires_at'] - 30) {
    if (!refresh_access_token()) {
        session_unset(); session_destroy();
        json_response(['ok' => true, 'authenticated' => false]);
    }
}
json_response([
    'ok' => true,
    'authenticated' => true,
    'name' => $_SESSION['admin_name'] ?? 'Admin',
]);
