<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method tidak diizinkan.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim((string)($input['email'] ?? ''));
$password = (string)($input['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    json_response(['ok' => false, 'message' => 'Email dan password wajib diisi.'], 422);
}

[$base, $anon] = supabase_config();
$ch = curl_init($base . '/auth/v1/token?grant_type=password');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . $anon,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode(['email' => $email, 'password' => $password]),
    CURLOPT_TIMEOUT => 20,
]);
$raw = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$data = json_decode($raw ?: '', true);

if ($status < 200 || $status >= 300 || empty($data['access_token'])) {
    json_response(['ok' => false, 'message' => 'Login gagal. Periksa email/password admin.'], 401);
}

$access = $data['access_token'];
$profile = supabase_request('GET', '/rest/v1/profiles?select=id,role,full_name&id=eq.' . rawurlencode($data['user']['id'] ?? ''), null, $access);
// Retry without the accidental whitespace-safe query construction above.
if ($profile['status'] >= 400) {
    $profile = supabase_request('GET', '/rest/v1/profiles?select=id,role,full_name&id=eq.' . rawurlencode($data['user']['id'] ?? ''), null, $access);
}

$row = is_array($profile['body']) ? ($profile['body'][0] ?? null) : null;
if (!$row || ($row['role'] ?? '') !== 'admin') {
    session_unset();
    session_destroy();
    json_response(['ok' => false, 'message' => 'Akun berhasil login, tetapi belum memiliki role admin.'], 403);
}

session_regenerate_id(true);
$_SESSION['sb_access_token'] = $access;
$_SESSION['sb_refresh_token'] = (string)($data['refresh_token'] ?? '');
$_SESSION['sb_user_id'] = (string)($data['user']['id'] ?? '');
$_SESSION['admin_name'] = (string)($row['full_name'] ?? 'Admin');
$_SESSION['sb_expires_at'] = time() + (int)($data['expires_in'] ?? 3600);

json_response(['ok' => true, 'name' => $_SESSION['admin_name']]);
