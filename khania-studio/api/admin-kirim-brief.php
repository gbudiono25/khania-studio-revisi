<?php
// KHANIA STUDIO — Admin: Kirim Form Brief
// Uses the logged-in Supabase access token for admin authorization.
// SMTP credentials remain in the existing server-side config-pemesanan.php.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function json_out($data, $status=200){
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function bearer(){
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $h, $m)) return trim($m[1]);
    return '';
}
function post_json(){
    $raw=file_get_contents('php://input');
    $d=json_decode($raw,true);
    return is_array($d)?$d:[];
}
function sb_request($method,$url,$anonKey,$accessToken,$body=null){
    $ch=curl_init($url);
    $headers=['apikey: '.$anonKey,'Authorization: Bearer '.$accessToken,'Accept: application/json','Content-Type: application/json'];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10]);
    if($body!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $raw=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $decoded=json_decode((string)$raw,true);
    return ['status'=>$status,'body'=>$decoded??$raw,'raw'=>$raw,'error'=>$err];
}

if($_SERVER['REQUEST_METHOD']!=='POST') json_out(['success'=>false,'message'=>'Method tidak diizinkan.'],405);

require_once __DIR__.'/../lib/env.php';
loadEnv(__DIR__.'/../.env');
require_once __DIR__.'/../config-pemesanan.php';
require_once __DIR__.'/../lib/PHPMailerLite.php';

use PHPMailer\PHPMailer\PHPMailer;

$accessToken=bearer();
if($accessToken==='') json_out(['success'=>false,'message'=>'Sesi admin tidak ditemukan. Silakan login ulang.'],401);

$supabaseUrl=rtrim((string)getenv('SUPABASE_URL'),'/');
$anonKey=(string)getenv('SUPABASE_ANON_KEY');
if($supabaseUrl===''||$anonKey==='') json_out(['success'=>false,'message'=>'Konfigurasi Supabase belum tersedia di server.'],500);

$me=sb_request('GET',$supabaseUrl.'/auth/v1/user',$anonKey,$accessToken);
if($me['status']<200||$me['status']>=300||!is_array($me['body'])||empty($me['body']['id'])){
    json_out(['success'=>false,'message'=>'Sesi admin tidak valid atau sudah kedaluwarsa. Silakan login ulang.'],401);
}
$userId=(string)$me['body']['id'];
$profile=sb_request('GET',$supabaseUrl.'/rest/v1/profiles?select=role,full_name&id=eq.'.rawurlencode($userId).'&limit=1',$anonKey,$accessToken);
$profileRow=(is_array($profile['body'])&&isset($profile['body'][0]))?$profile['body'][0]:null;
if(!$profileRow||($profileRow['role']??'')!=='admin') json_out(['success'=>false,'message'=>'Akses ditolak. Hanya admin yang dapat mengirim Form Brief.'],403);

$input=post_json();
$orderId=trim((string)($input['order_id']??''));
if($orderId===''||!preg_match('/^[0-9a-fA-F-]{36}$/',$orderId)) json_out(['success'=>false,'message'=>'Order ID tidak valid.'],400);

try{$token=bin2hex(random_bytes(32));}catch(Throwable $e){json_out(['success'=>false,'message'=>'Sistem tidak dapat membuat token aman.'],500);}
$tokenHash=hash('sha256',$token);
$expires=(new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta')))->modify('+14 days')->format(DateTimeInterface::ATOM);

$prep=sb_request('POST',$supabaseUrl.'/rest/v1/rpc/admin_prepare_brief',$anonKey,$accessToken,[
    'p_order_id'=>$orderId,
    'p_token_hash'=>$tokenHash,
    'p_token_expires_at'=>$expires,
]);
$prepRow=(is_array($prep['body'])&&isset($prep['body'][0]))?$prep['body'][0]:$prep['body'];
if($prep['status']<200||$prep['status']>=300||!is_array($prepRow)||($prepRow['success']??false)!==true){
    $msg=is_array($prepRow)?($prepRow['message']??'Gagal menyiapkan Form Brief.'): 'Gagal menyiapkan Form Brief.';
    json_out(['success'=>false,'message'=>$msg],500);
}

$briefId=$prepRow['brief_id'];
$orderNumber=$prepRow['order_number'];
$clientName=$prepRow['client_name']?:'Klien Khania Studio';
$businessName=$prepRow['business_name']?:'';
$clientEmail=$prepRow['client_email']?:'';
$packageName=$prepRow['package_name']?:'';
$clientCode=$prepRow['client_code']?:'';
if(!filter_var($clientEmail,FILTER_VALIDATE_EMAIL)) json_out(['success'=>false,'message'=>'Email klien pada order tidak valid.'],422);

$link='https://khania-studio.com/formulir-permintaan-website-khania-studio-v3.html?brief_token='.rawurlencode($token);

$mail=new PHPMailer();
$mail->isSMTP();
$mail->Host=SMTP_HOST;
$mail->Port=SMTP_PORT;
$mail->SMTPSecure='tls';
$mail->SMTPAuth=true;
$mail->Username=SMTP_USERNAME;
$mail->Password=SMTP_PASSWORD;
$mail->CharSet='UTF-8';
$mail->isHTML(true);
$mail->setFrom(ADMIN_EMAIL,'Khania Studio');
$mail->addAddress($clientEmail,$clientName);
if(defined('INTERNAL_CC') && INTERNAL_CC!=='') $mail->addCC(INTERNAL_CC,'Khania Studio Internal');
$mail->Subject='Form Brief Website — '.$orderNumber.' — Khania Studio';
$mail->Body='<!doctype html><html><body style="font-family:Arial,sans-serif;color:#172033;line-height:1.6"><div style="max-width:680px;margin:auto"><h2>Khania Studio — Form Brief Website</h2><p>Halo '.htmlspecialchars($clientName,ENT_QUOTES,'UTF-8').',</p><p>Pembayaran untuk pesanan <b>'.htmlspecialchars($orderNumber,ENT_QUOTES,'UTF-8').'</b> telah diverifikasi.</p><p>Silakan mengisi <b>Client Website Brief V3</b> melalui tombol berikut. Data order dan identitas pemesan akan tampil otomatis pada formulir.</p><p><b>Kode Klien/Project:</b> '.htmlspecialchars($clientCode,ENT_QUOTES,'UTF-8').'<br><b>Paket:</b> '.htmlspecialchars($packageName,ENT_QUOTES,'UTF-8').'<br><b>Bisnis:</b> '.htmlspecialchars($businessName,ENT_QUOTES,'UTF-8').'</p><p><a href="'.htmlspecialchars($link,ENT_QUOTES,'UTF-8').'" style="display:inline-block;background:#d4af37;color:#101828;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:bold">Isi Form Brief Website</a></p><p>Link ini berlaku selama 14 hari dan ditujukan khusus untuk pesanan tersebut.</p><p>Jika membutuhkan bantuan, silakan hubungi Khania Studio melalui WhatsApp.</p><p>Terima kasih,<br><b>Khania Studio</b></p></div></body></html>';
$mail->AltBody='Khania Studio — Form Brief Website\n\nOrder: '.$orderNumber.'\nPaket: '.$packageName.'\nKode Klien/Project: '.$clientCode.'\n\nIsi Form Brief: '.$link.'\n\nLink berlaku 14 hari.';

try{$sent=$mail->send();}catch(Throwable $e){$sent=false;}
if(!$sent){
    json_out(['success'=>false,'message'=>'Form Brief sudah disiapkan, tetapi email belum berhasil dikirim. Silakan coba Kirim Ulang.'],502);
}

$mark=sb_request('POST',$supabaseUrl.'/rest/v1/rpc/admin_mark_brief_sent',$anonKey,$accessToken,['p_brief_id'=>$briefId]);
$markRow=(is_array($mark['body'])&&isset($mark['body'][0]))?$mark['body'][0]:$mark['body'];
if($mark['status']<200||$mark['status']>=300||!is_array($markRow)||($markRow['success']??false)!==true){
    // Email was already delivered. Report success with a warning so admin can refresh and retry the marker if necessary.
    json_out(['success'=>true,'warning'=>'Email Form Brief sudah terkirim, tetapi status pengiriman belum berhasil dicatat.','order_number'=>$orderNumber,'brief_id'=>$briefId]);
}

json_out(['success'=>true,'message'=>'Form Brief berhasil dikirim ke email klien.','order_number'=>$orderNumber,'brief_id'=>$briefId]);
