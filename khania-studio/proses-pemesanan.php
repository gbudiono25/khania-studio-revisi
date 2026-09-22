<?php
/**
 * KHANIA STUDIO — proses-pemesanan.php
 * Stage 1: create website order first; payment confirmation is handled separately.
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

require_once __DIR__ . '/config-pemesanan.php';
require_once __DIR__ . '/lib/SupabaseClient.php';
require_once __DIR__ . '/lib/PHPMailerLite.php';

use KhaniaStudio\SupabaseClient;
use PHPMailer\PHPMailer\PHPMailer;

$supabase = new SupabaseClient();
const MAX_FILE_SIZE = 10 * 1024 * 1024;

function fail($message, $status = 400) {
    http_response_code($status);
    $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pemesanan Website — Khania Studio</title><style>body{font:16px/1.6 Arial,sans-serif;background:#f1f5f9;color:#0f172a;margin:0}.wrap{max-width:700px;margin:10vh auto;padding:24px}.card{background:#fff;padding:28px;border-radius:16px;border:1px solid #dbe2ea}a{display:inline-block;background:#d4af37;color:#0f172a;padding:10px 16px;border-radius:9px;text-decoration:none;font-weight:700}</style></head><body><div class="wrap"><div class="card"><h1>Pemesanan Website</h1><p>'.$safe.'</p><a href="javascript:history.back()">Kembali ke formulir</a></div></div></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html#harga');
    exit;
}

function orderPost($key) { $value = $_POST[$key] ?? ''; return is_string($value) ? trim($value) : ''; }
function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function orderRupiah($n) { return 'Rp' . number_format((int)$n, 0, ',', '.'); }
function orderBaseUrl() { return 'https://khania-studio.com'; }

$packages = [
  'Starter' => ['code'=>'starter','base'=>400000,'setup'=>100000],
  'Bronze'  => ['code'=>'bronze','base'=>580000,'setup'=>200000],
  'Silver'  => ['code'=>'silver','base'=>1100000,'setup'=>400000],
  'Gold'    => ['code'=>'gold','base'=>1750000,'setup'=>600000],
];
$package = orderPost('package');
if (!isset($packages[$package])) fail('Paket pemesanan tidak valid. Silakan kembali ke halaman Layanan & Harga dan pilih paket yang tersedia.');

$name = orderPost('customerName');
$business = orderPost('businessName');
$wa = orderPost('whatsapp');
$email = orderPost('email');
$domain = orderPost('domain');
$voucherCode = strtoupper(orderPost('voucherCode'));
$base = $packages[$package]['base'];
$setup = $packages[$package]['setup'];
$subtotal = $base + $setup;
$voucherDiscount = 0;
$voucherUsed = '';

if ($voucherCode !== '' && $supabase->isConfigured()) {
    $result = $supabase->validateVoucher($voucherCode, $packages[$package]['code'], $subtotal);
    if ($result) {
        $voucherDiscount = (int)($result['discount_amount'] ?? 0);
        $voucherUsed = $voucherCode;
    }
}
if ($voucherCode !== '' && $voucherUsed === '' && !$supabase->isConfigured()) {
    $voucherFile = __DIR__ . '/data/vouchers.json';
    if (is_file($voucherFile)) {
        $list = json_decode((string)file_get_contents($voucherFile), true);
        if (is_array($list)) foreach ($list as $v) {
            if (strtoupper((string)($v['code'] ?? '')) !== $voucherCode || ($v['active'] ?? true) === false) continue;
            $today = date('Y-m-d');
            if (!empty($v['start']) && $today < $v['start']) continue;
            if (!empty($v['end']) && $today > $v['end']) continue;
            $allowed = $v['packages'] ?? [];
            if (is_array($allowed) && count($allowed) && !in_array($package, $allowed, true)) continue;
            if (($v['type'] ?? 'amount') === 'percent') $voucherDiscount = min($subtotal, (int)round($subtotal * min(100, max(0,(float)($v['value'] ?? 0))) / 100));
            else $voucherDiscount = min($subtotal, max(0,(int)($v['value'] ?? 0)));
            $voucherUsed = $voucherCode;
            break;
        }
    }
}
$total = max(0, $subtotal - $voucherDiscount);

$required = ['Nama Lengkap'=>$name,'Nama Usaha / Bisnis'=>$business,'Nomor WhatsApp'=>$wa,'Email'=>$email];
$missing=[]; foreach($required as $label=>$value) if($value==='') $missing[]=$label;
if ($missing) fail('Field wajib belum lengkap: ' . implode(', ', $missing) . '.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Alamat email tidak valid.');

$now = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
$orderDate = $now->format('Y-m-d');
$due = clone $now; $due->modify('+2 days'); $due->setTime(23,59,59);
$dueDate = $due->format('Y-m-d H:i:s');
$random = strtoupper(substr(bin2hex(random_bytes(4)),0,4));
$orderId = 'KS-' . $now->format('ymd') . '-' . $random;

$record = [
  'orderId'=>$orderId,'createdAt'=>$now->format(\DateTime::ATOM),'orderDate'=>$orderDate,'dueDate'=>$dueDate,
  'package'=>$package,'base'=>$base,'setup'=>$setup,'voucher'=>$voucherUsed,'discount'=>$voucherDiscount,'total'=>$total,
  'customerName'=>$name,'businessName'=>$business,'whatsapp'=>$wa,'email'=>$email,'domain'=>$domain,
  'status'=>'Menunggu Pembayaran'
];

$supabaseSaved = false;
$supabaseError = null;

if ($supabase->isConfigured()) {
    $packageCode = $packages[$package]['code'];
    $rpcResponse = $supabase->createPublicOrderDetailed([
        'full_name'     => $name,
        'business_name' => $business,
        'whatsapp'      => $wa,
        'email'         => $email,
        'domain'        => $domain ?: null,
        'package_code'  => $packageCode,
        'voucher_code'  => $voucherUsed ?: null,
    ]);

    $rpcBody = is_array($rpcResponse['body'] ?? null) ? $rpcResponse['body'] : [];
    $rpcResult = is_array($rpcBody[0] ?? null) ? $rpcBody[0] : null;

    if ($rpcResult && !empty($rpcResult['success']) && !empty($rpcResult['order_number'])) {
        // Use authoritative values returned by Supabase for the invoice/email.
        $orderId = (string)$rpcResult['order_number'];
        $base = (int)($rpcResult['base_price'] ?? $base);
        $setup = (int)($rpcResult['setup_fee'] ?? $setup);
        $voucherUsed = (string)($rpcResult['voucher_code'] ?? '');
        $voucherDiscount = (int)($rpcResult['voucher_discount'] ?? 0);
        $total = (int)($rpcResult['total_amount'] ?? 0);
        $dueDate = (string)($rpcResult['due_date'] ?? $dueDate);
        $due = new \DateTime($dueDate);
        $due->setTimezone(new \DateTimeZone('Asia/Jakarta'));
        $supabaseSaved = true;
    } else {
        $supabaseError = 'Supabase tidak berhasil membuat order melalui RPC.';

        // TEMPORARY DIAGNOSTIC: log only HTTP status + sanitized response.
        // Never log API keys, passwords, or the request payload.
        $diagnostic = [
            'http_status' => (int)($rpcResponse['status'] ?? 0),
            'curl_error'  => (string)($rpcResponse['curl_error'] ?? ''),
            'body'        => $rpcResponse['body'] ?? null,
        ];
        error_log(
            'KHANIA_ORDER_RPC_DIAGNOSTIC: ' .
            json_encode($diagnostic, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}

if (!$supabaseSaved) {
    if ($supabase->isConfigured()) {
        // Production mode: do not silently fall back to JSON when Supabase is configured.
        fail('Pesanan belum dapat disimpan ke sistem. Silakan coba lagi beberapa saat kemudian. Jika masalah tetap terjadi, hubungi Khania Studio. (Diagnostic sementara: cek error log server untuk entri KHANIA_ORDER_RPC_DIAGNOSTIC.)', 500);
    }

    // Development/offline fallback only when Supabase is not configured.
    $dataFile = __DIR__ . '/data/orders.json';
    $orders = is_file($dataFile) ? json_decode((string)file_get_contents($dataFile), true) : [];
    if (!is_array($orders)) $orders = [];
    $orders[] = $record;
    if (@file_put_contents($dataFile, json_encode($orders, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        fail('Pesanan tidak dapat disimpan. Silakan coba lagi atau hubungi Khania Studio.', 500);
    }
}

$confirmUrl = orderBaseUrl() . '/konfirmasi-pembayaran.html?ref=' . rawurlencode($orderId);
$subject = 'Invoice Pemesanan Website ' . $orderId . ' — ' . $package . ' — ' . $business;
$bodyText = "KHANIA STUDIO — INVOICE PEMESANAN WEBSITE\r\n\r\n";
$bodyText .= "Nomor Order: $orderId\r\nTanggal Order: " . $now->format('d-m-Y H:i') . " WIB\r\nBatas Pembayaran: " . $due->format('d-m-Y H:i') . " WIB\r\n\r\n";
$bodyText .= "Paket: $package\r\nSewa Hosting + Domain 1 Tahun + Desain Website: " . orderRupiah($base) . "\r\nBiaya Setup untuk 1 tahun pertama: " . orderRupiah($setup) . "\r\nVoucher: " . ($voucherUsed ?: '-') . "\r\nDiskon: " . orderRupiah($voucherDiscount) . "\r\nTOTAL TAGIHAN: " . orderRupiah($total) . "\r\n\r\n";
$bodyText .= "Pemesan: $name\r\nBisnis: $business\r\nWhatsApp: $wa\r\nEmail: $email\r\nDomain: " . ($domain ?: '-') . "\r\nStatus: Menunggu Pembayaran\r\n\r\n";
$bodyText .= "Silakan transfer sesuai total tagihan ke Bank BCA 6755-538-381 a.n. Gembong Budiono.\r\nBerita Transfer: $package atas nama $name\r\n\r\n";
$bodyText .= "Setelah pembayaran dilakukan, konfirmasi melalui: $confirmUrl\r\n";

$bodyHtml = '<!doctype html><html lang="id"><head><meta charset="utf-8"><style>body{font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a;padding:20px}.card{max-width:700px;margin:auto;background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden}.h{background:#0f172a;color:#fff;padding:22px;border-bottom:4px solid #d4af37}.c{padding:22px}.row{padding:9px 0;border-bottom:1px solid #f1f5f9}.total{font-size:20px;font-weight:bold}.bank{margin-top:18px;padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px}.cta{display:inline-block;margin-top:18px;background:#d4af37;color:#0f172a;padding:12px 18px;border-radius:9px;text-decoration:none;font-weight:700}.f{background:#f1f5f9;padding:14px;text-align:center;color:#64748b;font-size:12px}</style></head><body><div class="card"><div class="h"><h2>Invoice Pemesanan Website Khania Studio</h2><div>Order '.esc($orderId).'</div></div><div class="c">';
$rows=[['Paket',$package],['Nama Pemesan',$name],['Nama Bisnis',$business],['WhatsApp',$wa],['Email',$email],['Domain',$domain?:'-'],['Sewa Hosting + Domain 1 Tahun + Desain Website',orderRupiah($base)],['Biaya Setup untuk 1 tahun pertama',orderRupiah($setup)],['Voucher',$voucherUsed?:'-'],['Diskon',orderRupiah($voucherDiscount)],['TOTAL TAGIHAN',orderRupiah($total)],['Batas Pembayaran',$due->format('d-m-Y H:i').' WIB'],['Status','Menunggu Pembayaran']];
foreach($rows as $r){$cls=$r[0]==='TOTAL TAGIHAN'?' class="row total"':' class="row"';$bodyHtml.='<div'.$cls.'><strong>'.esc($r[0]).'</strong><br>'.esc($r[1]).'</div>';}
$bodyHtml.='<div class="bank"><strong>Informasi Pembayaran</strong><br>Bank BCA<br><strong>6755-538-381</strong><br>a.n. Gembong Budiono<br><br><strong>Berita Transfer:</strong> '.esc($package.' atas nama '.$name).'</div><a class="cta" href="'.esc($confirmUrl).'">Konfirmasi Pembayaran Setelah Transfer</a></div><div class="f">Invoice ini dikirim otomatis oleh sistem Khania Studio.</div></div></body></html>';

$mail = new PHPMailer();
$mail->isSMTP(); $mail->Host=SMTP_HOST; $mail->SMTPAuth=true; $mail->Username=SMTP_USERNAME; $mail->Password=SMTP_PASSWORD; $mail->SMTPSecure=PHPMailer::ENCRYPTION_STARTTLS; $mail->Port=SMTP_PORT; $mail->CharSet='UTF-8'; $mail->Encoding='quoted-printable';
$mail->setFrom(ADMIN_EMAIL,'Khania Studio Order');
$mail->addAddress($email,$name);
if (strtolower($email) !== strtolower(ADMIN_EMAIL)) $mail->addCC(ADMIN_EMAIL,'Khania Studio Admin');
if (defined('INTERNAL_CC') && INTERNAL_CC && strtolower(INTERNAL_CC)!==strtolower($email) && strtolower(INTERNAL_CC)!==strtolower(ADMIN_EMAIL)) $mail->addCC(INTERNAL_CC);
$mail->addReplyTo(ADMIN_EMAIL,'Khania Studio');
$mail->isHTML(true); $mail->Subject=$subject; $mail->Body=$bodyHtml; $mail->AltBody=$bodyText;
if (!$mail->send()) error_log('Khania Studio Order SMTP Error: '.$mail->ErrorInfo);

header('Location: konfirmasi-pemesanan.html?ref=' . rawurlencode($orderId));
exit;
