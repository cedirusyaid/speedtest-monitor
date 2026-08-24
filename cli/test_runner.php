<?php
/**
 * Speedtest Execution & MariaDB Storage Engine (CLI Version)
 * Mendukung format output speedtest-cli & speedtest-go, deteksi WiFi SSID, serta Notifikasi Telegram Jam 09:00 WITA.
 */

$config = require dirname(__DIR__) . '/config.php';
$stCfg  = $config['speedtest'];
$tgCfg  = $config['telegram'] ?? [];

require_once __DIR__ . '/telegram_helper.php';

$isForceTelegram = in_array('--force-telegram', $argv ?? []) || in_array('-t', $argv ?? []);

function log_msg($msg, $logFile = null) {
    $formatted = "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
    echo $formatted;
    if ($logFile) {
        file_put_contents($logFile, $formatted, FILE_APPEND);
    }
}

// Deteksi SSID / Koneksi Jaringan
function get_current_wifi_ssid() {
    $ssid = null;
    
    $termuxWifi = @shell_exec('timeout 2 termux-wifi-connectioninfo 2>/dev/null');
    if ($termuxWifi) {
        $wifiData = json_decode($termuxWifi, true);
        if (!empty($wifiData['ssid']) && $wifiData['ssid'] !== '<unknown ssid>') {
            $ssid = trim($wifiData['ssid'], '"');
        }
    }

    if (!$ssid) {
        $ifconfig = @shell_exec('ifconfig 2>/dev/null');
        if ($ifconfig) {
            if (preg_match('/wlan\d+:.*?inet\s+([0-9\.]+)/s', $ifconfig, $matches)) {
                $ssid = "WiFi (" . $matches[1] . ")";
            } elseif (preg_match('/(rmnet|ccmni)\d+:.*?inet/s', $ifconfig)) {
                $ssid = "Cellular / Mobile Data";
            } elseif (preg_match('/eth\d+:.*?inet/s', $ifconfig)) {
                $ssid = "Ethernet / LAN";
            }
        }
    }

    return $ssid ?: "Direct / Tailscale";
}

$wifiSsid = get_current_wifi_ssid();
log_msg("=== Memulai Pengujian Kecepatan Internet [Koneksi: {$wifiSsid}] ===", $stCfg['log_file']);

$startTime = microtime(true);
$rawOutput = '';
$data = null;
$usedEngine = '';

// 1. Prioritas 1: speedtest-cli dengan opsi HTTPS (--secure)
$pyBinary = trim(shell_exec('command -v speedtest-cli 2>/dev/null') ?: '/data/data/com.termux/files/usr/bin/speedtest-cli');
if (file_exists($pyBinary)) {
    $cmd = escapeshellcmd($pyBinary) . " --secure --json --timeout 45 2>&1";
    log_msg("Mencoba: {$cmd}", $stCfg['log_file']);
    $output = shell_exec($cmd);
    $decoded = json_decode((string)$output, true);
    if ($decoded && isset($decoded['download']) && isset($decoded['upload'])) {
        $rawOutput = $output;
        $data = $decoded;
        $usedEngine = 'speedtest-cli (secure)';
    }
}

// 2. Prioritas 2 (Fallback): speedtest-go
if (!$data) {
    $goBinary = trim(shell_exec('command -v speedtest-go 2>/dev/null') ?: '/data/data/com.termux/files/usr/bin/speedtest-go');
    if (file_exists($goBinary)) {
        $cmd = escapeshellcmd($goBinary) . " --json 2>&1";
        log_msg("Fallback ke: {$cmd}", $stCfg['log_file']);
        $output = shell_exec($cmd);
        $decoded = json_decode((string)$output, true);
        if ($decoded && isset($decoded['servers']) && !empty($decoded['servers'])) {
            $rawOutput = $output;
            $data = $decoded;
            $usedEngine = 'speedtest-go (fallback)';
        } else {
            $rawOutput = $output;
        }
    }
}

$duration = round(microtime(true) - $startTime, 2);
log_msg("Pengujian selesai dalam {$duration} detik (Engine: {$usedEngine}). Memproses hasil...", $stCfg['log_file']);

$status = 'SUCCESS';
$errorMessage = null;

$pingMs = null;
$jitterMs = null;
$downloadMbps = 0.00;
$uploadMbps = 0.00;
$packetLoss = 0.00;
$ispName = null;
$serverName = null;
$serverSponsor = null;
$serverLocation = null;
$clientIp = null;

if (!$data) {
    $status = 'FAILED';
    $errorMessage = "Pengujian gagal pada semua engine. Output: " . substr(strip_tags((string)$rawOutput), 0, 500);
    log_msg("[FAILED] {$errorMessage}", $stCfg['log_file']);
} else {
    // Format speedtest-cli
    if (isset($data['download']) && isset($data['upload'])) {
        $downloadMbps = round((float)$data['download'] / 1000000, 2);
        $uploadMbps   = round((float)$data['upload'] / 1000000, 2);
        $pingMs       = round((float)($data['ping'] ?? 0), 2);
        
        if (isset($data['client'])) {
            $clientIp = $data['client']['ip'] ?? null;
            $ispName  = $data['client']['isp'] ?? null;
        }

        if (isset($data['server'])) {
            $serverName     = $data['server']['name'] ?? null;
            $serverSponsor  = $data['server']['sponsor'] ?? null;
            $serverLocation = ($data['server']['name'] ?? '') . ', ' . ($data['server']['country'] ?? '');
            if (isset($data['server']['latency'])) {
                $pingMs = round((float)$data['server']['latency'], 2);
            }
        }
    } 
    // Format speedtest-go
    elseif (isset($data['servers']) && !empty($data['servers'])) {
        if (isset($data['user_info'])) {
            $clientIp = $data['user_info']['IP'] ?? null;
            $ispName  = $data['user_info']['Isp'] ?? null;
        }

        $server = $data['servers'][0] ?? [];
        if (isset($server['latency']) && $server['latency'] > 0) {
            $pingMs = round($server['latency'] / 1000000, 2);
        }
        if (isset($server['jitter']) && $server['jitter'] > 0) {
            $jitterMs = round($server['jitter'] / 1000000, 2);
        }
        if (isset($server['dl_speed']) && $server['dl_speed'] > 0) {
            $downloadMbps = round(($server['dl_speed'] * 8) / 1000000, 2);
        }
        if (isset($server['ul_speed']) && $server['ul_speed'] > 0) {
            $uploadMbps = round(($server['ul_speed'] * 8) / 1000000, 2);
        }

        $serverName     = $server['name'] ?? null;
        $serverSponsor  = $server['sponsor'] ?? null;
        $serverLocation = ($server['name'] ?? '') . ', ' . ($server['country'] ?? '');
    }

    if ($status === 'SUCCESS') {
        log_msg(sprintf(
            "[HASIL] ISP: %s | SSID: %s | Server: %s (%s) | Ping: %.2f ms | Download: %.2f Mbps | Upload: %.2f Mbps",
            $ispName ?? '-',
            $wifiSsid,
            $serverName ?? '-',
            $serverSponsor ?? '-',
            $pingMs ?? 0,
            $downloadMbps,
            $uploadMbps
        ), $stCfg['log_file']);
    }
}

// 3. Simpan ke MariaDB dengan timezone sinkron
$insertId = null;
$pdo = null;

try {
    $pdo = get_speedtest_db_pdo($config);

    $sql = "INSERT INTO `log_speedtest` 
        (`ping_ms`, `jitter_ms`, `download_mbps`, `upload_mbps`, `packet_loss_pct`, `isp_name`, `server_name`, `server_sponsor`, `server_location`, `client_ip`, `wifi_ssid`, `raw_output`, `status`, `error_message`, `created_at`) 
        VALUES 
        (:ping, :jitter, :download, :upload, :loss, :isp, :server, :sponsor, :loc, :ip, :wifi, :raw, :status, :err, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ping'     => $pingMs,
        ':jitter'   => $jitterMs,
        ':download' => $downloadMbps,
        ':upload'   => $uploadMbps,
        ':loss'     => $packetLoss,
        ':isp'      => $ispName,
        ':server'   => $serverName,
        ':sponsor'  => $serverSponsor,
        ':loc'      => $serverLocation,
        ':ip'       => $clientIp,
        ':wifi'     => $wifiSsid,
        ':raw'      => (string)$rawOutput,
        ':status'   => $status,
        ':err'      => $errorMessage
    ]);

    $insertId = (int)$pdo->lastInsertId();
    log_msg("[OK] Data tersimpan di database `{$config['db']['database']}` (ID: #{$insertId})", $stCfg['log_file']);

} catch (PDOException $e) {
    log_msg("[DB ERROR] Gagal menyimpan ke database: " . $e->getMessage(), $stCfg['log_file']);
}

// 4. Mekanisme Notifikasi Telegram (Otomatis Jam 09:00 WITA / Force Mode)
if ($status === 'SUCCESS' && !empty($tgCfg['enabled'])) {
    $notifPayload = [
        'id'            => $insertId,
        'download_mbps' => $downloadMbps,
        'upload_mbps'   => $uploadMbps,
        'ping_ms'       => $pingMs,
        'jitter_ms'     => $jitterMs,
        'wifi_ssid'     => $wifiSsid,
        'isp_name'      => $ispName,
        'server_name'   => $serverName,
        'server_sponsor'=> $serverSponsor,
        'client_ip'     => $clientIp
    ];

    $notifResult = handle_speedtest_telegram_notification($notifPayload, $pdo, $tgCfg, $isForceTelegram);
    
    if ($notifResult['status'] === 'success') {
        log_msg("[TELEGRAM] Berhasil mengirim notifikasi Telegram! (Key: {$notifResult['period_key']})", $stCfg['log_file']);
    } elseif ($notifResult['status'] === 'already_sent') {
        log_msg("[TELEGRAM] {$notifResult['reason']}", $stCfg['log_file']);
    } elseif ($notifResult['status'] === 'skipped') {
        log_msg("[TELEGRAM] Melewati notifikasi: {$notifResult['reason']}", $stCfg['log_file']);
    } else {
        log_msg("[TELEGRAM ERROR] {$notifResult['reason']}", $stCfg['log_file']);
    }
}
