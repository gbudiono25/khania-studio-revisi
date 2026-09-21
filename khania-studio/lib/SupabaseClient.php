<?php

/**
 * KHANIA STUDIO — Supabase PHP Client
 *
 * Lightweight REST API (PostgREST) + Storage API client.
 * Uses cURL for maximum compatibility with shared hosting (Rumahweb).
 */

namespace KhaniaStudio;

require_once __DIR__ . '/env.php';

loadEnv(__DIR__ . '/../.env');

class SupabaseClient
{
    private string $url;
    private string $anonKey;
    private string $serviceRoleKey;
    private string $storageBucketProofs;
    private string $storageBucketFiles;

    public function __construct()
    {
        $this->url          = rtrim((string) getenv('SUPABASE_URL'), '/');
        $this->anonKey       = (string) getenv('SUPABASE_ANON_KEY');
        $this->serviceRoleKey = (string) getenv('SUPABASE_SERVICE_ROLE_KEY');
        $this->storageBucketProofs = (string) getenv('SUPABASE_STORAGE_BUCKET_PAYMENT_PROOFS');
        $this->storageBucketFiles  = (string) getenv('SUPABASE_STORAGE_BUCKET_CLIENT_FILES');
    }

    /**
     * Use the service role key (bypasses RLS) when available.
     * Falls back to anon key (RLS applies).
     */
    private function getAuthHeaders(): array
    {
        $apiKey = !empty($this->serviceRoleKey) ? $this->serviceRoleKey : $this->anonKey;
        return [
            'apikey: ' . $apiKey,
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'Prefer: return=representation',
        ];
    }

    /**
     * Perform an HTTP request to the Supabase REST API.
     *
     * @param string $method   GET|POST|PATCH|PUT|DELETE
     * @param string $table    Target table (e.g. "orders")
     * @param array  $params   Query parameters (select, filters, order, etc.)
     * @param mixed  $body     POST/PATCH body (array or null)
     * @return array{status:int, body:mixed}
     */
    private function request(string $method, string $table, array $params = [], $body = null): array
    {
        $url = $this->url . '/rest/v1/' . rawurlencode($table);

        if (!empty($params)) {
            $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            $url .= '?' . $queryString;
        }

        $ch = curl_init($url);

        $headers = $this->getAuthHeaders();
        $payload = null;

        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Length: ' . strlen($payload);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_CUSTOMREQUEST   => $method,
            CURLOPT_HTTPHEADER      => $headers,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_CONNECTTIMEOUT  => 10,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response     = curl_exec($ch);
        $httpStatus   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        $curlErrNo    = curl_errno($ch);
        curl_close($ch);

        if ($curlErrNo) {
            return [
                'status' => 0,
                'body'   => ['error' => 'cURL Error: ' . $curlError],
            ];
        }

        $decoded = json_decode($response, true);

        return [
            'status' => $httpStatus,
            'body'   => $decoded ?? $response,
        ];
    }

    /**
     * SELECT records from a table.
     *
     * @param string $table   Table name
     * @param string $select  Select string (default: "*")
     * @param array  $filters Associative array of column => value
     * @param int    $limit   Limit results
     * @return array Array of records (empty array on failure)
     */
    public function select(string $table, string $select = '*', array $filters = [], int $limit = 0): array
    {
        $params = ['select' => $select];

        foreach ($filters as $col => $val) {
            if ($val === null) {
                $params[$col . '=is'] = 'null';
            } else {
                $params[$col . '=eq'] = $val;
            }
        }

        if ($limit > 0) {
            $params['limit'] = $limit;
        }

        $res = $this->request('GET', $table, $params);

        if (!is_array($res['body'])) {
            return [];
        }

        return $res['body'];
    }

    /**
     * SELECT a single record.
     *
     * @return array|null Single record or null
     */
    public function selectOne(string $table, string $select = '*', array $filters = []): ?array
    {
        $results = $this->select($table, $select, $filters, 1);
        return $results[0] ?? null;
    }

    /**
     * INSERT a record into a table and return the inserted row(s).
     *
     * @param string $table  Table name
     * @param array  $data   Associative array of column => value
     * @return array|null    Inserted record or null on failure
     */
    public function insert(string $table, array $data): ?array
    {
        $res = $this->request('POST', $table, [], $data);

        if ($res['status'] >= 200 && $res['status'] < 300) {
            $body = $res['body'];
            if (is_array($body) && isset($body[0])) {
                return $body[0];
            }
            return $body;
        }

        return null;
    }

    /**
     * UPDATE records in a table.
     *
     * @return array Array of updated records
     */
    public function update(string $table, array $data, array $filters = []): array
    {
        $params = [];

        foreach ($filters as $col => $val) {
            if ($val === null) {
                $params[$col . '=is'] = 'null';
            } else {
                $params[$col . '=eq'] = $val;
            }
        }

        $res = $this->request('PATCH', $table, $params, $data);

        if (!is_array($res['body'])) {
            return [];
        }

        return $res['body'];
    }

    /**
     * Upload a file to Supabase Storage.
     *
     * @param string $bucket   Bucket name
     * @param string $path     File path within the bucket
     * @param string $content  File content (binary)
     * @param string $contentType MIME type
     * @return array{status:int, body:mixed}
     */
    public function uploadFile(string $bucket, string $path, string $content, string $contentType = 'application/octet-stream'): array
    {
        $url = $this->url . '/storage/v1/object/' . rawurlencode($bucket) . '/' . rawurlencode($path);

        $apiKey = !empty($this->serviceRoleKey) ? $this->serviceRoleKey : $this->anonKey;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_PUT             => true,
            CURLOPT_HTTPHEADER      => [
                'apikey: ' . $apiKey,
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: ' . $contentType,
                'Content-Length: ' . strlen($content),
            ],
            CURLOPT_TIMEOUT         => 60,
            CURLOPT_CONNECTTIMEOUT  => 10,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);

        $response = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        return [
            'status' => $httpStatus,
            'body'   => $decoded ?? $response,
        ];
    }

    /**
     * Get a signed URL for a private storage file (valid for $expiresIn seconds).
     */
    public function getSignedUrl(string $bucket, string $path, int $expiresIn = 3600): string
    {
        $url = $this->url . '/storage/v1/object/sign/' . rawurlencode($bucket) . '/' . rawurlencode($path);

        $apiKey = !empty($this->serviceRoleKey) ? $this->serviceRoleKey : $this->anonKey;

        $payload = json_encode(['expiresIn' => $expiresIn]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POST            => true,
            CURLOPT_HTTPHEADER      => [
                'apikey: ' . $apiKey,
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS      => $payload,
            CURLOPT_TIMEOUT         => 10,
        ]);

        $response = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($httpStatus >= 200 && $httpStatus < 300 && is_array($decoded) && isset($decoded['signedURL'])) {
            return $decoded['signedURL'];
        }

        return '';
    }

    /**
     * Get the storage bucket name for payment proofs.
     */
    public function getPaymentProofsBucket(): string
    {
        return $this->storageBucketProofs;
    }

    /**
     * Get the storage bucket name for client files.
     */
    public function getClientFilesBucket(): string
    {
        return $this->storageBucketFiles;
    }

    /**
     * Get the Supabase URL.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Check if Supabase is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->url) && !empty($this->anonKey);
    }

    /**
     * Find or create a client record by email.
     * Returns the client UUID.
     */
    public function findOrCreateClient(array $data): ?string
    {
        $client = $this->selectOne('clients', '*', ['email' => $data['email']]);
        if ($client !== null && isset($client['id'])) {
            $this->update('clients', [
                'full_name'     => $data['full_name'],
                'business_name' => $data['business_name'],
                'whatsapp'      => $data['whatsapp'],
                'domain'        => $data['domain'] ?? null,
                'address'       => $data['address'] ?? null,
            ], ['id' => $client['id']]);

            return $client['id'];
        }

        $payload = [
            'full_name'     => $data['full_name'],
            'business_name' => $data['business_name'],
            'whatsapp'      => $data['whatsapp'],
            'email'         => $data['email'],
            'domain'        => $data['domain'] ?? null,
            'address'       => $data['address'] ?? null,
        ];

        $inserted = $this->insert('clients', $payload);
        if ($inserted && isset($inserted['id'])) {
            return $inserted['id'];
        }

        return null;
    }

    /**
     * Call a PostgreSQL function (RPC) via PostgREST.
     *
     * @param string $fn      Function name
     * @param array  $params  Parameters to pass as query string
     * @return array Response decoded body
     */
    public function rpc(string $fn, array $params = []): array
    {
        $params['select'] = '*';
        $url = $this->url . '/rest/v1/rpc/' . rawurlencode($fn);

        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        $ch = curl_init($url);

        $apiKey = !empty($this->serviceRoleKey) ? $this->serviceRoleKey : $this->anonKey;

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_CUSTOMREQUEST   => 'GET',
            CURLOPT_HTTPHEADER      => [
                'apikey: ' . $apiKey,
                'Authorization: Bearer ' . $apiKey,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_CONNECTTIMEOUT  => 10,
        ]);

        $response = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * Call a PostgreSQL function (RPC) via PostgREST with POST body.
     *
     * @param string $fn      Function name
     * @param array  $params  Parameters to pass as JSON body
     * @return array Response decoded body
     */
    public function rpcPost(string $fn, array $params = []): array
    {
        $url = $this->url . '/rest/v1/rpc/' . rawurlencode($fn);

        $apiKey = !empty($this->serviceRoleKey) ? $this->serviceRoleKey : $this->anonKey;

        $ch = curl_init($url);

        $payload = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POST            => true,
            CURLOPT_HTTPHEADER      => [
                'apikey: ' . $apiKey,
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS      => $payload,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_CONNECTTIMEOUT  => 10,
        ]);

        $response = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * Create a website order through the controlled public Supabase RPC.
     * Prices and totals are calculated server-side from the packages/vouchers tables.
     */
    public function createPublicOrder(array $data): ?array
    {
        $result = $this->rpcPost('create_public_order', [
            'p_full_name'     => $data['full_name'] ?? '',
            'p_business_name' => $data['business_name'] ?? '',
            'p_whatsapp'      => $data['whatsapp'] ?? '',
            'p_email'         => $data['email'] ?? '',
            'p_domain'        => $data['domain'] ?? null,
            'p_package_code'  => $data['package_code'] ?? '',
            'p_voucher_code'  => $data['voucher_code'] ?? null,
        ]);

        if (!empty($result) && is_array($result[0] ?? null)) {
            return $result[0];
        }

        return null;
    }

    /**
     * Find an order for payment confirmation by order number + customer email.
     * The RPC keeps public order data hidden while allowing the payment form to
     * identify the correct order without requiring a Supabase Auth session.
     */
    public function findOrderForPayment(string $orderNumber, string $email): ?array
    {
        $result = $this->rpcPost('get_order_for_payment', [
            'p_order_number' => $orderNumber,
            'p_email'        => $email,
        ]);
        if (!empty($result) && is_array($result[0] ?? null)) {
            return $result[0];
        }
        return null;
    }

    /**
     * Atomically record a payment confirmation and move the order to
     * payment_received. Returns a small status object from Supabase RPC.
     */
    public function submitPaymentConfirmation(string $orderNumber, string $email, string $paymentDate, int $amount, string $proofPath): array
    {
        $result = $this->rpcPost('submit_payment_confirmation', [
            'p_order_number' => $orderNumber,
            'p_email'        => $email,
            'p_payment_date' => $paymentDate,
            'p_amount'       => $amount,
            'p_proof_path'   => $proofPath,
        ]);
        return is_array($result[0] ?? null) ? $result[0] : [];
    }

    /**
     * Validate a voucher via RPC function (secure, no direct table SELECT needed).
     * Falls back to direct table query if RPC is not available.
     *
     * @return array|null Result with keys: valid, discount_amount, discount_type, discount_value, code, message
     */
    public function validateVoucherRpc(string $code, string $packageCode = '', int $amount = 0): ?array
    {
        $result = $this->rpcPost('validate_voucher', [
            'p_code'        => $code,
            'p_package_code' => $packageCode,
            'p_amount'      => $amount,
        ]);

        if (!empty($result) && is_array($result[0] ?? null)) {
            return $result[0];
        }

        return null;
    }

    /**
     * Find a package by its code.
     * Uses RPC function first (safe for public), falls back to direct SELECT.
     */
    public function findPackage(string $code): ?array
    {
        $result = $this->rpcPost('get_package_by_code', ['p_code' => $code]);
        if (!empty($result) && is_array($result[0] ?? null)) {
            return $result[0];
        }

        return $this->selectOne('packages', '*', ['code' => $code, 'active' => true]);
    }

    /**
     * Validate a voucher code against the database.
     * Uses RPC function (secure) if available, falls back to direct SELECT.
     *
     * @return array|null Result with keys: code, discount_amount, discount_type, discount_value
     */
    public function validateVoucher(string $code, string $packageCode = '', int $amount = 0): ?array
    {
        $result = $this->validateVoucherRpc($code, $packageCode, $amount);
        if ($result !== null && ($result['valid'] ?? false) === true) {
            return [
                'code'             => $result['code'] ?? $code,
                'discount_amount'  => (int) ($result['discount_amount'] ?? 0),
                'discount_type'    => $result['discount_type'] ?? 'amount',
                'discount_value'   => $result['discount_value'] ?? 0,
            ];
        }

        if ($result !== null && ($result['valid'] ?? false) === false) {
            return null;
        }

        // Fallback: direct table SELECT (only works with service_role or admin)
        $voucher = $this->selectOne('vouchers', '*', ['code' => $code]);
        if ($voucher === null) {
            return null;
        }

        if (($voucher['active'] ?? true) !== true && ($voucher['active'] ?? true) !== 1 && ($voucher['active'] ?? true) !== null) {
            return null;
        }

        $today = date('Y-m-d');
        if (!empty($voucher['valid_from']) && $today < $voucher['valid_from']) {
            return null;
        }
        if (!empty($voucher['valid_until']) && $today > $voucher['valid_until']) {
            return null;
        }
        if (isset($voucher['max_uses']) && $voucher['max_uses'] !== null && $voucher['used_count'] >= $voucher['max_uses']) {
            return null;
        }

        $discount = 0;
        if (($voucher['discount_type'] ?? 'amount') === 'percent') {
            $discount = min($amount, (int) round($amount * min(100, max(0, (float) $voucher['discount_value'])) / 100));
        } else {
            $discount = min($amount, (int) max(0, $voucher['discount_value']));
        }

        return [
            'code'             => $voucher['code'],
            'discount_amount'  => $discount,
            'discount_type'    => $voucher['discount_type'] ?? 'amount',
            'discount_value'   => $voucher['discount_value'],
        ];
    }
}
