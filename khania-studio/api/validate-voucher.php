<?php

/**
 * KHANIA STUDIO — Voucher Validation API
 * Validates a voucher code against Supabase and returns JSON.
 *
 * Endpoint: api/validate-voucher.php
 * Method: GET or POST
 * Params: code=<voucher_code>&package=<package_name>
 */

require_once __DIR__ . '/../lib/SupabaseClient.php';

use KhaniaStudio\SupabaseClient;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$code = strtoupper(trim($_GET['code'] ?? $_POST['code'] ?? ''));
$packageInput = strtolower(trim($_GET['package'] ?? $_POST['package'] ?? ''));

if ($code === '') {
    http_response_code(400);
    echo json_encode([
        'valid'   => false,
        'message' => 'Kode voucher wajib diisi.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($packageInput === '') {
    http_response_code(400);
    echo json_encode([
        'valid'   => false,
        'message' => 'Paket wajib ditentukan.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$supabase = new SupabaseClient();

if (!$supabase->isConfigured()) {
    // Fallback: read from local JSON for development/testing
    $voucherFile = __DIR__ . '/../data/vouchers.json';
    if (is_file($voucherFile)) {
        $list = json_decode((string) file_get_contents($voucherFile), true);
        if (is_array($list)) {
            foreach ($list as $v) {
                if (strtoupper((string) ($v['code'] ?? '')) !== $code) {
                    continue;
                }
                if (($v['active'] ?? true) === false) {
                    continue;
                }
                $today = date('Y-m-d');
                if (!empty($v['start']) && $today < $v['start']) {
                    continue;
                }
                if (!empty($v['end']) && $today > $v['end']) {
                    continue;
                }
                $allowed = $v['packages'] ?? [];
                if (is_array($allowed) && count($allowed) && !in_array(ucfirst($packageInput), $allowed, true)) {
                    continue;
                }

                $response = [
                    'valid'           => true,
                    'code'            => $v['code'],
                    'discount_type'   => $v['type'] ?? 'amount',
                    'discount_value'  => (int) ($v['value'] ?? 0),
                    'message'         => '✓ Voucher berhasil digunakan.',
                ];
                echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
        }
    }

    http_response_code(404);
    echo json_encode([
        'valid'   => false,
        'message' => 'Kode voucher tidak valid atau tidak tersedia.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Primary path: validate via Supabase RPC function (secure, no direct table SELECT)
$rpcResult = $supabase->validateVoucherRpc($code, $packageInput, 0);

if ($rpcResult !== null && !empty($rpcResult)) {
    if (($rpcResult['valid'] ?? false) === true) {
        http_response_code(200);
        echo json_encode([
            'valid'           => true,
            'code'            => $rpcResult['code'] ?? $code,
            'discount_type'   => $rpcResult['discount_type'] ?? 'amount',
            'discount_value'  => (int) ($rpcResult['discount_value'] ?? 0),
            'discount_amount' => (int) ($rpcResult['discount_amount'] ?? 0),
            'message'         => $rpcResult['message'] ?? 'Voucher berhasil digunakan.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    } else {
        http_response_code(400);
        echo json_encode([
            'valid'   => false,
            'message' => $rpcResult['message'] ?? 'Kode voucher tidak valid.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Fallback: try direct SELECT (only works if service_role key is set or SELECT policy exists)
$voucher = $supabase->selectOne('vouchers', '*', ['code' => $code]);

if ($voucher !== null) {
    $today = date('Y-m-d');
    if (!empty($voucher['valid_from']) && $today < $voucher['valid_from']) {
        http_response_code(400);
        echo json_encode([
            'valid'   => false,
            'message' => 'Voucher belum berlaku pada tanggal ini.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!empty($voucher['valid_until']) && $today > $voucher['valid_until']) {
        http_response_code(400);
        echo json_encode([
            'valid'   => false,
            'message' => 'Kode voucher sudah tidak berlaku.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (isset($voucher['max_uses']) && $voucher['max_uses'] !== null && $voucher['used_count'] >= $voucher['max_uses']) {
        http_response_code(400);
        echo json_encode([
            'valid'   => false,
            'message' => 'Voucher sudah mencapai batas penggunaan.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        'valid'           => true,
        'code'            => $voucher['code'],
        'discount_type'   => $voucher['discount_type'] ?? 'amount',
        'discount_value'  => (int) ($voucher['discount_value'] ?? 0),
        'message'         => '✓ Voucher berhasil digunakan.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Final fallback: local JSON (only when Supabase has no voucher data or RPC unavailable)
$voucherFile = __DIR__ . '/../data/vouchers.json';
if (is_file($voucherFile)) {
    $list = json_decode((string) file_get_contents($voucherFile), true);
    if (is_array($list)) {
        foreach ($list as $v) {
            if (strtoupper((string) ($v['code'] ?? '')) !== $code) {
                continue;
            }
            if (($v['active'] ?? true) === false) {
                continue;
            }
            $today = date('Y-m-d');
            if (!empty($v['start']) && $today < $v['start']) {
                continue;
            }
            if (!empty($v['end']) && $today > $v['end']) {
                continue;
            }
            $allowed = $v['packages'] ?? [];
            if (is_array($allowed) && count($allowed) && !in_array(ucfirst($packageInput), $allowed, true)) {
                continue;
            }

            http_response_code(200);
            echo json_encode([
                'valid'           => true,
                'code'            => $v['code'],
                'discount_type'   => $v['type'] ?? 'amount',
                'discount_value'  => (int) ($v['value'] ?? 0),
                'message'         => '✓ Voucher berhasil digunakan.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}

http_response_code(404);
echo json_encode([
    'valid'   => false,
    'message' => 'Kode voucher tidak valid atau tidak tersedia.',
], JSON_UNESCAPED_UNICODE);
