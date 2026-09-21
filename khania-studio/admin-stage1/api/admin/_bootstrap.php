<?php
session_name('khania_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require_once dirname(__DIR__, 2) . '/lib/env.php';
loadEnv(dirname(__DIR__, 2) . '/.env');

function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function supabase_config(): array {
    $url = rtrim((string)getenv('SUPABASE_URL'), '/');
    $key = (string)getenv('SUPABASE_ANON_KEY');
    if ($url === '' || $key === '') {
        json_response(['ok' => false, 'message' => 'Konfigurasi Supabase belum tersedia di server.'], 500);
    }
    return [$url, $key];
}

function supabase_request(string $method, string $path, ?array $body = null, ?string $accessToken = null): array {
    [$base, $anon] = supabase_config();
    $url = $base . $path;
    $headers = [
        'apikey: ' . $anon,
        'Authorization: Bearer ' . ($accessToken ?: $anon),
        'Accept: application/json',
        'Content-Type: application/json',
    ];
    if ($body !== null) {
        $headers[] = 'Prefer: return=representation';
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($raw === false || $error !== '') {
        return ['status' => 0, 'body' => ['message' => $error ?: 'cURL error']];
    }
    $decoded = json_decode($raw, true);
    return ['status' => $status, 'body' => $decoded ?? $raw];
}

function require_admin(): void {
    if (empty($_SESSION['sb_access_token'])) {
        json_response(['ok' => false, 'authenticated' => false, 'message' => 'Sesi admin tidak aktif.'], 401);
    }
}

function current_access_token(): string {
    require_admin();
    return (string)$_SESSION['sb_access_token'];
}

function refresh_access_token(): bool {
    if (empty($_SESSION['sb_refresh_token'])) return false;
    [$base, $anon] = supabase_config();
    $url = $base . '/auth/v1/token?grant_type=refresh_token';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $anon,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode(['refresh_token' => $_SESSION['sb_refresh_token']]),
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($raw ?: '', true);
    if ($status >= 200 && $status < 300 && !empty($data['access_token'])) {
        $_SESSION['sb_access_token'] = $data['access_token'];
        if (!empty($data['refresh_token'])) $_SESSION['sb_refresh_token'] = $data['refresh_token'];
        $_SESSION['sb_expires_at'] = time() + (int)($data['expires_in'] ?? 3600);
        return true;
    }
    return false;
}
