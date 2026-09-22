<?php
declare(strict_types=1);

/*
 * KHANIA STUDIO — public Supabase browser configuration
 * Exposes ONLY the Supabase Project URL and anon/publishable key.
 * NEVER expose service_role or other secret keys here.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/lib/env.php';
loadEnv(dirname(__DIR__) . '/.env');

$url = getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? '');
$anon = getenv('SUPABASE_ANON_KEY') ?: ($_ENV['SUPABASE_ANON_KEY'] ?? '');

if (!$url || !$anon) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Supabase browser configuration is not available.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'url' => $url,
    'anonKey' => $anon
], JSON_UNESCAPED_SLASHES);
