<?php
// DIAGNOSTIC SEMENTARA - HAPUS SETELAH SELESAI DIGUNAKAN
header('Content-Type: text/plain; charset=utf-8');

echo "=== KHANIA STUDIO PHP DIAGNOSTIC ===\n\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "SAPI: " . PHP_SAPI . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? '(tidak tersedia)') . "\n";
echo "Script: " . __FILE__ . "\n\n";

echo "=== EXTENSIONS ===\n";
foreach (['curl','openssl','json','mbstring','fileinfo'] as $ext) {
    echo sprintf("%-12s %s\n", $ext, extension_loaded($ext) ? 'OK' : 'TIDAK ADA');
}

echo "\n=== FILE CHECK ===\n";
$files = [
    '.env',
    'config-pemesanan.php',
    'lib/env.php',
    'lib/SupabaseClient.php',
    'proses-pemesanan.php',
];
foreach ($files as $file) {
    echo sprintf("%-28s %s\n", $file, is_file(__DIR__ . '/' . $file) ? 'ADA' : 'TIDAK ADA');
}

echo "\n=== ENV CHECK (NILAI RAHASIA TIDAK DITAMPILKAN) ===\n";
$env = __DIR__ . '/.env';
if (is_file($env)) {
    echo ".env readable: " . (is_readable($env) ? 'YA' : 'TIDAK') . "\n";
    $content = @file_get_contents($env);
    if ($content === false) {
        echo ".env read: GAGAL\n";
    } else {
        foreach (preg_split('/\R/', $content) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                [$key] = explode('=', $line, 2);
                $key = trim($key);
                if (in_array($key, ['SUPABASE_URL','SUPABASE_ANON_KEY','SUPABASE_SERVICE_ROLE_KEY'], true)) {
                    echo $key . ": " . (($value = trim(explode('=', $line, 2)[1])) !== '' ? 'TERISI' : 'KOSONG') . "\n";
                }
            }
        }
    }
}

echo "\n=== LOAD TEST ===\n";
function load_test($label, $file) {
    echo "-- $label --\n";
    try {
        require_once $file;
        echo "OK\n";
    } catch (Throwable $e) {
        echo "ERROR: " . get_class($e) . "\n";
        echo "Message: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . "\n";
        echo "Line: " . $e->getLine() . "\n";
    }
}
load_test('lib/env.php', __DIR__ . '/lib/env.php');
load_test('lib/SupabaseClient.php', __DIR__ . '/lib/SupabaseClient.php');

echo "\n=== DONE ===\n";
echo "HAPUS FILE diagnose.php dari hosting setelah hasil dikirim.\n";
