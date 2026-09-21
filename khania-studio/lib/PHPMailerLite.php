<?php
namespace PHPMailer\PHPMailer;

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
