<?php
/**
 * KHANIA STUDIO — proses-konfirmasi-pembayaran.php
 * Stage 1: separate payment confirmation from website order creation.
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

function failPayment($message, $status = 400) {
    http_response_code($status);
    $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Konfirmasi Pembayaran — Khania Studio</title><style>body{font:16px/1.6 Arial,sans-serif;background:#f1f5f9;color:#0f172a;margin:0}.wrap{max-width:700px;margin:10vh auto;padding:24px}.card{background:#fff;padding:28px;border-radius:16px;border:1px solid #dbe2ea}a{display:inline-block;background:#d4af37;color:#0f172a;padding:10px 16px;border-radius:9px;text-decoration:none;font-weight:700}</style></head><body><div class="wrap"><div class="card"><h1>Konfirmasi Pembayaran</h1><p>'.$safe.'</p><a href="javascript:history.back()">Kembali ke formulir</a></div></div></body></html>';
    exit;
}
function postValue($key) { $v=$_POST[$key]??''; return is_string($v)?trim($v):''; }
function escPay($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function rupiahPay($n) { return 'Rp' . number_format((int)$n, 0, ',', '.'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.html#harga'); exit; }

$orderId = strtoupper(postValue('orderNumber'));
$email = postValue('email');
$paymentDate = postValue('paymentDate');
if ($orderId === '' || $email === '' || $paymentDate === '') failPayment('Nomor Order, Email, dan Tanggal Pembayaran wajib diisi.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) failPayment('Alamat email tidak valid.');
if (!preg_match('/^KS-\d{6}-[A-Z0-9]{4}$/', $orderId)) failPayment('Nomor Order tidak valid.');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) failPayment('Tanggal pembayaran tidak valid.');
if ($paymentDate > date('Y-m-d')) failPayment('Tanggal pembayaran tidak boleh melebihi tanggal hari ini.');

$file = $_FILES['paymentProof'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) failPayment('Bukti transfer wajib diunggah.');
if ((int)$file['size'] <= 0 || (int)$file['size'] > MAX_FILE_SIZE) failPayment('Bukti transfer maksimal 10 MB.');
$ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
$allowedExts = ['jpg','jpeg','png','webp','pdf'];
if (!in_array($ext, $allowedExts, true)) failPayment('Format bukti transfer harus JPG, PNG, WEBP, atau PDF.');

// Locate order securely by order number + email.
$order = null;
if ($supabase->isConfigured()) {
    $order = $supabase->findOrderForPayment($orderId, $email);
}
if ($order === null) {
    $dataFile = __DIR__ . '/data/orders.json';
    if (is_file($dataFile)) {
        $orders = json_decode((string)file_get_contents($dataFile), true);
        if (is_array($orders)) foreach ($orders as $candidate) {
            if (strtoupper((string)($candidate['orderId'] ?? '')) === $orderId && strtolower((string)($candidate['email'] ?? '')) === strtolower($email)) {
                $order = [
                    'id' => null,
                    'order_number' => $candidate['orderId'],
                    'package' => $candidate['package'] ?? '-',
                    'total_amount' => (int)($candidate['total'] ?? 0),
                    'customer_name' => $candidate['customerName'] ?? '',
                    'business_name' => $candidate['businessName'] ?? '',
                    'email' => $candidate['email'] ?? '',
                    'status' => 'pending_payment'
                ];
                break;
            }
        }
    }
}
if ($order === null) failPayment('Nomor Order dan Email tidak ditemukan. Pastikan keduanya sesuai dengan invoice yang diterima.');
if (($order['status'] ?? '') !== 'pending_payment' && ($order['status'] ?? '') !== 'Menunggu Pembayaran') {
    failPayment('Order ini tidak berada pada status Menunggu Pembayaran. Jika Anda sudah pernah mengirim konfirmasi, silakan hubungi Khania Studio.');
}

$uploadDir = __DIR__ . '/data/order-proofs';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true)) failPayment('Folder penyimpanan bukti pembayaran tidak dapat dibuat.', 500);
$safeName = $orderId . '-PAYMENT-' . date('YmdHis') . '-' . preg_replace('/[^A-Za-z0-9._-]/','_',basename((string)$file['name']));
$proofPath = $uploadDir . '/' . $safeName;
if (!move_uploaded_file($file['tmp_name'], $proofPath)) failPayment('Bukti transfer gagal disimpan. Silakan coba lagi.', 500);

$proofStoragePath = null;
if ($supabase->isConfigured()) {
    $content = @file_get_contents($proofPath);
    if ($content !== false) {
        $mime = 'application/octet-stream';
        if ($ext==='jpg'||$ext==='jpeg') $mime='image/jpeg'; elseif($ext==='png') $mime='image/png'; elseif($ext==='webp') $mime='image/webp'; elseif($ext==='pdf') $mime='application/pdf';
        $bucket = $supabase->getPaymentProofsBucket() ?: 'order-proofs';
        $storagePath = $orderId . '/' . $safeName;
        $up = $supabase->uploadFile($bucket, $storagePath, $content, $mime);
        if (($up['status'] ?? 0) >= 200 && ($up['status'] ?? 0) < 300) $proofStoragePath = $storagePath;
        else error_log('Khania Studio Payment: Supabase Storage upload failed for '.$orderId);
    }
}

$amount = (int)($order['total_amount'] ?? 0);
$submitted = false;
if ($supabase->isConfigured() && !empty($order['id'])) {
    $rpc = $supabase->submitPaymentConfirmation($orderId, $email, $paymentDate, $amount, $proofStoragePath ?: $safeName);
    if (!empty($rpc['success'])) $submitted = true;
    elseif (!empty($rpc['message'])) error_log('Khania Studio Payment RPC: '.$rpc['message']);
}

if (!$submitted) {
    $dataFile = __DIR__ . '/data/orders.json';
    $orders = is_file($dataFile) ? json_decode((string)file_get_contents($dataFile), true) : [];
    if (!is_array($orders)) $orders=[];
    $found=false;
    foreach ($orders as &$candidate) {
        if (strtoupper((string)($candidate['orderId'] ?? '')) === $orderId && strtolower((string)($candidate['email'] ?? '')) === strtolower($email)) {
            $candidate['paymentDate']=$paymentDate;
            $candidate['paymentProof']=$safeName;
            $candidate['status']='Menunggu Verifikasi Pembayaran';
            $found=true; break;
        }
    }
    unset($candidate);
    if ($found) {
        if (@file_put_contents($dataFile, json_encode($orders, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX) === false) failPayment('Konfirmasi pembayaran tidak dapat disimpan.',500);
    } elseif (!$submitted) {
        failPayment('Konfirmasi pembayaran tidak dapat disimpan. Silakan coba lagi atau hubungi Khania Studio.',500);
    }
}

$package = $order['package'] ?? '-';
$business = $order['business_name'] ?? '-';
$subject = 'Konfirmasi Pembayaran ' . $orderId . ' — ' . $package . ' — ' . $business;
$bodyText = "KHANIA STUDIO — KONFIRMASI PEMBAYARAN\r\n\r\nNomor Order: $orderId\r\nPaket: $package\r\nNama Pemesan: " . ($order['customer_name'] ?? '-') . "\r\nEmail: $email\r\nTanggal Pembayaran: $paymentDate\r\nJumlah Tagihan: " . rupiahPay($amount) . "\r\nStatus: Menunggu Verifikasi Pembayaran\r\n";
$bodyHtml = '<!doctype html><html lang="id"><head><meta charset="utf-8"><style>body{font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a;padding:20px}.card{max-width:700px;margin:auto;background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden}.h{background:#0f172a;color:#fff;padding:22px;border-bottom:4px solid #d4af37}.c{padding:22px}.row{padding:9px 0;border-bottom:1px solid #f1f5f9}.total{font-size:20px;font-weight:bold}.f{background:#f1f5f9;padding:14px;text-align:center;color:#64748b;font-size:12px}</style></head><body><div class="card"><div class="h"><h2>Konfirmasi Pembayaran Khania Studio</h2><div>Order '.escPay($orderId).'</div></div><div class="c">';
$rows=[['Paket',$package],['Nama Pemesan',$order['customer_name']??'-'],['Nama Bisnis',$business],['Email',$email],['Tanggal Pembayaran',$paymentDate],['Jumlah Tagihan',rupiahPay($amount)],['Status','Menunggu Verifikasi Pembayaran']];
foreach($rows as $r){$cls=$r[0]==='Status'?' class="row total"':' class="row"';$bodyHtml.='<div'.$cls.'><strong>'.escPay($r[0]).'</strong><br>'.escPay($r[1]).'</div>';}
$bodyHtml.='</div><div class="f">Bukti transfer tersimpan pada sistem. Email ini dikirim otomatis oleh Khania Studio.</div></div></body></html>';

$mail = new PHPMailer();
$mail->isSMTP(); $mail->Host=SMTP_HOST; $mail->SMTPAuth=true; $mail->Username=SMTP_USERNAME; $mail->Password=SMTP_PASSWORD; $mail->SMTPSecure=PHPMailer::ENCRYPTION_STARTTLS; $mail->Port=SMTP_PORT; $mail->CharSet='UTF-8'; $mail->Encoding='quoted-printable';
$mail->setFrom(ADMIN_EMAIL,'Khania Studio Payment');
$mail->addAddress(ADMIN_EMAIL,'Khania Studio Admin');
if (defined('INTERNAL_CC') && INTERNAL_CC && strtolower(INTERNAL_CC)!==strtolower(ADMIN_EMAIL)) $mail->addCC(INTERNAL_CC);
if (strtolower($email)!==strtolower(ADMIN_EMAIL) && strtolower($email)!==strtolower(INTERNAL_CC ?? '')) $mail->addCC($email,$order['customer_name']??'Pelanggan');
$mail->addReplyTo($email,$order['customer_name']??'Pelanggan');
$mail->addAttachment($proofPath,$safeName);
$mail->isHTML(true); $mail->Subject=$subject; $mail->Body=$bodyHtml; $mail->AltBody=$bodyText;
if (!$mail->send()) error_log('Khania Studio Payment SMTP Error: '.$mail->ErrorInfo);

header('Location: konfirmasi-pembayaran-sukses.html?ref=' . rawurlencode($orderId));
exit;
