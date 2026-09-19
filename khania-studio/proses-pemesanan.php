<?php
namespace PHPMailer\PHPMailer;

/**
 * Khania Studio — proses-formulir.php
 * Standalone Self-Contained PHPMailer SMTP Server Handler for Brief Form V3
 *
 * Designed for 100% zero-dependency execution on Rumahweb shared hosting.
 * Includes RFC 5322 quoted-printable encoding to guarantee delivery to external mail providers (Gmail/Yahoo/etc).
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

// SMTP configuration is stored separately so the password is not duplicated in this script.
require_once __DIR__ . '/config-pemesanan.php';
require_once __DIR__ . '/lib/SupabaseClient.php';

use KhaniaStudio\SupabaseClient;

$supabase = new SupabaseClient();

const MAX_FILE_SIZE = 10 * 1024 * 1024;

/* Embedded PHPMailer Exception Class */
if (!class_exists('PHPMailer\PHPMailer\Exception')) {
    class Exception extends \Exception {
        public function errorMessage() {
            return '<strong>' . htmlspecialchars($this->getMessage(), ENT_QUOTES, 'UTF-8') . "</strong><br />\n";
        }
    }
}

/* Embedded PHPMailer SMTP Transport Class */
if (!class_exists('PHPMailer\PHPMailer\SMTP')) {
    class SMTP {
        const VERSION = '6.9.1';
        const LE = "\r\n";
        const DEFAULT_PORT = 25;
        const MAX_LINE_LENGTH = 998;
        const MAX_REPLY_LENGTH = 512;
        const DEBUG_OFF = 0;
        const DEBUG_CLIENT = 1;
        const DEBUG_SERVER = 2;

        public $do_debug = self::DEBUG_OFF;
        public $Debugoutput = 'echo';
        public $SMTP_PORT = 25;
        public $CRLF = "\r\n";
        public $do_verp = false;
        public $Timeout = 30;
        public $Timelimit = 30;

        protected $smtp_conn;
        protected $error = ['error' => '', 'detail' => '', 'smtp_code' => '', 'smtp_code_ex' => ''];
        protected $helo_rply;
        protected $server_caps;
        protected $last_reply = '';

        public function connect($host, $port = null, $timeout = 30, $options = []) {
            $this->emptyLastError();
            if ($this->connected()) {
                $this->setError('Already connected to a server');
                return false;
            }
            if (empty($port)) {
                $port = $this->SMTP_PORT;
            }
            $errno = 0;
            $errstr = '';
            $socket_context = stream_context_create($options);
            set_error_handler([$this, 'errorHandler']);
            $connection = stream_socket_client($host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $socket_context);
            restore_error_handler();

            if (!is_resource($connection)) {
                $this->setError('Failed to connect to server', $errno, $errstr);
                return false;
            }
            $this->smtp_conn = $connection;
            stream_set_timeout($this->smtp_conn, $timeout);
            $announce = $this->get_lines();
            return true;
        }

        public function startTLS() {
            if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) {
                return false;
            }
            $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            set_error_handler([$this, 'errorHandler']);
            $crypto_ok = stream_socket_enable_crypto($this->smtp_conn, true, $crypto_method);
            restore_error_handler();
            return $crypto_ok === true;
        }

        public function authenticate($username, $password, $authtype = null) {
            if (!$this->connected()) {
                $this->setError('Called authenticate() without being connected');
                return false;
            }
            if (empty($authtype)) {
                $authtype = 'LOGIN';
            }
            switch ($authtype) {
                case 'PLAIN':
                    if (!$this->sendCommand('AUTH PLAIN', 'AUTH PLAIN', 334)) return false;
                    if (!$this->sendCommand('User & Password', base64_encode("\0" . $username . "\0" . $password), 235)) return false;
                    break;
                case 'LOGIN':
                default:
                    if (!$this->sendCommand('AUTH LOGIN', 'AUTH LOGIN', 334)) return false;
                    if (!$this->sendCommand('Username', base64_encode($username), 334)) return false;
                    if (!$this->sendCommand('Password', base64_encode($password), 235)) return false;
                    break;
            }
            return true;
        }

        public function hello($host = '') {
            if (!$this->connected()) {
                $this->setError('Called hello() without being connected');
                return false;
            }
            if (empty($host)) $host = 'localhost';
            if (!$this->sendHelo('EHLO', $host)) {
                if (!$this->sendHelo('HELO', $host)) return false;
            }
            return true;
        }

        protected function sendHelo($hello, $host) {
            $noerror = $this->sendCommand($hello, $hello . ' ' . $host, 250);
            if ($noerror) $this->parseHelloFields($hello);
            return $noerror;
        }

        protected function parseHelloFields($type) {
            $this->server_caps = [];
            $lines = explode("\n", (string)$this->helo_rply);
            foreach ($lines as $line) {
                if (empty($line)) continue;
                $line = trim(substr($line, 4));
                $fields = explode(' ', $line);
                if (!empty($fields[0])) {
                    $cmd = strtoupper($fields[0]);
                    $this->server_caps[$cmd] = count($fields) > 1 ? array_slice($fields, 1) : true;
                }
            }
        }

        public function mail($from) {
            return $this->sendCommand('MAIL FROM', 'MAIL FROM:<' . $from . '>', 250);
        }

        public function recipient($toaddress) {
            return $this->sendCommand('RCPT TO', 'RCPT TO:<' . $toaddress . '>', [250, 251]);
        }

        public function data($msg_data) {
            if (!$this->sendCommand('DATA', 'DATA', 354)) return false;
            $msg_data = str_replace("\r\n", "\n", $msg_data);
            $lines = explode("\n", $msg_data);
            foreach ($lines as $line) {
                if (!empty($line) && $line[0] === '.') {
                    $line = '.' . $line;
                }
                $this->client_send($line . static::LE, 'DATA');
            }
            return $this->sendCommand('DATA END', '.', 250);
        }

        public function quit($close_on_error = true) {
            $noerror = $this->sendCommand('QUIT', 'QUIT', 221);
            $err = $this->error;
            if ($noerror || $close_on_error) {
                $this->close();
                $this->error = $err;
            }
            return $noerror;
        }

        public function close() {
            $this->emptyLastError();
            $this->server_caps = null;
            $this->helo_rply = null;
            if (is_resource($this->smtp_conn)) {
                fclose($this->smtp_conn);
                $this->smtp_conn = null;
            }
        }

        public function connected() {
            if (is_resource($this->smtp_conn)) {
                $sock_status = stream_get_meta_data($this->smtp_conn);
                return !$sock_status['timed_out'];
            }
            return false;
        }

        protected function sendCommand($commandlabel, $command, $expect) {
            if (!$this->connected()) {
                $this->setError('Called ' . $commandlabel . '() without being connected');
                return false;
            }
            if (strpos($command, "\r") !== false || strpos($command, "\n") !== false) {
                $this->setError('Command contains line breaks');
                return false;
            }
            $this->client_send($command . static::LE, $commandlabel);
            $this->last_reply = $this->get_lines();
            if ($commandlabel === 'EHLO' || $commandlabel === 'HELO') {
                $this->helo_rply = $this->last_reply;
            }
            $code = (int)substr($this->last_reply, 0, 3);
            if (!in_array($code, (array)$expect, true)) {
                $this->setError($commandlabel . ' command failed', $this->last_reply, $code);
                return false;
            }
            $this->emptyLastError();
            return true;
        }

        protected function client_send($data, $commandlabel = '') {
            set_error_handler([$this, 'errorHandler']);
            $result = fwrite($this->smtp_conn, $data);
            restore_error_handler();
            return $result;
        }

        protected function get_lines() {
            if (!is_resource($this->smtp_conn)) return '';
            $data = '';
            stream_set_timeout($this->smtp_conn, $this->Timeout);
            $selR = [$this->smtp_conn];
            $selW = null;
            while (is_resource($this->smtp_conn) && !feof($this->smtp_conn)) {
                if (!stream_select($selR, $selW, $selW, $this->Timeout)) break;
                $str = fgets($this->smtp_conn, self::MAX_REPLY_LENGTH);
                $data .= $str;
                if (isset($str[3]) && $str[3] === ' ') break;
            }
            return $data;
        }

        public function getLastError() { return $this->error; }
        protected function setError($message, $detail = '', $smtp_code = '') {
            $this->error['error'] = $message;
            $this->error['detail'] = $detail;
            $this->error['smtp_code'] = $smtp_code;
        }
        protected function emptyLastError() {
            $this->error = ['error' => '', 'detail' => '', 'smtp_code' => '', 'smtp_code_ex' => ''];
        }
        public function errorHandler($errno, $errmsg, $errfile = '', $errline = 0) { return true; }
    }
}

/* Embedded PHPMailer Main Class */
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    class PHPMailer {
        const ENCRYPTION_STARTTLS = 'tls';
        const ENCRYPTION_SMTPS = 'ssl';

        public $CharSet = 'utf-8';
        public $ContentType = 'text/html';
        public $Encoding = 'quoted-printable';
        public $ErrorInfo = '';
        public $From = 'root@localhost';
        public $FromName = '';
        public $Sender = '';
        public $Subject = '';
        public $Body = '';
        public $AltBody = '';
        public $Mailer = 'smtp';
        public $Host = 'localhost';
        public $Port = 25;
        public $SMTPSecure = '';
        public $SMTPAuth = false;
        public $Username = '';
        public $Password = '';
        public $Timeout = 30;
        public $SMTPDebug = 0;

        protected $to = [];
        protected $cc = [];
        protected $bcc = [];
        protected $ReplyTo = [];
        protected $attachment = [];
        protected $boundary = [];
        protected $smtp;

        public function isSMTP() { $this->Mailer = 'smtp'; }
        public function isHTML($isHtml = true) { $this->ContentType = $isHtml ? 'text/html' : 'text/plain'; }
        public function setFrom($address, $name = '') {
            $this->From = trim($address);
            $this->FromName = trim($name);
            if (empty($this->Sender)) $this->Sender = $this->From;
            return true;
        }
        public function addAddress($address, $name = '') { $this->to[] = [trim($address), trim($name)]; return true; }
        public function addCC($address, $name = '') { $this->cc[] = [trim($address), trim($name)]; return true; }
        public function addBCC($address, $name = '') { $this->bcc[] = [trim($address), trim($name)]; return true; }
        public function addReplyTo($address, $name = '') { $this->ReplyTo[] = [trim($address), trim($name)]; return true; }

        public function addAttachment($path, $name = '', $encoding = 'base64', $type = '') {
            if (!@is_file($path) || !@is_readable($path)) return false;
            if (empty($name)) $name = basename($path);
            if (empty($type)) $type = 'application/octet-stream';
            $this->attachment[] = [$path, $name, $name, $encoding, $type, false, 'attachment', $name];
            return true;
        }

        public function send() {
            try {
                $smtp = new SMTP();
                $smtp->Timeout = $this->Timeout;
                if (!$smtp->connect($this->Host, $this->Port, $this->Timeout)) {
                    throw new Exception('SMTP Connection failed');
                }
                if (!$smtp->hello(gethostname() ?: 'localhost')) {
                    throw new Exception('SMTP Hello failed');
                }
                if ($this->SMTPSecure === 'tls') {
                    if (!$smtp->startTLS()) throw new Exception('SMTP STARTTLS failed');
                    if (!$smtp->hello(gethostname() ?: 'localhost')) throw new Exception('SMTP EHLO after TLS failed');
                }
                if ($this->SMTPAuth) {
                    if (!$smtp->authenticate($this->Username, $this->Password)) throw new Exception('SMTP Auth failed');
                }
                $sender = !empty($this->Sender) ? $this->Sender : $this->From;
                if (!$smtp->mail($sender)) throw new Exception('MAIL FROM failed');

                $recipients = array_merge($this->to, $this->cc, $this->bcc);
                foreach ($recipients as $to) {
                    if (!$smtp->recipient($to[0])) throw new Exception('RCPT TO failed for ' . $to[0]);
                }

                $header = $this->createHeader();
                $body = $this->createBody();

                if (!$smtp->data($header . $body)) throw new Exception('DATA command failed');
                $smtp->quit();
                $smtp->close();
                return true;
            } catch (Exception $e) {
                $this->ErrorInfo = $e->getMessage();
                return false;
            }
        }

        protected function createHeader() {
            $r = 'Date: ' . date('D, j M Y H:i:s O') . "\r\n";
            $r .= 'From: ' . $this->formatAddr([$this->From, $this->FromName]) . "\r\n";
            if (!empty($this->to)) $r .= 'To: ' . $this->addrList($this->to) . "\r\n";
            if (!empty($this->cc)) $r .= 'Cc: ' . $this->addrList($this->cc) . "\r\n";
            if (!empty($this->ReplyTo)) $r .= 'Reply-To: ' . $this->addrList($this->ReplyTo) . "\r\n";
            $r .= 'Subject: =?UTF-8?B?' . base64_encode($this->Subject) . "?=\r\n";
            $r .= 'MIME-Version: 1.0' . "\r\n";
            $r .= sprintf("Message-ID: <%s@%s>\r\n", md5(uniqid((string)microtime(true))), gethostname() ?: 'khania-studio.com');

            if (!empty($this->attachment)) {
                $this->boundary[1] = 'b1_' . md5(uniqid((string)microtime(true)));
                $r .= 'Content-Type: multipart/mixed; boundary="' . $this->boundary[1] . '"' . "\r\n";
            } else {
                $r .= 'Content-Type: ' . $this->ContentType . '; charset=' . $this->CharSet . "\r\n";
                $r .= 'Content-Transfer-Encoding: ' . $this->Encoding . "\r\n";
            }
            return $r . "\r\n";
        }

        protected function createBody() {
            $body = '';
            if (!empty($this->attachment)) {
                $b = $this->boundary[1];
                $body .= "--" . $b . "\r\n";
                $body .= 'Content-Type: ' . $this->ContentType . '; charset=' . $this->CharSet . "\r\n";
                $body .= 'Content-Transfer-Encoding: ' . $this->Encoding . "\r\n\r\n";
                
                $encodedBody = ($this->Encoding === 'quoted-printable') ? quoted_printable_encode($this->Body) : $this->Body;
                $body .= $encodedBody . "\r\n\r\n";

                foreach ($this->attachment as $att) {
                    $path = $att[0];
                    $name = $att[1];
                    $type = $att[4];
                    $fileData = file_get_contents($path);
                    if ($fileData === false) continue;
                    $body .= "--" . $b . "\r\n";
                    $body .= 'Content-Type: ' . $type . '; name="' . $name . '"' . "\r\n";
                    $body .= 'Content-Transfer-Encoding: base64' . "\r\n";
                    $body .= 'Content-Disposition: attachment; filename="' . $name . '"' . "\r\n\r\n";
                    $body .= chunk_split(base64_encode($fileData)) . "\r\n";
                }
                $body .= "--" . $b . "--\r\n";
            } else {
                $encodedBody = ($this->Encoding === 'quoted-printable') ? quoted_printable_encode($this->Body) : $this->Body;
                $body .= $encodedBody;
            }
            return $body;
        }

        protected function addrList($addrArray) {
            $list = [];
            foreach ($addrArray as $addr) $list[] = $this->formatAddr($addr);
            return implode(', ', $list);
        }

        protected function formatAddr($addr) {
            return empty($addr[1]) ? $addr[0] : '=?UTF-8?B?' . base64_encode($addr[1]) . '?= <' . $addr[0] . '>';
        }
    }
}

function fail($message, $status = 400) {
    $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pemesanan Website — Khania Studio</title><style>body{font:16px/1.6 Arial,sans-serif;background:#f1f5f9;color:#0f172a;margin:0}.wrap{max-width:700px;margin:10vh auto;padding:24px}.card{background:#fff;padding:28px;border-radius:16px;border:1px solid #dbe2ea}a{display:inline-block;background:#d4af37;color:#0f172a;padding:10px 16px;border-radius:9px;text-decoration:none;font-weight:700}</style></head><body><div class="wrap"><div class="card"><h1>Informasi Pengiriman</h1><p>'.$safe.'</p><a href="javascript:history.back()">Kembali ke formulir</a></div></div></body></html>';
    exit;
}

/* SECURITY FIX: REMOVE PUBLIC DIAGNOSTIC DASHBOARD ON GET REQUESTS */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html#harga');
    exit;
}


/* ORDER SUBMISSION */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.html#harga'); exit; }
if (!empty($_POST['website'] ?? '')) fail('Permintaan tidak dapat diproses.', 400);

function orderPost($key) {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}
function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function orderRupiah($n) { return 'Rp' . number_format((int)$n, 0, ',', '.'); }

$packages = [
    'Starter' => ['base'=>400000,'setup'=>100000],
    'Bronze'  => ['base'=>580000,'setup'=>200000],
    'Silver'  => ['base'=>1100000,'setup'=>400000],
    'Gold'    => ['base'=>1750000,'setup'=>600000],
];
$package = orderPost('package');
if (!isset($packages[$package])) fail('Paket pemesanan tidak valid. Silakan kembali ke halaman paket dan pilih paket yang tersedia.');

$name = orderPost('customerName');
$business = orderPost('businessName');
$wa = orderPost('whatsapp');
$email = orderPost('email');
$domain = orderPost('domain');
$paymentDate = orderPost('paymentDate');
$voucherCode = strtoupper(orderPost('voucherCode'));
$voucherDiscount = max(0, (int)orderPost('voucherDiscount'));
$base = $packages[$package]['base'];
$setup = $packages[$package]['setup'];
$subtotal = $base + $setup;
// Never trust the browser's total/discount. Voucher verification is repeated server-side.
$voucherDiscount = 0;
$voucherUsed = '';

if ($voucherCode !== '' && $supabase->isConfigured()) {
    // Primary: validate voucher from Supabase
    $packageMap = ['Starter'=>'starter','Bronze'=>'bronze','Silver'=>'silver','Gold'=>'gold'];
    $pkgCode = $packageMap[$package] ?? strtolower($package);
    $result = $supabase->validateVoucher($voucherCode, $pkgCode, $subtotal);
    if ($result) {
        $voucherDiscount = $result['discount_amount'] ?? 0;
        $voucherUsed = $voucherCode;
    }
}

// Fallback: validate voucher from local JSON if Supabase is unavailable
if ($voucherCode !== '' && $voucherUsed === '' && !$supabase->isConfigured()) {
    $voucherFile = __DIR__ . '/data/vouchers.json';
    if (is_file($voucherFile)) {
        $list = json_decode((string)file_get_contents($voucherFile), true);
        if (is_array($list)) {
            foreach ($list as $v) {
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
}
$total = $subtotal - $voucherDiscount;

$required = ['Nama Lengkap'=>$name,'Nama Usaha / Bisnis'=>$business,'Nomor WhatsApp'=>$wa,'Email'=>$email,'Tanggal Pembayaran'=>$paymentDate];
$missing=[]; foreach($required as $label=>$value) if($value==='') $missing[]=$label;
if ($missing) fail('Field wajib belum lengkap: ' . implode(', ', $missing) . '.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Alamat email tidak valid.');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) fail('Tanggal pembayaran tidak valid.');

$now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
$orderDate = $now->format('Y-m-d');
$due = clone $now; $due->modify('+2 days'); $due->setTime(23,59,59);
$dueDate = $due->format('Y-m-d H:i:s');
$random = strtoupper(substr(bin2hex(random_bytes(4)),0,4));
$orderId = 'KS-' . $now->format('ymd') . '-' . $random;

// Payment proof upload
if (!isset($_FILES['paymentProof']) || $_FILES['paymentProof']['error'] !== UPLOAD_ERR_OK) fail('Bukti transfer wajib diunggah.');
$file = $_FILES['paymentProof'];
if ((int)$file['size'] <= 0 || (int)$file['size'] > MAX_FILE_SIZE) fail('Bukti transfer maksimal 10 MB.');
$ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
$allowedExts = ['jpg','jpeg','png','webp','pdf'];
if (!in_array($ext, $allowedExts, true)) fail('Format bukti transfer harus JPG, PNG, WEBP, atau PDF.');

// Local upload (for email attachment fallback)
$uploadDir = __DIR__ . '/data/order-proofs';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true)) fail('Folder penyimpanan bukti pembayaran tidak dapat dibuat.', 500);
$safeName = $orderId . '-' . preg_replace('/[^A-Za-z0-9._-]/','_',basename((string)$file['name']));
$proofPath = $uploadDir . '/' . $safeName;
if (!move_uploaded_file($file['tmp_name'], $proofPath)) fail('Bukti transfer gagal disimpan. Silakan coba lagi.', 500);

// Store order record (Supabase primary, local JSON fallback)
$record = [
  'orderId'=>$orderId,'createdAt'=>$now->format(DateTime::ATOM),'orderDate'=>$orderDate,'dueDate'=>$dueDate,
  'package'=>$package,'base'=>$base,'setup'=>$setup,'voucher'=>$voucherUsed,'discount'=>$voucherDiscount,'total'=>$total,
  'customerName'=>$name,'businessName'=>$business,'whatsapp'=>$wa,'email'=>$email,'domain'=>$domain,
  'paymentDate'=>$paymentDate,'paymentProof'=>$safeName,'status'=>'Menunggu Verifikasi Pembayaran'
];

$supabaseError = null;

if ($supabase->isConfigured()) {
    $fileContent = file_get_contents($proofPath);
    $fileMime = 'application/octet-stream';
    if ($ext === 'jpg' || $ext === 'jpeg') $fileMime = 'image/jpeg';
    elseif ($ext === 'png') $fileMime = 'image/png';
    elseif ($ext === 'webp') $fileMime = 'image/webp';
    elseif ($ext === 'pdf') $fileMime = 'application/pdf';

    $storageBucket = $supabase->getPaymentProofsBucket() ?: 'order-proofs';
    $storagePath = $orderId . '/' . $safeName;
    $uploadResult = $supabase->uploadFile($storageBucket, $storagePath, $fileContent, $fileMime);

    $paymentProofUrl = null;
    if ($uploadResult['status'] >= 200 && $uploadResult['status'] < 300) {
        $paymentProofUrl = $storagePath;
    }

    $packageMap = ['Starter'=>'starter','Bronze'=>'bronze','Silver'=>'silver','Gold'=>'gold'];
    $pkgCode = $packageMap[$package] ?? strtolower($package);
    $pkgRecord = $supabase->findPackage($pkgCode);
    $packageId = $pkgRecord['id'] ?? null;

    $clientId = $supabase->findOrCreateClient([
        'full_name'     => $name,
        'business_name' => $business,
        'whatsapp'      => $wa,
        'email'         => $email,
        'domain'        => $domain ?: null,
    ]);

    if ($clientId && $packageId) {
        $orderPayload = [
            'order_number'    => $orderId,
            'client_id'       => $clientId,
            'package_id'      => $packageId,
            'order_date'      => $orderDate,
            'due_date'        => $dueDate,
            'base_price'      => $base,
            'setup_fee'       => $setup,
            'voucher_code'    => $voucherUsed ?: null,
            'voucher_discount' => $voucherDiscount,
            'total_amount'    => $total,
            'status'          => 'pending_payment',
            'notes'           => 'Order via website (pemesanan.html)',
        ];
        $orderRecord = $supabase->insert('orders', $orderPayload);

        if ($orderRecord && isset($orderRecord['id'])) {
            $paymentPayload = [
                'order_id'     => $orderRecord['id'],
                'payment_date' => $paymentDate,
                'amount'       => $total,
                'proof_path'   => $paymentProofUrl ?: $safeName,
                'status'       => 'pending',
            ];
            $supabase->insert('payments', $paymentPayload);
        }
    } else {
        $supabaseError = 'Gagal membuat record client/order di Supabase.';
    }
}

// Fallback: save to local JSON if Supabase is unavailable or failed
if (!$supabase->isConfigured() || $supabaseError) {
    $dataFile = __DIR__ . '/data/orders.json';
    $orders = is_file($dataFile) ? json_decode((string)file_get_contents($dataFile), true) : [];
    if (!is_array($orders)) $orders = [];
    $orders[] = $record;
    if (@file_put_contents($dataFile, json_encode($orders, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        error_log('Khania Studio Order: Failed to write to local JSON fallback.');
    }
} else {
    error_log('Khania Studio Order: Saved to Supabase. ' . ($supabaseError ?: ''));
}

$subject = 'Pesanan Website ' . $orderId . ' — ' . $package . ' — ' . $business;
$bodyText = "KHANIA STUDIO — PESANAN WEBSITE\r\n\r\n";
$bodyText .= "Nomor Order: $orderId\r\nTanggal Order: " . $now->format('d-m-Y H:i') . " WIB\r\nBatas Pembayaran: " . $due->format('d-m-Y H:i') . " WIB\r\n\r\n";
$bodyText .= "Paket: $package\r\nSewa Hosting + Domain 1 Tahun + Desain Website: " . orderRupiah($base) . "\r\nBiaya Setup untuk 1 tahun pertama: " . orderRupiah($setup) . "\r\nVoucher: " . ($voucherUsed ?: '-') . "\r\nDiskon: " . orderRupiah($voucherDiscount) . "\r\nTOTAL: " . orderRupiah($total) . "\r\n\r\n";
$bodyText .= "Pemesan: $name\r\nBisnis: $business\r\nWhatsApp: $wa\r\nEmail: $email\r\nDomain: " . ($domain ?: '-') . "\r\nTanggal Pembayaran: $paymentDate\r\nStatus: Menunggu Verifikasi Pembayaran\r\n";
$bodyHtml = '<!doctype html><html lang="id"><head><meta charset="utf-8"><style>body{font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a;padding:20px}.card{max-width:700px;margin:auto;background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden}.h{background:#0f172a;color:#fff;padding:22px;border-bottom:4px solid #d4af37}.c{padding:22px}.row{padding:8px 0;border-bottom:1px solid #f1f5f9}.total{font-size:20px;font-weight:bold}.f{background:#f1f5f9;padding:14px;text-align:center;color:#64748b;font-size:12px}</style></head><body><div class="card"><div class="h"><h2>Pesanan Website Khania Studio</h2><div>Order ' . esc($orderId) . '</div></div><div class="c">';
$rows=[['Paket',$package],['Nama Pemesan',$name],['Nama Bisnis',$business],['WhatsApp',$wa],['Email',$email],['Domain',$domain?:'-'],['Hosting + Domain + Desain',orderRupiah($base)],['Setup Tahun Pertama',orderRupiah($setup)],['Voucher',$voucherUsed?:'-'],['Diskon',orderRupiah($voucherDiscount)],['TOTAL',orderRupiah($total)],['Tanggal Pembayaran',$paymentDate],['Batas Pembayaran',$due->format('d-m-Y H:i').' WIB'],['Status','Menunggu Verifikasi Pembayaran']];
foreach($rows as $r){$cls=$r[0]==='TOTAL'?' class="row total"':' class="row"';$bodyHtml.='<div'.$cls.'><strong>'.esc($r[0]).'</strong><br>'.esc($r[1]).'</div>';}
$bodyHtml.='</div><div class="f">Bukti transfer terlampir. Email ini dikirim otomatis oleh sistem Khania Studio.</div></div></body></html>';

$mail = new PHPMailer();
$mail->isSMTP(); $mail->Host=SMTP_HOST; $mail->SMTPAuth=true; $mail->Username=SMTP_USERNAME; $mail->Password=SMTP_PASSWORD; $mail->SMTPSecure=PHPMailer::ENCRYPTION_STARTTLS; $mail->Port=SMTP_PORT; $mail->CharSet='UTF-8'; $mail->Encoding='quoted-printable';
$mail->setFrom(ADMIN_EMAIL,'Khania Studio Order'); $mail->addAddress(ADMIN_EMAIL,'Khania Studio Admin'); if (defined('INTERNAL_CC') && INTERNAL_CC) $mail->addCC(INTERNAL_CC); $mail->addReplyTo($email,$name); $mail->addAttachment($proofPath,$safeName); $mail->isHTML(true); $mail->Subject=$subject; $mail->Body=$bodyHtml; $mail->AltBody=$bodyText;
if (!$mail->send()) { error_log('Khania Studio Order SMTP Error: '.$mail->ErrorInfo); }

header('Location: konfirmasi-pemesanan.html?ref=' . rawurlencode($orderId)); exit;
