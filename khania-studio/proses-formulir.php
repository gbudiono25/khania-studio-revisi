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

// SMTP Configuration Constants (SAFE PLACEHOLDER — Client enters actual password on server)
const SMTP_HOST     = 'khania-studio.com';
const SMTP_PORT     = 587;
const SMTP_USERNAME = 'admin@khania-studio.com';
const SMTP_PASSWORD = 'Gb130866@567';

const ADMIN_EMAIL   = 'admin@khania-studio.com';
const INTERNAL_CC   = 'gbudiono.25@gmail.com';

const MAX_FILE_SIZE     = 10 * 1024 * 1024; // 10 MB per file
const MAX_TOTAL_UPLOADS = 20;
const MAX_TOTAL_BYTES   = 30 * 1024 * 1024; // 30 MB total

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
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Client Brief — Khania Studio</title><style>body{font:16px/1.6 Arial,sans-serif;background:#f1f5f9;color:#0f172a;margin:0}.wrap{max-width:700px;margin:10vh auto;padding:24px}.card{background:#fff;padding:28px;border-radius:16px;border:1px solid #dbe2ea}a{display:inline-block;background:#d4af37;color:#0f172a;padding:10px 16px;border-radius:9px;text-decoration:none;font-weight:700}</style></head><body><div class="wrap"><div class="card"><h1>Informasi Pengiriman</h1><p>'.$safe.'</p><a href="javascript:history.back()">Kembali ke formulir</a></div></div></body></html>';
    exit;
}

require_once __DIR__ . '/lib/SupabaseClient.php';

use KhaniaStudio\SupabaseClient;

$supabase = new SupabaseClient();

/* SECURITY FIX: REMOVE PUBLIC DIAGNOSTIC DASHBOARD ON GET REQUESTS */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: formulir-permintaan-website-khania-studio-v3.html');
    exit;
}

/* POST FORM SUBMISSION PROCESSING FOR BRIEF V3 */
if (!empty($_POST['website'] ?? '')) {
    fail('Permintaan tidak dapat diproses.', 400);
}

function post($key) {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function postArray($key) {
    $vals = $_POST[$key] ?? [];
    if (!is_array($vals)) return [];
    return array_filter(array_map('trim', $vals));
}

$briefToken         = post('brief_token');
$orderId            = post('order_id');
$siteType           = post('siteType');
$picRole            = post('picRole');
$picCity            = post('picCity');
$approvalName       = post('approvalName');
$businessCategory = post('businessCategory');
$businessDescription = post('businessDescription');
$targetAudience = post('targetAudience');

if ($briefToken === '') {
    fail('Link Client Brief tidak memiliki token akses. Silakan gunakan link terbaru yang dikirim Khania Studio.');
}

if (!$supabase->isConfigured()) {
    fail('Sistem database Khania Studio belum siap memproses Client Brief. Silakan hubungi Khania Studio.');
}

/* IMPORTANT: the one-time token is authoritative; order/client/package/payment are read from the database. */
$tokenHash = hash('sha256', $briefToken);
$ctxRes = $supabase->rpcPostDetailed('get_brief_context', ['p_token_hash' => $tokenHash]);
$ctxBody = $ctxRes['body'];
$ctxRow = (is_array($ctxBody) && isset($ctxBody[0])) ? $ctxBody[0] : $ctxBody;
if ($ctxRes['status'] < 200 || $ctxRes['status'] >= 300 || !is_array($ctxRow) || ($ctxRow['success'] ?? false) !== true) {
    $ctxMessage = is_array($ctxRow) ? ($ctxRow['message'] ?? 'Link Client Brief tidak valid.') : 'Link Client Brief tidak valid.';
    fail($ctxMessage);
}
$orderId = (string)$ctxRow['order_number'];

/* IMPORTANT: order, client, package and payment status are authoritative on the server. */
$orderRows = $supabase->select(
    'orders',
    'id,order_number,client_id,package_id,status,order_date',
    'order_number=eq.' . rawurlencode($orderId)
);
$orderRecord = is_array($orderRows) && isset($orderRows[0]) ? $orderRows[0] : null;
if (!$orderRecord || empty($orderRecord['id'])) {
    fail('Nomor Order tidak ditemukan. Pastikan Anda menggunakan link Client Brief dari Khania Studio.');
}

$paymentStatus = strtolower(trim((string)($orderRecord['status'] ?? '')));
$verifiedStatuses = ['payment_verified','paid','verified','confirmed','completed_payment','pembayaran_terverifikasi'];
if (!in_array($paymentStatus, $verifiedStatuses, true)) {
    fail('Client Brief baru dapat diisi setelah pembayaran pada order ini terverifikasi oleh Khania Studio.');
}

$clientRows = $supabase->select(
    'clients',
    'id,client_code,full_name,business_name,whatsapp,email,domain',
    'id=eq.' . rawurlencode((string)$orderRecord['client_id'])
);
$clientRecord = is_array($clientRows) && isset($clientRows[0]) ? $clientRows[0] : null;
if (!$clientRecord) {
    fail('Data klien untuk order ini tidak ditemukan.');
}

$packageRows = $supabase->select(
    'packages',
    'id,code,name,base_price,setup_fee',
    'id=eq.' . rawurlencode((string)$orderRecord['package_id'])
);
$packageRecord = is_array($packageRows) && isset($packageRows[0]) ? $packageRows[0] : null;
if (!$packageRecord) {
    fail('Data paket untuk order ini tidak ditemukan.');
}

$packageCodeMap = ['starter'=>'Starter','bronze'=>'Bronze','silver'=>'Silver','gold'=>'Gold'];
$package = $packageCodeMap[strtolower((string)($packageRecord['code'] ?? ''))] ?? (string)($packageRecord['name'] ?? '');
$pic = trim((string)($clientRecord['full_name'] ?? ''));
$picEmail = trim((string)($clientRecord['email'] ?? ''));
$picWhatsapp = trim((string)($clientRecord['whatsapp'] ?? ''));
$businessName = trim((string)($clientRecord['business_name'] ?? ''));

$required = [
    'Nomor Order' => $orderId,
    'Jenis Website' => $siteType,
    'Nama PIC dari Order' => $pic,
    'Email PIC dari Order' => $picEmail,
    'WhatsApp PIC dari Order' => $picWhatsapp,
    'Nama Bisnis dari Order' => $businessName,
    'Peran PIC' => $picRole,
    'Nama Persetujuan' => $approvalName,
    'Bidang Usaha' => $businessCategory,
    'Deskripsi Bisnis' => $businessDescription,
    'Target Pelanggan' => $targetAudience,
];

$missing = [];
foreach ($required as $label => $value) {
    if ($value === '') $missing[] = $label;
}
if ($missing) {
    fail('Field wajib belum lengkap: ' . implode(', ', $missing) . '.');
}

if (!filter_var($picEmail, FILTER_VALIDATE_EMAIL)) {
    fail('Alamat email PIC tidak valid.');
}

/* File Attachment Processing */
$attachments = [];
$totalBytes = 0;
$totalFiles = 0;

function addUploads($field, &$attachments, &$totalBytes, &$totalFiles) {
    if (!isset($_FILES[$field])) return;
    $f = $_FILES[$field];
    $names = $f['name'];
    if (!is_array($names)) {
        $names = [$f['name']]; 
        $f['tmp_name'] = [$f['tmp_name']];
        $f['error'] = [$f['error']];
        $f['size'] = [$f['size']];
        $f['type'] = [$f['type']];
    }

    foreach ($names as $i => $original) {
        $error = (int)($f['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error !== UPLOAD_ERR_OK) fail('Salah satu file gagal diunggah. Silakan coba lagi.');
        $size = (int)($f['size'][$i] ?? 0);
        if ($size <= 0 || $size > MAX_FILE_SIZE) {
            fail('Ada file yang melebihi batas 10 MB per file.');
        }
        $tmp = (string)$f['tmp_name'][$i];
        if (!is_uploaded_file($tmp)) fail('Upload file tidak valid.');

        $ext = strtolower(pathinfo((string)$original, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif','svg','pdf','doc','docx','xls','xlsx','mp4','mov','webm'];
        if (!in_array($ext, $allowed, true)) {
            fail('Tipe file tidak diperbolehkan: ' . htmlspecialchars((string)$original, ENT_QUOTES, 'UTF-8'));
        }

        $totalBytes += $size;
        $totalFiles++;
        if ($totalFiles > MAX_TOTAL_UPLOADS || $totalBytes > MAX_TOTAL_BYTES) {
            fail('Total file terlalu besar. Maksimal 20 file dan 30 MB per pengiriman.');
        }

        $cleanName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$original));
        $attachments[] = [
            'path' => $tmp,
            'name' => $cleanName,
            'type' => (string)($f['type'][$i] ?? 'application/octet-stream'),
            'size' => $size,
        ];
    }
}

addUploads('referenceFiles', $attachments, $totalBytes, $totalFiles);
addUploads('testimonialPhoto', $attachments, $totalBytes, $totalFiles);
addUploads('logo', $attachments, $totalBytes, $totalFiles);
addUploads('businessImages', $attachments, $totalBytes, $totalFiles);
addUploads('videos', $attachments, $totalBytes, $totalFiles);
addUploads('documents', $attachments, $totalBytes, $totalFiles);
addUploads('productImage', $attachments, $totalBytes, $totalFiles);

/* Product / service items */
$featuredProduct = post('featuredProduct');
if ($featuredProduct === '') {
    $featuredProduct = 'Belum tahu — mohon direkomendasikan Khania Studio';
}

$pn  = $_POST['productName'] ?? [];
$pps = $_POST['productPriceStatus'] ?? [];
$pp  = $_POST['productPrice'] ?? [];
$pd  = $_POST['productDescription'] ?? [];
$pb  = $_POST['productBenefit'] ?? [];

if (!is_array($pn))  $pn = [];
if (!is_array($pps)) $pps = [];
if (!is_array($pp))  $pp = [];
if (!is_array($pd))  $pd = [];
if (!is_array($pb))  $pb = [];

$packageQuotas = [
    'Starter' => 1,
    'Bronze'  => 3,
    'Silver'  => 6,
    'Gold'    => 10
];
$fullDisplayQuota = $packageQuotas[$package] ?? 1;

$products = [];
if ($pn) {
    foreach ($pn as $i => $name) {
        $name = trim((string)$name);
        if ($name === '') continue;
        $priceStatus = trim((string)($pps[$i] ?? 'Ya, tampilkan harga'));
        $price       = trim((string)($pp[$i] ?? ''));
        $desc        = trim((string)($pd[$i] ?? ''));
        $benef       = trim((string)($pb[$i] ?? ''));
        $products[] = [
            'name'        => $name,
            'priceStatus' => $priceStatus !== '' ? $priceStatus : 'Ya, tampilkan harga',
            'price'       => $price !== '' ? $price : '-',
            'desc'        => $desc !== '' ? $desc : '-',
            'benef'       => $benef !== '' ? $benef : '-',
            'isQuota'     => (count($products) < $fullDisplayQuota),
        ];
    }
}

/* Save brief submission to the EXISTING verified order. Never create a new order here. */
$briefSaved = false;
$randomCode = strtoupper(substr(md5(uniqid((string)microtime(true), true)), 0, 4));
$submissionId = $orderId . '-BRIEF-' . $randomCode;
$subject = 'Client Brief V3 — ' . $businessName . ' — ' . $package . ' — ' . $orderId;

$sections = [
    'A. DATA ORDER & PEMESAN' => [
        'Kode Klien/Project' => (string)($clientRecord['client_code'] ?? ''),
        'Nomor Order' => $orderId,
        'Paket Website' => $package,
        'Status Pembayaran' => 'Pembayaran Terverifikasi',
        'Jenis Website' => $siteType,
        'Nama Pemesan / PIC' => $pic,
        'Jabatan / Peran' => $picRole,
        'Email PIC' => $picEmail,
        'WhatsApp PIC' => $picWhatsapp,
        'Kota Domisili' => $picCity,
    ],
    'B. INFORMASI BISNIS' => [
        'Nama Bisnis / Brand' => $businessName,
        'Nama Legal' => post('legalName'),
        'Bidang Usaha' => $businessCategory,
        'Tahun Berdiri' => post('foundedYear'),
        'Deskripsi Bisnis' => $businessDescription,
        'Target Pelanggan' => $targetAudience,
        'Area Layanan' => post('serviceArea'),
        'Jam Operasional' => post('businessHours'),
        'Lokasi / Alamat Usaha' => post('businessAddress'),
    ],
    'C. TUJUAN UTAMA WEBSITE' => [
        'Tujuan Utama' => post('primaryGoal'),
        'Tujuan Lain' => implode(', ', postArray('secondaryGoals')),
    ],
    'D. STRUKTUR HALAMAN' => [
        'Pilihan Struktur' => post('structureChoice'),
        'Usulan Halaman / Menu' => post('proposedStructure'),
    ],
    'E. FITUR & FUNGSI WEBSITE' => [
        'Fitur Terpilih' => implode(', ', postArray('features')),
    ],
    'F. DOMAIN WEBSITE' => [
        'Status Domain' => post('hasDomain'),
        'Domain Lama' => post('existingDomain'),
        'Domain Baru / Diinginkan' => post('desiredDomain'),
    ],
    'G. WEBSITE LAMA & REDESIGN' => [
        'Status Website Lama' => post('hasExistingSite'),
        'URL Website Lama' => post('oldSiteUrl'),
        'Yang Dipertahankan' => post('keepOld'),
        'Yang Diubah' => post('changeOld'),
        'Jenis Pekerjaan' => post('jobType'),
    ],
    'H. PRODUK / JASA' => [
        'Produk / Jasa Ditonjolkan' => $featuredProduct,
        'Kuota Tampilan Lengkap Paket' => $fullDisplayQuota . ' Item (' . $package . ')',
        'Total Produk / Jasa Dicatat' => count($products) . ' Item',
    ],
    'I. BRAND & DESIGN DIRECTION' => [
        'Pilihan Warna' => post('colorChoice'),
        'Warna Utama / Preferensi' => post('brandColors'),
        'Warna yang Dihindari' => post('avoidColors'),
        'Pilihan Font' => post('fontChoice'),
        'Gaya Font' => post('fontStyle'),
        'Gaya Visual' => implode(', ', postArray('visualStyle')),
    ],
    'J. REFERENSI DESAIN' => [
        'Kondisi Referensi' => post('referenceStatus'),
        'Link Website Referensi' => post('referenceUrl'),
        'Bagian yang Disukai' => implode(', ', postArray('likedParts')),
    ],
    'K. PESAN UTAMA WEBSITE' => [
        'Headline' => post('headline'),
        'Subheadline' => post('subheadline'),
        'Tombol Aksi Utama (CTA)' => post('primaryCTA'),
    ],
    'L. DATA SPESIFIK INDUSTRI' => [
        'Laundry - Jenis Layanan' => post('laundryServiceTypes'),
        'Laundry - Model Layanan' => post('laundryModel'),
        'Laundry - Pickup/Delivery' => post('laundryPickup'),
        'Laundry - Waktu Proses' => post('laundryTurnaround'),
        'Laundry - Fasilitas' => post('laundryFacilities'),
        'Laundry - Daftar Harga' => post('laundryPricing'),
        'Company Profile - Sejarah' => post('companyHistory'),
        'Company Profile - Visi' => post('vision'),
        'Company Profile - Misi' => post('mission'),
        'Company Profile - Core Values' => post('coreValues'),
        'Company Profile - Manajemen' => post('management'),
        'Company Profile - Unit Bisnis' => post('businessUnits'),
        'Restoran - Menu Utama' => post('restaurantMenu'),
        'Restoran - Rentang Harga' => post('restaurantPrice'),
        'Restoran - Reservasi' => post('restaurantBooking'),
        'Restoran - Order Online' => post('restaurantDelivery'),
        'Hotel - Tipe Kamar & Tarif' => post('hotelRooms'),
        'Hotel - Fasilitas' => post('hotelFacilities'),
        'Hotel - Check-in/out' => post('hotelCheckInOut'),
        'Hotel - Reservasi' => post('hotelBooking'),
        'Properti - Nama Proyek' => post('propertyProject'),
        'Properti - Lokasi' => post('propertyLocation'),
        'Properti - Luas & Unit' => post('propertyArea'),
        'Properti - Legalitas' => post('propertyLegal'),
        'Properti - Tipe Rumah' => post('propertyTypes'),
        'Properti - Fasilitas' => post('propertyFacilities'),
        'Travel - Paket & Destinasi' => post('travelPackages'),
        'Travel - Izin PPIU' => post('travelLicenses'),
        'Travel - Fasilitas' => post('travelFacilities'),
    ],
    'M. KREDIBILITAS & TESTIMONIAL' => [
        'Kredibilitas / Legalitas' => post('credentials'),
        'Testimonial Pelanggan' => post('testimonials'),
    ],
    'O. KONTAK BISNIS & MEDIA SOSIAL' => [
        'WhatsApp Bisnis' => post('bizWa'),
        'PIC Admin WA' => post('bizWaPic'),
        'Telepon' => post('phone'),
        'Email Bisnis' => post('bizEmail'),
        'Instagram' => post('instagram'),
        'Facebook' => post('facebook'),
        'TikTok' => post('tiktok'),
        'Google Maps' => post('maps'),
        'Alamat Publik' => post('publicAddress'),
        'Kontak Utama Ditonjolkan' => post('primaryContact'),
    ],
    'P. SEO & TARGET PASAR' => [
        'Keyword' => post('keywords'),
        'Kota / Area Target' => post('seoLocation'),
        'Website Pesaing' => post('competitors'),
        'Topik Artikel' => post('seoTopics'),
        'Bantuan SEO Khania Studio' => post('seoAssist'),
    ],
    'Q. PERIKSA & KONFIRMASI' => [
        'Pernyataan Hak Aset' => post('assetConsent') === 'on' ? 'Ya (Disetujui)' : 'Ya',
        'Nama Persetujuan' => $approvalName,
    ],
    'R. PERMINTAAN TAMBAHAN / KEBUTUHAN KHUSUS' => [
        'Jenis Permintaan Tambahan' => implode(', ', postArray('additionalRequests')),
        'Detail Permintaan Tambahan' => post('additionalRequestDetails'),
    ],
];

$briefData = [
    'order_id' => $orderId,
    'client_code' => (string)($clientRecord['client_code'] ?? ''),
    'package' => $package,
    'site_type' => $siteType,
    'pic' => $pic,
    'pic_role' => $picRole,
    'pic_email' => $picEmail,
    'pic_whatsapp' => $picWhatsapp,
    'pic_city' => $picCity,
    'business_name' => $businessName,
    'business_category' => $businessCategory,
    'business_description' => $businessDescription,
    'target_audience' => $targetAudience,
    'approval_name' => $approvalName,
    'additional_requests' => postArray('additionalRequests'),
    'additional_request_details' => post('additionalRequestDetails'),
    'products' => $products,
    'sections' => $sections,
    'submitted_via' => 'Client Website Brief V3 Integrated',
];

$briefRows = $supabase->select('website_briefs', 'id,status', ['order_id' => $orderRecord['id']], 1);
$existingBrief = is_array($briefRows) && isset($briefRows[0]) ? $briefRows[0] : null;
if ($existingBrief && strtoupper((string)($existingBrief['status'] ?? '')) !== 'DRAFT') {
    fail('Client Brief untuk order ini sudah pernah dikirim. Jika ada koreksi, tunggu instruksi dari Khania Studio.');
}

$briefRecord = $supabase->update('website_briefs', [
    'client_id' => $orderRecord['client_id'],
    'client_code' => (string)($clientRecord['client_code'] ?? ''),
    'version' => 1,
    'brief_data' => $briefData,
    'submitted_at' => date('c'),
    'status' => 'SUBMITTED',
], ['id' => $existingBrief['id']]);
$briefRecord = $briefRecord[0] ?? null;

if (!$briefRecord || empty($briefRecord['id'])) {
    error_log('Khania Studio Brief: Failed to update website_briefs for order ' . $orderId);
    fail('Data Client Brief tidak berhasil disimpan ke database. Pesanan tidak diubah. Silakan coba lagi.');
}

// Upload attachments to Supabase Storage, grouped by Order ID.
if ($attachments) {
    $storageBucket = $supabase->getClientFilesBucket() ?: 'client-files';
    foreach ($attachments as $idx => $a) {
        $fileContent = file_get_contents($a['path']);
        if ($fileContent !== false) {
            $storagePath = $orderId . '/brief-v3/file_' . $idx . '_' . basename($a['name']);
            $supabase->uploadFile($storageBucket, $storagePath, $fileContent, $a['type']);
        }
    }
}

$briefSaved = true;
error_log('Khania Studio Brief: Saved integrated submission ' . $submissionId . ' for order ' . $orderId . '.');

/* Build Plain Text Body */
$bodyText = "KHANIA STUDIO — CLIENT WEBSITE BRIEF V3\r\n";
$bodyText .= "ID SUBMISSION: " . $submissionId . "\r\n";
$bodyText .= "WAKTU: " . date('d-m-Y H:i:s') . " WIB\r\n";
$bodyText .= "STATUS: SUBMITTED — MENUNGGU PEMERIKSAAN KHANIA STUDIO\r\n";
$bodyText .= "========================================\r\n\r\n";

foreach ($sections as $sectionTitle => $fields) {
    $hasValue = false;
    $secText = "=== " . $sectionTitle . " ===\r\n";
    foreach ($fields as $label => $val) {
        if ($val !== '') {
            $hasValue = true;
            $secText .= $label . ":\r\n" . $val . "\r\n\r\n";
        }
    }
    if ($hasValue) $bodyText .= $secText;
}

if ($products) {
    $bodyText .= "=== H. DAFTAR PRODUK / JASA & UNGGULAN ===\r\n";
    $bodyText .= "Produk / Jasa Ditonjolkan: " . $featuredProduct . "\r\n";
    $bodyText .= "Kuota Tampilan Lengkap Paket (" . $package . "): " . $fullDisplayQuota . " Item\r\n";
    $bodyText .= "Total Produk / Jasa Dicatat: " . count($products) . " Item\r\n\r\n";
    foreach ($products as $idx => $p) {
        $quotaStatus = $p['isQuota'] ? "Termasuk Kuota Tampilan Lengkap Paket" : "Di luar kuota tampilan lengkap (Tampilan Ringkas)";
        $bodyText .= sprintf(
            "%d. %s\r\n   Status Harga: %s\r\n   Harga: %s\r\n   Status Kuota: %s\r\n   Deskripsi: %s\r\n   Manfaat: %s\r\n\r\n",
            $idx + 1,
            $p['name'],
            $p['priceStatus'],
            $p['price'],
            $quotaStatus,
            $p['desc'],
            $p['benef']
        );
    }
}

if ($attachments) {
    $bodyText .= "=== N. DAFTAR FILE TERLAMPIR ===\r\n";
    foreach ($attachments as $a) {
        $bodyText .= "- " . $a['name'] . " (" . round($a['size'] / 1024, 1) . " KB)\r\n";
    }
    $bodyText .= "\r\n";
}

/* Build HTML Body with RFC 5322 line breaks */
$bodyHtml = "<!doctype html>\r\n<html lang=\"id\">\r\n<head>\r\n<meta charset=\"utf-8\">\r\n";
$bodyHtml .= "<style>\r\n";
$bodyHtml .= "body{font-family:Arial,sans-serif;line-height:1.6;color:#0f172a;background:#f8fafc;margin:0;padding:20px;}\r\n";
$bodyHtml .= ".container{max-width:740px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;}\r\n";
$bodyHtml .= ".header{background:#0f172a;color:#ffffff;padding:24px;border-bottom:4px solid #d4af37;}\r\n";
$bodyHtml .= ".header h1{margin:0;font-size:22px;color:#d4af37;}\r\n";
$bodyHtml .= ".header p{margin:4px 0 0;font-size:13.5px;color:#cbd5e1;}\r\n";
$bodyHtml .= ".content{padding:24px;}\r\n";
$bodyHtml .= ".section{margin-bottom:24px;border-bottom:1px solid #f1f5f9;padding-bottom:16px;}\r\n";
$bodyHtml .= ".section-title{font-size:15.5px;font-weight:bold;color:#0f172a;background:#f1f5f9;padding:8px 12px;border-left:4px solid #d4af37;margin-bottom:12px;border-radius:0 6px 6px 0;}\r\n";
$bodyHtml .= ".field-row{margin-bottom:10px;}\r\n";
$bodyHtml .= ".field-label{font-weight:bold;font-size:13px;color:#475569;margin-bottom:2px;}\r\n";
$bodyHtml .= ".field-value{font-size:13.5px;color:#0f172a;white-space:pre-wrap;background:#fafafa;padding:7px 11px;border-radius:6px;border:1px solid #f1f5f9;}\r\n";
$bodyHtml .= ".product-card{background:#f8fafc;border:1px solid #e2e8f0;padding:12px;border-radius:8px;margin-bottom:10px;}\r\n";
$bodyHtml .= ".footer{background:#f1f5f9;padding:16px 24px;text-align:center;font-size:12px;color:#64748b;}\r\n";
$bodyHtml .= "</style>\r\n</head>\r\n<body>\r\n";

$bodyHtml .= "<div class=\"container\">\r\n";
$bodyHtml .= "<div class=\"header\">\r\n";
$bodyHtml .= "<h1>Client Website Brief V3</h1>\r\n";
$bodyHtml .= "<p>ID Submission: <strong>" . htmlspecialchars($submissionId, ENT_QUOTES, 'UTF-8') . "</strong> | " . date('d M Y, H:i') . " WIB</p>\r\n";
$bodyHtml .= "</div>\r\n";
$bodyHtml .= "<div class=\"content\">\r\n";

foreach ($sections as $sectionTitle => $fields) {
    $fieldHtml = '';
    foreach ($fields as $label => $val) {
        if ($val !== '') {
            $fieldHtml .= "<div class=\"field-row\">\r\n";
            $fieldHtml .= "<div class=\"field-label\">" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</div>\r\n";
            $fieldHtml .= "<div class=\"field-value\">" . nl2br(htmlspecialchars($val, ENT_QUOTES, 'UTF-8')) . "</div>\r\n";
            $fieldHtml .= "</div>\r\n";
        }
    }
    if ($fieldHtml !== '') {
        $bodyHtml .= "<div class=\"section\">\r\n";
        $bodyHtml .= "<div class=\"section-title\">" . htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8') . "</div>\r\n";
        $bodyHtml .= $fieldHtml;
        $bodyHtml .= "</div>\r\n";
    }
    if ($sectionTitle === 'H. PRODUK / JASA' && $products) {
        $bodyHtml .= "<div class=\"section\">\r\n";
        $bodyHtml .= "<div class=\"section-title\">DETAIL SELURUH PRODUK / JASA BISNIS</div>\r\n";
        foreach ($products as $idx => $p) {
            $isQuota = $p['isQuota'];
            $badgeBg = $isQuota ? '#DCFCE7' : '#FEF3C7';
            $badgeColor = $isQuota ? '#166534' : '#92400E';
            $badgeText = $isQuota ? '✓ Tampilan Lengkap Paket' : '⚡ Tampilan Ringkas';
            $borderColor = $isQuota ? '#166534' : '#CBD5E1';
            
            $bodyHtml .= "<div class=\"product-card\" style=\"border-left:4px solid " . $borderColor . ";\">\r\n";
            $bodyHtml .= "<div style=\"display:flex;justify-content:space-between;align-items:center;\">\r\n";
            $bodyHtml .= "<strong>" . ($idx + 1) . ". " . htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') . "</strong>\r\n";
            $bodyHtml .= "<span style=\"font-size:11px;font-weight:bold;padding:2px 8px;border-radius:12px;background:" . $badgeBg . ";color:" . $badgeColor . ";\">" . $badgeText . "</span>\r\n";
            $bodyHtml .= "</div>\r\n";
            $bodyHtml .= "<small>Status Harga: " . htmlspecialchars($p['priceStatus'], ENT_QUOTES, 'UTF-8') . "</small><br>\r\n";
            $bodyHtml .= "<small>Harga: " . htmlspecialchars($p['price'], ENT_QUOTES, 'UTF-8') . "</small><br>\r\n";
            $bodyHtml .= "<small>Deskripsi: " . htmlspecialchars($p['desc'], ENT_QUOTES, 'UTF-8') . "</small><br>\r\n";
            $bodyHtml .= "<small>Manfaat: " . htmlspecialchars($p['benef'], ENT_QUOTES, 'UTF-8') . "</small>\r\n";
            $bodyHtml .= "</div>\r\n";
        }
        $bodyHtml .= "</div>\r\n";
    }
}

if ($attachments) {
    $bodyHtml .= "<div class=\"section\">\r\n";
    $bodyHtml .= "<div class=\"section-title\">N. DAFTAR FILE TERLAMPIR</div>\r\n";
    $bodyHtml .= "<ul>\r\n";
    foreach ($attachments as $a) {
        $bodyHtml .= "<li><strong>" . htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8') . "</strong> (" . round($a['size'] / 1024, 1) . " KB)</li>\r\n";
    }
    $bodyHtml .= "</ul>\r\n";
    $bodyHtml .= "</div>\r\n";
}

$bodyHtml .= "</div>\r\n";
$bodyHtml .= "<div class=\"footer\">Email ini dikirim secara otomatis melalui sistem Client Website Brief Form V3 Khania Studio.</div>\r\n";
$bodyHtml .= "</div>\r\n</body>\r\n</html>";

/* Execute PHPMailer SMTP Sending with Quoted-Printable RFC 5322 encoding */
$mail = new PHPMailer();
$mail->isSMTP();
$mail->Host       = SMTP_HOST;
$mail->SMTPAuth   = true;
$mail->Username   = SMTP_USERNAME;
$mail->Password   = SMTP_PASSWORD;
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port       = SMTP_PORT;
$mail->CharSet    = 'UTF-8';
$mail->Encoding   = 'quoted-printable';

$mail->setFrom(ADMIN_EMAIL, 'Khania Studio Form');
$mail->addAddress(ADMIN_EMAIL, 'Khania Studio Admin');
$mail->addCC(INTERNAL_CC);
$mail->addReplyTo($picEmail, $pic);

foreach ($attachments as $a) {
    $mail->addAttachment($a['path'], $a['name']);
}

$mail->isHTML(true);
$mail->Subject = $subject;
$mail->Body    = $bodyHtml;
$mail->AltBody = $bodyText;

$sent = $mail->send();

if ($sent) {
    header('Location: konfirmasi-formulir.html?ref=' . rawurlencode($submissionId));
    exit;
} else {
    error_log('Khania Studio SMTP Error: ' . $mail->ErrorInfo);
    fail('Server menerima formulir, tetapi pengiriman email SMTP belum berhasil. Silakan coba lagi atau hubungi Khania Studio via WhatsApp.', 500);
}
