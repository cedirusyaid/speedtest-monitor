<?php
/**
 * Telegram Notification & Idempotency Engine
 * Speedtest Monitoring Center
 */

/**
 * Cek apakah notifikasi untuk periode tertentu sudah pernah terkirim
 */
function check_telegram_already_sent($periodKey, $type = 'DAILY_09AM', $pdo = null, $cacheFile = null) {
    // 1. Cek Cache File Lokal
    if ($cacheFile && file_exists($cacheFile)) {
        $cache = json_decode(@file_get_contents($cacheFile), true);
        if (isset($cache[$type][$periodKey]) && $cache[$type][$periodKey]['status'] === 'SUCCESS') {
            return [
                'already_sent' => true,
                'sent_at'      => $cache[$type][$periodKey]['sent_at'] ?? null,
                'speedtest_id' => $cache[$type][$periodKey]['speedtest_id'] ?? null,
                'source'       => 'cache'
            ];
        }
    }

    // 2. Cek Database MariaDB
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT id, speedtest_id, sent_at, status FROM log_speedtest_notif WHERE notif_type = :type AND period_key = :period AND status = 'SUCCESS' LIMIT 1");
            $stmt->execute([':type' => $type, ':period' => $periodKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return [
                    'already_sent' => true,
                    'sent_at'      => $row['sent_at'],
                    'speedtest_id' => $row['speedtest_id'],
                    'source'       => 'database'
                ];
            }
        } catch (PDOException $e) {
            // fallback ignore DB error
        }
    }

    return [
        'already_sent' => false,
        'sent_at'      => null,
        'speedtest_id' => null,
        'source'       => null
    ];
}

/**
 * Catat status notifikasi berhasil dikirim ke DB dan cache file
 */
function record_telegram_sent($periodKey, $speedtestId, $messagePreview, $status = 'SUCCESS', $type = 'DAILY_09AM', $pdo = null, $cacheFile = null) {
    $now = date('Y-m-d H:i:s');

    // 1. Simpan ke Cache File
    if ($cacheFile) {
        $cache = [];
        if (file_exists($cacheFile)) {
            $cache = json_decode(@file_get_contents($cacheFile), true) ?: [];
        }
        $cache[$type][$periodKey] = [
            'sent_at'      => $now,
            'speedtest_id' => $speedtestId,
            'status'       => $status
        ];
        @file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT));
    }

    // 2. Simpan ke MariaDB
    if ($pdo) {
        try {
            $sql = "INSERT INTO `log_speedtest_notif` (`notif_type`, `period_key`, `speedtest_id`, `sent_at`, `message_preview`, `status`) 
                    VALUES (:type, :period, :sid, :sent_at, :msg, :status)
                    ON DUPLICATE KEY UPDATE `sent_at` = VALUES(`sent_at`), `speedtest_id` = VALUES(`speedtest_id`), `status` = VALUES(`status`), `message_preview` = VALUES(`message_preview`)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':type'    => $type,
                ':period'  => $periodKey,
                ':sid'     => $speedtestId,
                ':sent_at' => $now,
                ':msg'     => substr($messagePreview, 0, 500),
                ':status'  => $status
            ]);
        } catch (PDOException $e) {
            // fallback ignore
        }
    }
}

/**
 * Kirim pesan ke Telegram Bot API
 */
function raw_send_telegram($message, $token, $chatId) {
    if (empty($token) || empty($chatId)) return ['ok' => false, 'error' => 'Token/ChatId kosong'];

    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $payload = [
        'chat_id'                  => $chatId,
        'text'                     => $message,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true
    ];

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($payload),
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($url, false, $ctx);
    if (!$response) {
        return ['ok' => false, 'error' => 'Gagal menghubungi Telegram API server'];
    }

    $resDecoded = json_decode($response, true);
    return $resDecoded ?: ['ok' => false, 'error' => 'Invalid JSON response from Telegram'];
}

/**
 * Logika evaluasi pengiriman Speedtest Telegram
 */
function handle_speedtest_telegram_notification(array $data, $pdo, array $tgConfig, $isForce = false) {
    if (empty($tgConfig['enabled']) && !$isForce) {
        return ['status' => 'skipped', 'reason' => 'Telegram notification disabled'];
    }

    $currentHour = (int)date('G'); // 0 - 23
    $targetHour  = (int)($tgConfig['daily_hour'] ?? 9);
    $todayDate   = date('Y-m-d');
    $periodKey   = $todayDate . ' 09'; // Unique key untuk jam 9 hari ini
    $cacheFile   = $tgConfig['cache_file'] ?? (__DIR__ . '/../.telegram_notif_cache.json');

    // 1. Jika bukan jam 9 AM dan tidak dipaksa force: skip
    if ($currentHour !== $targetHour && !$isForce) {
        return [
            'status' => 'skipped',
            'reason' => "Bukan jam target notifikasi (Sekarang: jam {$currentHour}:00 WITA, Target: jam {$targetHour}:00 WITA)"
        ];
    }

    // 2. Cek apakah sudah pernah terkirim hari ini di jam 9 AM
    if (!$isForce) {
        $check = check_telegram_already_sent($periodKey, 'DAILY_09AM', $pdo, $cacheFile);
        if ($check['already_sent']) {
            return [
                'status'  => 'already_sent',
                'sent_at' => $check['sent_at'],
                'reason'  => "Notifikasi jam 09:00 hari ini ($todayDate) sudah pernah dikirim pada {$check['sent_at']} (Source: {$check['source']})"
            ];
        }
    }

    // 3. Susun template pesan Telegram
    $speedtestId = $data['id'] ?? 0;
    $download    = number_format((float)($data['download_mbps'] ?? 0), 2);
    $upload      = number_format((float)($data['upload_mbps'] ?? 0), 2);
    $ping        = number_format((float)($data['ping_ms'] ?? 0), 2);
    $jitter      = isset($data['jitter_ms']) ? number_format((float)$data['jitter_ms'], 2) : '-';
    $wifiSsid    = htmlspecialchars($data['wifi_ssid'] ?? 'Koneksi Langsung');
    $isp         = htmlspecialchars($data['isp_name'] ?? 'Tidak Diketahui');
    $server      = htmlspecialchars(($data['server_sponsor'] ?? '') ?: ($data['server_name'] ?? '-'));
    $ip          = htmlspecialchars($data['client_ip'] ?? '-');
    $waktu       = date('d M Y, H:i') . ' WITA';
    $dashUrl     = $tgConfig['dashboard_url'] ?? 'http://cepad/speedtest/';

    $msg = "⚡ <b>LAPORAN SPEEDTEST INTERNET (09:00 WITA)</b> ⚡\n";
    $msg .= "📱 <b>Device:</b> <code>cepad</code> (Sinjai)\n";
    $msg .= "🗓️ <b>Waktu:</b> <code>{$waktu}</code>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "📶 <b>WiFi / SSID:</b> <b>{$wifiSsid}</b>\n";
    $msg .= "🌐 <b>Provider:</b> <b>{$isp}</b>\n";
    $msg .= "🎯 <b>Server:</b> {$server}\n";
    $msg .= "📍 <b>IP Publik:</b> <code>{$ip}</code>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "📥 <b>Download:</b> <b>{$download} Mbps</b>\n";
    $msg .= "📤 <b>Upload:</b> <b>{$upload} Mbps</b>\n";
    $msg .= "⏱️ <b>Ping Latency:</b> <b>{$ping} ms</b> (Jitter: {$jitter} ms)\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "📊 <a href=\"{$dashUrl}\">Buka Speedtest Dashboard</a>";

    // 4. Eksekusi pengiriman
    $sendResult = raw_send_telegram($msg, $tgConfig['token'], $tgConfig['chat_id']);

    if (!empty($sendResult['ok'])) {
        record_telegram_sent($periodKey, $speedtestId, $msg, 'SUCCESS', 'DAILY_09AM', $pdo, $cacheFile);
        return [
            'status'     => 'success',
            'sent_at'    => date('Y-m-d H:i:s'),
            'period_key' => $periodKey,
            'reason'     => 'Notifikasi Telegram berhasil dikirim'
        ];
    } else {
        $err = $sendResult['error'] ?? ($sendResult['description'] ?? 'Unknown Telegram error');
        record_telegram_sent($periodKey, $speedtestId, $msg, 'FAILED', 'DAILY_09AM', $pdo, $cacheFile);
        return [
            'status' => 'failed',
            'reason' => "Gagal kirim Telegram: {$err}"
        ];
    }
}
