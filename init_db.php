<?php
/**
 * Database Initializer & Migration Tool
 */

$config = require __DIR__ . '/config.php';
$dbCfg = $config['db'];

echo "=== Inisialisasi Database Speedtest Center ===\n";

try {
    $dsn = "mysql:host={$dbCfg['host']};port={$dbCfg['port']};charset={$dbCfg['charset']}";
    $pdo = new PDO($dsn, $dbCfg['user'], $dbCfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $sql = file_get_contents(__DIR__ . '/schema.sql');
    if (!$sql) {
        throw new Exception("File schema.sql tidak ditemukan atau kosong.");
    }

    $pdo->exec($sql);
    echo "[OK] Database `{$dbCfg['database']}` dan tabel `log_speedtest` berhasil disiapkan!\n";

} catch (PDOException $e) {
    echo "[ERROR] Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
