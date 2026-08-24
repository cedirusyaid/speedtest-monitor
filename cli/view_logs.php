<?php
/**
 * CLI Log Viewer
 * Menampilkan ringkasan riwayat pengujian speedtest dari database.
 */

$config = require dirname(__DIR__) . '/config.php';

$limit = isset($argv[1]) ? (int)$argv[1] : 10;
if ($limit <= 0) $limit = 10;

$tzLabel = ($config['app']['timezone'] === 'Asia/Makassar') ? 'WITA' : $config['app']['timezone'];

try {
    $pdo = get_speedtest_db_pdo($config);

    $stmt = $pdo->prepare("SELECT id, ping_ms, jitter_ms, download_mbps, upload_mbps, isp_name, wifi_ssid, server_sponsor, server_location, status, created_at FROM log_speedtest ORDER BY id DESC LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    echo "\n========================================================================================================================\n";
    echo "                              RIWAYAT SPEEDTEST CENTER ({$tzLabel} • Terakhir {$limit})\n";
    echo "========================================================================================================================\n";
    printf("%-5s | %-19s | %-8s | %-9s | %-9s | %-16s | %-18s | %-16s | %-7s\n", 
        "ID", "Waktu (" . $tzLabel . ")", "Ping(ms)", "Down(M)", "Up(M)", "WiFi/Koneksi", "ISP", "Server", "Status"
    );
    echo "------------------------------------------------------------------------------------------------------------------------\n";

    if (empty($rows)) {
        echo "Belum ada riwayat pengujian tersimpan.\n";
    } else {
        foreach ($rows as $r) {
            printf("%-5d | %-19s | %-8.2f | %-9.2f | %-9.2f | %-16s | %-18s | %-16s | %-7s\n",
                $r['id'],
                $r['created_at'],
                $r['ping_ms'] ?? 0,
                $r['download_mbps'] ?? 0,
                $r['upload_mbps'] ?? 0,
                substr($r['wifi_ssid'] ?? '-', 0, 16),
                substr($r['isp_name'] ?? '-', 0, 18),
                substr($r['server_sponsor'] ?? ($r['server_location'] ?? '-'), 0, 16),
                $r['status']
            );
        }
    }
    echo "========================================================================================================================\n\n";

} catch (PDOException $e) {
    echo "[ERROR] Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
