<?php

/**
 * KHANIA STUDIO — Packages List API
 * Returns active package list from Supabase as JSON.
 *
 * Endpoint: api/packages.php
 * Method: GET
 */

require_once __DIR__ . '/../lib/SupabaseClient.php';

use KhaniaStudio\SupabaseClient;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$supabase = new SupabaseClient();

if (!$supabase->isConfigured()) {
    // Fallback: local packages data matching the spec
    $packages = [
        ['code' => 'starter', 'name' => 'Starter',      'base_price' => 400000,  'setup_fee' => 100000],
        ['code' => 'bronze',  'name' => 'Bronze',       'base_price' => 580000,  'setup_fee' => 200000],
        ['code' => 'silver',  'name' => 'Silver',       'base_price' => 1100000, 'setup_fee' => 400000],
        ['code' => 'gold',    'name' => 'Gold',         'base_price' => 1750000, 'setup_fee' => 600000],
    ];
    echo json_encode($packages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Try RPC function first (most secure, doesn't require direct table SELECT)
$packages = $supabase->rpcPost('get_active_packages', []);
if (!empty($packages)) {
    http_response_code(200);
    echo json_encode($packages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Fallback: direct SELECT (works if anon has public_read policy or service_role is used)
$packages = $supabase->select('packages', 'id,code,name,base_price,setup_fee', ['active' => true]);
if (empty($packages)) {
    $packages = $supabase->select('packages', 'id,code,name,base_price,setup_fee');
}

http_response_code(200);
echo json_encode($packages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
