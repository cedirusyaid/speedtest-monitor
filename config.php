<?php
/**
 * Speedtest Monitoring Center - Global Configuration Loader & Database Factory
 * Membaca konfigurasi dari file .env secara dinamis dengan sinkronisasi zona waktu.
 */

// 1. Native .env loader
function load_speedtest_env($filePath) {
    if (!file_exists($filePath)) return [];
    $env = [];
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim(trim($val), "\"'");
            $env[$key] = $val;
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
            }
        }
    }
    return $env;
}

$env = load_speedtest_env(__DIR__ . '/.env');

// 2. Zona Waktu (Default: Asia/Makassar = WITA / UTC+8)
$appTimezone = $env['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'Asia/Makassar';
date_default_timezone_set($appTimezone);

// Hitung offset MariaDB / MySQL (contoh: +08:00)
try {
    $tzObj = new DateTimeZone($appTimezone);
    $dtObj = new DateTime('now', $tzObj);
    $mysqlTzOffset = $dtObj->format('P');
} catch (Exception $e) {
    $mysqlTzOffset = '+08:00';
}

$logPath = $env['SPEEDTEST_LOG_FILE'] ?? 'speedtest.log';
if (!preg_match('/^\//', $logPath)) {
    $logPath = __DIR__ . '/' . $logPath;
}

// 3. Deteksi Nama Device / Hostname
$rawHost = gethostname() ?: php_uname('n');
if (empty($rawHost) || strtolower($rawHost) === 'localhost') {
    if (!empty($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_NAME'] !== '127.0.0.1') {
        $rawHost = explode('.', $_SERVER['SERVER_NAME'])[0];
    } elseif (!empty($_SERVER['HTTP_HOST'])) {
        $hostOnly = explode(':', $_SERVER['HTTP_HOST'])[0];
        if ($hostOnly !== 'localhost' && $hostOnly !== '127.0.0.1') {
            $rawHost = explode('.', $hostOnly)[0];
        }
    }
}
$deviceName = $env['APP_DEVICE_NAME'] ?? getenv('APP_DEVICE_NAME') ?: ($rawHost ?: 'server');

$config = [
    'app' => [
        'device_name'     => $deviceName,
        'timezone'        => $appTimezone,
        'mysql_tz_offset' => $mysqlTzOffset,
    ],
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
        'dashboard_url' => $env['TELEGRAM_DASHBOARD_URL'] ?? getenv('TELEGRAM_DASHBOARD_URL') ?: "http://{$deviceName}/speedtest/",
    ]
];

// Helper global PDO connection factory dengan sinkronisasi zona waktu DB
if (!function_exists('get_speedtest_db_pdo')) {
    function get_speedtest_db_pdo($config = null) {
        if (!$config) {
            $config = require __DIR__ . '/config.php';
        }
        $dbCfg = $config['db'];
        $tzOffset = $config['app']['mysql_tz_offset'] ?? '+08:00';
        
        $dsn = "mysql:host={$dbCfg['host']};port={$dbCfg['port']};dbname={$dbCfg['database']};charset={$dbCfg['charset']}";
        $pdo = new PDO($dsn, $dbCfg['user'], $dbCfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $pdo->exec("SET time_zone = '{$tzOffset}'");
        return $pdo;
    }
}

return $config;
