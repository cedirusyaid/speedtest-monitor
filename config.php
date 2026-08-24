<?php
/**
 * Speedtest Monitoring Center - Configuration Loader
 * Membaca konfigurasi dari file .env secara otomatis.
 */

// Simple native .env parser
function load_env_file($filePath) {
    if (!file_exists($filePath)) return [];
    $env = [];
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            // Hapus tanda kutip jika ada
            $val = trim($val, "\"'");
            $env[$key] = $val;
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
            }
        }
    }
    return $env;
}

$env = load_env_file(__DIR__ . '/.env');

$logPath = $env['SPEEDTEST_LOG_FILE'] ?? 'speedtest.log';
if (!preg_match('/^\//', $logPath)) {
    $logPath = __DIR__ . '/' . $logPath;
}

return [
    'db' => [
        'host'     => $env['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => (int)($env['DB_PORT'] ?? getenv('DB_PORT') ?: 3306),
        'user'     => $env['DB_USER'] ?? getenv('DB_USER') ?: 'root',
        'password' => $env['DB_PASS'] ?? getenv('DB_PASS') ?: '',
        'database' => $env['DB_NAME'] ?? getenv('DB_NAME') ?: 'db_monitoring',
        'charset'  => $env['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
    'speedtest' => [
        'binary'   => $env['SPEEDTEST_BINARY'] ?? getenv('SPEEDTEST_BINARY') ?: '/data/data/com.termux/files/usr/bin/speedtest-cli',
        'timeout'  => (int)($env['SPEEDTEST_TIMEOUT'] ?? getenv('SPEEDTEST_TIMEOUT') ?: 120),
        'log_file' => $logPath,
    ],
    'telegram' => [
        'enabled'       => filter_var($env['TELEGRAM_ENABLED'] ?? getenv('TELEGRAM_ENABLED') ?: true, FILTER_VALIDATE_BOOLEAN),
        'token'         => $env['TELEGRAM_BOT_TOKEN'] ?? getenv('TELEGRAM_BOT_TOKEN') ?: '8270886210:AAHoidg-_LpnTUBgLys96-U17hHkjUrCAd0',
        'chat_id'       => $env['TELEGRAM_CHAT_ID'] ?? getenv('TELEGRAM_CHAT_ID') ?: '-1002836383641',
        'daily_hour'    => (int)($env['TELEGRAM_DAILY_HOUR'] ?? getenv('TELEGRAM_DAILY_HOUR') ?: 9),
        'cache_file'    => __DIR__ . '/.telegram_notif_cache.json',
        'dashboard_url' => $env['TELEGRAM_DASHBOARD_URL'] ?? getenv('TELEGRAM_DASHBOARD_URL') ?: 'http://cepad/speedtest/',
    ]
];
