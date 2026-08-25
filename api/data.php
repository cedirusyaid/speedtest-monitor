<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$config = require dirname(__DIR__) . '/config.php';

try {
    $pdo = get_speedtest_db_pdo($config);

    // 1. Ambil daftar Bulan (untuk dropdown)
    $monthNamesIndo = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];

    $stmtMonths = $pdo->query("
        SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') AS ym 
        FROM log_speedtest 
        ORDER BY ym DESC
    ");
    $availableMonths = [];
    while ($mRow = $stmtMonths->fetch()) {
        $ym = $mRow['ym'];
        list($y, $m) = explode('-', $ym);
        $label = ($monthNamesIndo[$m] ?? $m) . " " . $y;
        $availableMonths[] = [
            'value' => $ym,
            'label' => $label
        ];
    }

    // 2. Ambil daftar ISP (untuk dropdown)
    $stmtIsps = $pdo->query("
        SELECT DISTINCT isp_name 
        FROM log_speedtest 
        WHERE isp_name IS NOT NULL AND isp_name != '' 
        ORDER BY isp_name ASC
    ");
    $availableIsps = $stmtIsps->fetchAll(PDO::FETCH_COLUMN);

    // 3. Ambil daftar WiFi SSID / Tipe Koneksi (untuk dropdown)
    $stmtSsids = $pdo->query("
        SELECT DISTINCT wifi_ssid 
        FROM log_speedtest 
        WHERE wifi_ssid IS NOT NULL AND wifi_ssid != '' 
        ORDER BY wifi_ssid ASC
    ");
    $availableSsids = $stmtSsids->fetchAll(PDO::FETCH_COLUMN);

    // 4. Baca Parameter Filter
    $filterMonth = isset($_GET['month']) && $_GET['month'] !== 'all' && $_GET['month'] !== '' ? $_GET['month'] : null;
    $filterIsp   = isset($_GET['isp']) && $_GET['isp'] !== 'all' && $_GET['isp'] !== '' ? $_GET['isp'] : null;
    $filterSsid  = isset($_GET['ssid']) && $_GET['ssid'] !== 'all' && $_GET['ssid'] !== '' ? $_GET['ssid'] : null;
    $limit       = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    if ($limit <= 0 || $limit > 500) $limit = 50;

    $whereClauses = ["1=1"];
    $params = [];

    if ($filterMonth && preg_match('/^\d{4}-\d{2}$/', $filterMonth)) {
        $whereClauses[] = "DATE_FORMAT(created_at, '%Y-%m') = :month";
        $params[':month'] = $filterMonth;
    }

    if ($filterIsp) {
        $whereClauses[] = "isp_name = :isp";
        $params[':isp'] = $filterIsp;
    }

    if ($filterSsid) {
        $whereClauses[] = "wifi_ssid = :ssid";
        $params[':ssid'] = $filterSsid;
    }

    $whereSql = implode(" AND ", $whereClauses);

    // 5. Hitung Agregasi Summary Terfilter
    $sqlSummary = "
        SELECT 
            COUNT(*) AS total_tests,
            COALESCE(AVG(CASE WHEN status='SUCCESS' THEN download_mbps END), 0) AS avg_download,
            COALESCE(AVG(CASE WHEN status='SUCCESS' THEN upload_mbps END), 0) AS avg_upload,
            COALESCE(AVG(CASE WHEN status='SUCCESS' THEN ping_ms END), 0) AS avg_ping,
            COALESCE(MAX(download_mbps), 0) AS max_download,
            COALESCE(MAX(upload_mbps), 0) AS max_upload
        FROM log_speedtest 
        WHERE {$whereSql}
    ";
    $stmtSummary = $pdo->prepare($sqlSummary);
    $stmtSummary->execute($params);
    $summary = $stmtSummary->fetch();

    // 6. Data Pengujian Terbaru (Terfilter)
    $sqlLatest = "SELECT * FROM log_speedtest WHERE {$whereSql} ORDER BY id DESC LIMIT 1";
    $stmtLatest = $pdo->prepare($sqlLatest);
    $stmtLatest->execute($params);
    $latest = $stmtLatest->fetch() ?: null;

    // 7. Data Chart
    $sqlChart = "
        SELECT id, ping_ms, download_mbps, upload_mbps, created_at 
        FROM log_speedtest 
        WHERE {$whereSql} AND status = 'SUCCESS' 
        ORDER BY id DESC 
        LIMIT 30
    ";
    $stmtChart = $pdo->prepare($sqlChart);
    $stmtChart->execute($params);
    $chartRows = array_reverse($stmtChart->fetchAll());

    $chartLabels = [];
    $chartDownloads = [];
    $chartUploads = [];
    $chartPings = [];

    foreach ($chartRows as $cr) {
        $chartLabels[] = date('d/m H:i', strtotime($cr['created_at']));
        $chartDownloads[] = (float)$cr['download_mbps'];
        $chartUploads[] = (float)$cr['upload_mbps'];
        $chartPings[] = (float)$cr['ping_ms'];
    }

    // 7.5 Analisis Statistik Jam Rawan (Hourly Congestion & Risk Analysis)
    $sqlHourly = "
        SELECT 
            HOUR(created_at) AS hr,
            COUNT(*) AS total_tests,
            SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) AS success_tests,
            SUM(CASE WHEN status != 'SUCCESS' THEN 1 ELSE 0 END) AS fail_tests,
            COALESCE(AVG(CASE WHEN status = 'SUCCESS' THEN download_mbps END), 0) AS avg_download,
            COALESCE(AVG(CASE WHEN status = 'SUCCESS' THEN upload_mbps END), 0) AS avg_upload,
            COALESCE(AVG(CASE WHEN status = 'SUCCESS' THEN ping_ms END), 0) AS avg_ping,
            COALESCE(AVG(CASE WHEN status = 'SUCCESS' THEN jitter_ms END), 0) AS avg_jitter,
            COALESCE(MIN(CASE WHEN status = 'SUCCESS' THEN download_mbps END), 0) AS min_download,
            COALESCE(MAX(CASE WHEN status = 'SUCCESS' THEN ping_ms END), 0) AS max_ping
        FROM log_speedtest 
        WHERE {$whereSql}
        GROUP BY HOUR(created_at)
    ";
    $stmtHourly = $pdo->prepare($sqlHourly);
    $stmtHourly->execute($params);
    $rawHourly = [];
    while ($hRow = $stmtHourly->fetch()) {
        $rawHourly[(int)$hRow['hr']] = $hRow;
    }

    $hourlyLabels = [];
    $hourlyDownloads = [];
    $hourlyUploads = [];
    $hourlyPings = [];
    $hourlyRiskScores = [];
    $hourlyRiskColors = [];
    $hourlyDetails = [];

    $globalAvgPing = (float)$summary['avg_ping'] ?: 40;
    $globalAvgDl   = (float)$summary['avg_download'] ?: 30;

    $worstHour = null;
    $maxRiskScore = -1;
    $bestHour = null;
    $minRiskScore = 999;

    $workHoursRisk = [];
    $nightHoursRisk = [];
    $offpeakHoursRisk = [];

    for ($h = 0; $h < 24; $h++) {
        $hLabel = sprintf('%02d:00', $h);
        $hourlyLabels[] = $hLabel;

        if (isset($rawHourly[$h])) {
            $hrData = $rawHourly[$h];
            $avgDl   = round((float)$hrData['avg_download'], 2);
            $avgUl   = round((float)$hrData['avg_upload'], 2);
            $avgP    = round((float)$hrData['avg_ping'], 2);
            $avgJit  = round((float)$hrData['avg_jitter'], 2);
            $totalT  = (int)$hrData['total_tests'];
            $failT   = (int)$hrData['fail_tests'];

            // Kalkulasi Risk Score (0 - 100)
            $pingRatio = $globalAvgPing > 0 ? ($avgP / $globalAvgPing) : 1;
            $pingScore = min(45, max(0, ($avgP > 30 ? ($avgP - 30) * 0.4 : 0) + ($pingRatio > 1.15 ? ($pingRatio - 1) * 25 : 0)));

            $dlDropRatio = $globalAvgDl > 0 ? max(0, ($globalAvgDl - $avgDl) / $globalAvgDl) : 0;
            $dlScore = min(40, $dlDropRatio * 40);

            $failRatio = $totalT > 0 ? ($failT / $totalT) : 0;
            $failScore = min(15, ($failRatio * 15) + ($avgJit > 15 ? 5 : 0));

            $riskScore = (int)round(min(100, max(5, $pingScore + $dlScore + $failScore)));

            if ($riskScore >= 60) {
                $riskLevel = 'RAWAN';
                $riskColor = '#ef4444'; // Merah
                $riskBadge = 'Rawan / Bottleneck';
            } elseif ($riskScore >= 35) {
                $riskLevel = 'WASPAD';
                $riskColor = '#f59e0b'; // Kuning
                $riskBadge = 'Padat / Sedang';
            } else {
                $riskLevel = 'LANCAR';
                $riskColor = '#10b981'; // Hijau
                $riskBadge = 'Stabil / Lancar';
            }

            if ($riskScore > $maxRiskScore) {
                $maxRiskScore = $riskScore;
                $worstHour = [
                    'hour'         => $hLabel,
                    'hour_num'     => $h,
                    'risk_score'   => $riskScore,
                    'level'        => $riskLevel,
                    'avg_download' => $avgDl,
                    'avg_ping'     => $avgP,
                    'total_tests'  => $totalT
                ];
            }

            if ($riskScore < $minRiskScore) {
                $minRiskScore = $riskScore;
                $bestHour = [
                    'hour'         => $hLabel,
                    'hour_num'     => $h,
                    'risk_score'   => $riskScore,
                    'level'        => $riskLevel,
                    'avg_download' => $avgDl,
                    'avg_ping'     => $avgP,
                    'total_tests'  => $totalT
                ];
            }

            if ($h >= 8 && $h <= 17) $workHoursRisk[] = $riskScore;
            elseif ($h >= 18 && $h <= 23) $nightHoursRisk[] = $riskScore;
            else $offpeakHoursRisk[] = $riskScore;

            $hourlyDownloads[]   = $avgDl;
            $hourlyUploads[]     = $avgUl;
            $hourlyPings[]       = $avgP;
            $hourlyRiskScores[]  = $riskScore;
            $hourlyRiskColors[]  = $riskColor;

            $hourlyDetails[] = [
                'hour'         => $hLabel,
                'has_data'     => true,
                'total_tests'  => $totalT,
                'avg_download' => $avgDl,
                'avg_upload'   => $avgUl,
                'avg_ping'     => $avgP,
                'avg_jitter'   => $avgJit,
                'risk_score'   => $riskScore,
                'risk_level'   => $riskLevel,
                'risk_badge'   => $riskBadge,
                'risk_color'   => $riskColor
            ];
        } else {
            $hourlyDownloads[]   = 0;
            $hourlyUploads[]     = 0;
            $hourlyPings[]       = 0;
            $hourlyRiskScores[]  = 0;
            $hourlyRiskColors[]  = '#334155';

            $hourlyDetails[] = [
                'hour'         => $hLabel,
                'has_data'     => false,
                'total_tests'  => 0,
                'avg_download' => 0,
                'avg_upload'   => 0,
                'avg_ping'     => 0,
                'avg_jitter'   => 0,
                'risk_score'   => 0,
                'risk_level'   => 'NO_DATA',
                'risk_badge'   => 'Belum Ada Data',
                'risk_color'   => '#334155'
            ];
        }
    }

    $avgWorkRisk    = count($workHoursRisk) > 0 ? (int)round(array_sum($workHoursRisk) / count($workHoursRisk)) : null;
    $avgNightRisk   = count($nightHoursRisk) > 0 ? (int)round(array_sum($nightHoursRisk) / count($nightHoursRisk)) : null;
    $avgOffpeakRisk = count($offpeakHoursRisk) > 0 ? (int)round(array_sum($offpeakHoursRisk) / count($offpeakHoursRisk)) : null;

    // 8. Data Log Tabel
    $sqlLogs = "
        SELECT id, ping_ms, jitter_ms, download_mbps, upload_mbps, packet_loss_pct, 
               isp_name, wifi_ssid, server_name, server_sponsor, server_location, client_ip, 
               status, error_message, created_at 
        FROM log_speedtest 
        WHERE {$whereSql} 
        ORDER BY id DESC 
        LIMIT :limit
    ";
    $stmtLogs = $pdo->prepare($sqlLogs);
    foreach ($params as $key => $val) {
        $stmtLogs->bindValue($key, $val);
    }
    $stmtLogs->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtLogs->execute();
    $logs = $stmtLogs->fetchAll();

    echo json_encode([
        'status' => 'success',
        'app_timezone' => $config['app']['timezone'] ?? 'Asia/Makassar',
        'filters' => [
            'selected_month'   => $filterMonth,
            'selected_isp'     => $filterIsp,
            'selected_ssid'    => $filterSsid,
            'available_months' => $availableMonths,
            'available_isps'   => $availableIsps,
            'available_ssids'  => $availableSsids
        ],
        'summary' => [
            'total_tests'    => (int)$summary['total_tests'],
            'avg_download'   => round((float)$summary['avg_download'], 2),
            'avg_upload'     => round((float)$summary['avg_upload'], 2),
            'avg_ping'       => round((float)$summary['avg_ping'], 2),
            'max_download'   => round((float)$summary['max_download'], 2),
            'max_upload'     => round((float)$summary['max_upload'], 2),
            'latest'         => $latest
        ],
        'hourly_risk' => [
            'labels'              => $hourlyLabels,
            'downloads'           => $hourlyDownloads,
            'uploads'             => $hourlyUploads,
            'pings'               => $hourlyPings,
            'risk_scores'         => $hourlyRiskScores,
            'risk_colors'         => $hourlyRiskColors,
            'details'             => $hourlyDetails,
            'worst_hour'          => $worstHour,
            'best_hour'           => $bestHour,
            'avg_work_risk'       => $avgWorkRisk,
            'avg_night_risk'      => $avgNightRisk,
            'avg_offpeak_risk'    => $avgOffpeakRisk
        ],
        'chart' => [
            'labels'    => $chartLabels,
            'downloads' => $chartDownloads,
            'uploads'   => $chartUploads,
            'pings'     => $chartPings
        ],
        'logs' => $logs
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
