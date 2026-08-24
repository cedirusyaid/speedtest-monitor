<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$config = require dirname(__DIR__) . '/config.php';
$dbCfg = $config['db'];

try {
    $dsn = "mysql:host={$dbCfg['host']};port={$dbCfg['port']};dbname={$dbCfg['database']};charset={$dbCfg['charset']}";
    $pdo = new PDO($dsn, $dbCfg['user'], $dbCfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

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
