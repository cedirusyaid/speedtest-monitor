<?php
set_time_limit(180);
ini_set('max_execution_time', 180);

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$config = require dirname(__DIR__) . '/config.php';
$dbCfg = $config['db'];
$stCfg = $config['speedtest'];

date_default_timezone_set('Asia/Makassar');

// Deteksi SSID / Koneksi Jaringan
function get_current_wifi_ssid() {
    $ssid = null;
    
    // 1. Coba ambil nama SSID via termux-wifi-connectioninfo (timeout 2s)
    $termuxWifi = @shell_exec('timeout 2 termux-wifi-connectioninfo 2>/dev/null');
    if ($termuxWifi) {
        $wifiData = json_decode($termuxWifi, true);
        if (!empty($wifiData['ssid']) && $wifiData['ssid'] !== '<unknown ssid>') {
            $ssid = trim($wifiData['ssid'], '"');
        }
    }

    // 2. Fallback deteksi status interface lokal
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
$startTime = microtime(true);
$rawOutput = '';
$data = null;
$usedEngine = '';

// 1. Prioritas 1: speedtest-cli dengan opsi HTTPS (--secure)
$pyBinary = trim(shell_exec('command -v speedtest-cli 2>/dev/null') ?: '/data/data/com.termux/files/usr/bin/speedtest-cli');
if (file_exists($pyBinary)) {
    $cmd = escapeshellcmd($pyBinary) . " --secure --json --timeout 45 2>&1";
    $output = shell_exec($cmd);
    $decoded = json_decode((string)$output, true);
    if ($decoded && isset($decoded['download']) && isset($decoded['upload'])) {
        $rawOutput = $output;
        $data = $decoded;
        $usedEngine = 'speedtest-cli (secure)';
    }
}

// 2. Prioritas 2 (Fallback): speedtest-go jika prioritas 1 gagal
if (!$data) {
    $goBinary = trim(shell_exec('command -v speedtest-go 2>/dev/null') ?: '/data/data/com.termux/files/usr/bin/speedtest-go');
    if (file_exists($goBinary)) {
        $cmd = escapeshellcmd($goBinary) . " --json 2>&1";
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

$testStatus = 'SUCCESS';
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
    $testStatus = 'FAILED';
    $errorMessage = "Pengujian gagal pada semua engine. Output: " . substr(strip_tags((string)$rawOutput), 0, 500);
} else {
    // Format A: speedtest-cli (Python)
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
    // Format B: speedtest-go (Go)
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
}

// Simpan hasil ke database MariaDB
try {
    $dsn = "mysql:host={$dbCfg['host']};port={$dbCfg['port']};dbname={$dbCfg['database']};charset={$dbCfg['charset']}";
    $pdo = new PDO($dsn, $dbCfg['user'], $dbCfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

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
        ':status'   => $testStatus,
        ':err'      => $errorMessage
    ]);

    $insertId = $pdo->lastInsertId();

    echo json_encode([
        'status' => $testStatus === 'SUCCESS' ? 'success' : 'failed',
        'id'     => (int)$insertId,
        'engine' => $usedEngine,
        'duration_seconds' => $duration,
        'data'   => [
            'ping_ms'       => $pingMs,
            'jitter_ms'     => $jitterMs,
            'download_mbps' => $downloadMbps,
            'upload_mbps'   => $uploadMbps,
            'isp_name'      => $ispName,
            'server_name'   => $serverName,
            'server_sponsor'=> $serverSponsor,
            'client_ip'     => $clientIp,
            'wifi_ssid'     => $wifiSsid,
            'created_at'    => date('Y-m-d H:i:s')
        ],
        'error' => $errorMessage
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database save error: ' . $e->getMessage()
    ]);
}
