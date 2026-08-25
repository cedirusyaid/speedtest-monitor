<?php
$appConfig = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$deviceName = $appConfig['app']['device_name'] ?? (gethostname() ?: 'Server');
$serverHost = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
$phpVer = 'PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedtest Monitoring & Analytics Center | <?= htmlspecialchars(strtoupper($deviceName)) ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        },
                        darkBg: '#090d16',
                        darkCard: '#151d30',
                        darkBorder: '#23304a'
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #090d16; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        .glass-card {
            background: rgba(21, 29, 48, 0.85);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.07);
        }
        .custom-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2394a3b8' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.65rem center;
            background-repeat: no-repeat;
            background-size: 1.25em 1.25em;
            padding-right: 2.2rem;
            -webkit-appearance: none;
            appearance: none;
        }
    </style>
</head>
<body class="text-slate-100 min-h-screen antialiased flex flex-col justify-between selection:bg-indigo-500 selection:text-white">

    <!-- Top Navigation Bar -->
    <header class="border-b border-slate-800/80 bg-slate-900/80 sticky top-0 z-40 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-lg shadow-indigo-500/25">
                    <i class="fa-solid fa-gauge-high text-lg"></i>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-white tracking-tight flex items-center gap-2">
                        Speedtest Center <span class="text-[11px] bg-indigo-500/20 text-indigo-400 font-mono px-2 py-0.5 rounded border border-indigo-500/30"><?= htmlspecialchars(strtoupper($deviceName)) ?></span>
                    </h1>
                    <p class="text-xs text-slate-400">Bandwidth & Latency Analytics • Unified Repository</p>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <div class="hidden sm:flex items-center space-x-2 text-xs bg-slate-800/60 px-3 py-1.5 rounded-lg border border-slate-700/60">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="text-slate-300">MariaDB 12.3</span>
                    <span class="text-slate-500">•</span>
                    <span class="text-slate-300">PHP 8.5</span>
                </div>

                <!-- Run Test Button -->
                <button id="btnRunTest" onclick="triggerSpeedtest()" class="relative inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 rounded-lg shadow-md shadow-indigo-600/30 transition-all duration-200 active:scale-95 disabled:opacity-50 disabled:pointer-events-none">
                    <i id="btnIcon" class="fa-solid fa-bolt-lightning mr-2"></i>
                    <span id="btnText">Uji Kecepatan</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-7 w-full flex-1 space-y-6">

        <!-- Progress Banner (Hidden by default) -->
        <div id="testProgressBanner" class="hidden p-4 rounded-xl bg-gradient-to-r from-indigo-950 via-slate-900 to-purple-950 border border-indigo-500/40 animate-pulse flex items-center justify-between shadow-xl">
            <div class="flex items-center space-x-3.5">
                <div class="w-7 h-7 rounded-full border-2 border-indigo-400 border-t-transparent animate-spin"></div>
                <div>
                    <h3 class="text-sm font-semibold text-white">Sedang Menguji Koneksi Jaringan & Mendeteksi WiFi SSID...</h3>
                    <p class="text-xs text-indigo-200">Menghubungkan ke endpoint aman (HTTPS). Mohon tunggu 15-25 detik.</p>
                </div>
            </div>
            <span class="text-xs font-mono text-indigo-300 bg-indigo-900/60 px-3 py-1 rounded-md border border-indigo-700/60">speedtest-cli (secure)</span>
        </div>

        <!-- Filter Control Bar -->
        <div class="glass-card rounded-2xl p-4 sm:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 border border-slate-800">
            <div class="flex items-center gap-2 text-sm font-semibold text-white">
                <i class="fa-solid fa-filter text-indigo-400"></i>
                <span>Filter Dashboard</span>
                <span id="filterActiveBadge" class="hidden text-[10px] font-mono bg-indigo-500/20 text-indigo-300 px-2 py-0.5 rounded border border-indigo-500/30">Terfilter</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 w-full md:w-auto">
                <!-- Month Filter -->
                <div class="relative">
                    <label class="block text-[11px] font-medium text-slate-400 uppercase tracking-wider mb-1">
                        <i class="fa-regular fa-calendar mr-1"></i> Bulan
                    </label>
                    <select id="filterMonth" onchange="applyFilters()" class="custom-select w-full bg-slate-900/90 border border-slate-700 text-slate-200 text-xs rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="all">Semua Bulan</option>
                    </select>
                </div>

                <!-- WiFi SSID Filter -->
                <div class="relative">
                    <label class="block text-[11px] font-medium text-slate-400 uppercase tracking-wider mb-1">
                        <i class="fa-solid fa-wifi mr-1"></i> WiFi / SSID
                    </label>
                    <select id="filterSsid" onchange="applyFilters()" class="custom-select w-full bg-slate-900/90 border border-slate-700 text-slate-200 text-xs rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="all">Semua WiFi / SSID</option>
                    </select>
                </div>

                <!-- ISP / Provider Filter -->
                <div class="relative">
                    <label class="block text-[11px] font-medium text-slate-400 uppercase tracking-wider mb-1">
                        <i class="fa-solid fa-tower-broadcast mr-1"></i> Provider (ISP)
                    </label>
                    <select id="filterIsp" onchange="applyFilters()" class="custom-select w-full bg-slate-900/90 border border-slate-700 text-slate-200 text-xs rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="all">Semua Provider</option>
                    </select>
                </div>

                <!-- Action / Reset Filter -->
                <div class="flex items-end">
                    <button onclick="resetFilters()" class="w-full py-2 px-3 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg text-xs font-semibold border border-slate-700 flex items-center justify-center gap-1.5 transition">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Metric Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Download Card -->
            <div class="glass-card rounded-2xl p-5 relative overflow-hidden group hover:border-emerald-500/40 transition-all duration-300">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-emerald-500/10 rounded-full blur-xl pointer-events-none"></div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Download</span>
                    <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-arrow-down"></i>
                    </span>
                </div>
                <div class="flex items-baseline space-x-2">
                    <span id="statDownload" class="text-3xl font-extrabold text-white tracking-tight">--</span>
                    <span class="text-sm font-semibold text-emerald-400">Mbps</span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400">
                    <span>Tertinggi: <b id="statMaxDownload" class="text-slate-200">--</b></span>
                    <span>Rata²: <b id="statAvgDownload" class="text-slate-200">--</b></span>
                </div>
            </div>

            <!-- Upload Card -->
            <div class="glass-card rounded-2xl p-5 relative overflow-hidden group hover:border-purple-500/40 transition-all duration-300">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-500/10 rounded-full blur-xl pointer-events-none"></div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Upload</span>
                    <span class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-400 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-arrow-up"></i>
                    </span>
                </div>
                <div class="flex items-baseline space-x-2">
                    <span id="statUpload" class="text-3xl font-extrabold text-white tracking-tight">--</span>
                    <span class="text-sm font-semibold text-purple-400">Mbps</span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400">
                    <span>Tertinggi: <b id="statMaxUpload" class="text-slate-200">--</b></span>
                    <span>Rata²: <b id="statAvgUpload" class="text-slate-200">--</b></span>
                </div>
            </div>

            <!-- Ping / Latency Card -->
            <div class="glass-card rounded-2xl p-5 relative overflow-hidden group hover:border-amber-500/40 transition-all duration-300">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-amber-500/10 rounded-full blur-xl pointer-events-none"></div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Latency / Ping</span>
                    <span class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-stopwatch"></i>
                    </span>
                </div>
                <div class="flex items-baseline space-x-2">
                    <span id="statPing" class="text-3xl font-extrabold text-white tracking-tight">--</span>
                    <span class="text-sm font-semibold text-amber-400">ms</span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400">
                    <span>Rata² Ping: <b id="statAvgPing" class="text-slate-200">--</b></span>
                    <span>Total Tes: <b id="statTotalTests" class="text-slate-200">0</b></span>
                </div>
            </div>

            <!-- ISP & WiFi SSID Card -->
            <div class="glass-card rounded-2xl p-5 relative overflow-hidden group hover:border-blue-500/40 transition-all duration-300">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-blue-500/10 rounded-full blur-xl pointer-events-none"></div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Koneksi & WiFi</span>
                    <span class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-network-wired"></i>
                    </span>
                </div>
                <div class="space-y-1">
                    <p id="statSsid" class="text-base font-bold text-white truncate flex items-center gap-1.5" title="WiFi SSID">
                        <i class="fa-solid fa-wifi text-xs text-indigo-400"></i> <span>--</span>
                    </p>
                    <p id="statIsp" class="text-xs text-slate-400 truncate">ISP: --</p>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400">
                    <span>IP: <b id="statIp" class="text-slate-200 font-mono">--</b></span>
                    <span id="statTime" class="text-[11px] text-slate-500 font-mono">--</span>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Bandwidth Chart (2 cols) -->
            <div class="glass-card rounded-2xl p-6 lg:col-span-2 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-chart-line text-indigo-400"></i>
                            Tren Bandwidth (Download & Upload)
                        </h2>
                        <p class="text-xs text-slate-400">Histori kecepatan jaringan berdasarkan filter aktif (Mbps)</p>
                    </div>
                    <div class="flex items-center space-x-3 text-xs font-medium">
                        <span class="flex items-center gap-1.5 text-emerald-400"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Download</span>
                        <span class="flex items-center gap-1.5 text-purple-400"><span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span> Upload</span>
                    </div>
                </div>
                <div class="h-64 w-full">
                    <canvas id="speedChart"></canvas>
                </div>
            </div>

            <!-- Ping & Latency Chart (1 col) -->
            <div class="glass-card rounded-2xl p-6 space-y-4">
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-wave-square text-amber-400"></i>
                        Stabilitas Latency (Ping)
                    </h2>
                    <p class="text-xs text-slate-400">Fluktuasi respon waktu (ms)</p>
                </div>
                <div class="h-64 w-full">
                    <canvas id="pingChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Hourly Risk & Peak Bottleneck Section -->
        <div class="glass-card rounded-2xl p-6 space-y-6 border border-slate-800">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-800/80 pb-4">
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
                        Statistik & Grafik Jam Rawan Koneksi
                    </h2>
                    <p class="text-xs text-slate-400">Pola fluktuasi latensi dan penurunan bandwidth selama 24 jam (Bottleneck Analysis)</p>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-xs">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> <span class="text-slate-300">Lancar (&lt; 35%)</span></span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> <span class="text-slate-300">Padat (35-60%)</span></span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span> <span class="text-slate-300">Rawan (&gt; 60%)</span></span>
                </div>
            </div>

            <!-- Risk Cards Summary -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Worst Hour Card -->
                <div class="bg-slate-900/80 rounded-xl p-4 border border-rose-500/20 relative overflow-hidden">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-rose-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-fire text-rose-500"></i> Jam Paling Rawan
                        </span>
                        <span id="badgeWorstRisk" class="text-[10px] font-mono px-2 py-0.5 rounded bg-rose-500/20 text-rose-300 border border-rose-500/30">--</span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span id="valWorstHour" class="text-2xl font-extrabold text-white">--:--</span>
                        <span class="text-xs text-slate-400 font-mono">WITA</span>
                    </div>
                    <div id="descWorstHour" class="mt-2 text-xs text-slate-400 truncate">
                        Memuat data...
                    </div>
                </div>

                <!-- Best Hour Card -->
                <div class="bg-slate-900/80 rounded-xl p-4 border border-emerald-500/20 relative overflow-hidden">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-emerald-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-check text-emerald-500"></i> Jam Paling Stabil
                        </span>
                        <span id="badgeBestRisk" class="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">--</span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span id="valBestHour" class="text-2xl font-extrabold text-white">--:--</span>
                        <span class="text-xs text-slate-400 font-mono">WITA</span>
                    </div>
                    <div id="descBestHour" class="mt-2 text-xs text-slate-400 truncate">
                        Memuat data...
                    </div>
                </div>

                <!-- Period Segment Risk Card -->
                <div class="bg-slate-900/80 rounded-xl p-4 border border-slate-800 relative overflow-hidden">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-indigo-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-chart-pie text-indigo-400"></i> Indeks Sesi Harian
                        </span>
                    </div>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400">Jam Kerja (08-17):</span>
                            <span id="valWorkRisk" class="font-mono font-bold text-slate-200">--</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400">Jam Malam (18-23):</span>
                            <span id="valNightRisk" class="font-mono font-bold text-slate-200">--</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400">Dini Hari (00-07):</span>
                            <span id="valOffpeakRisk" class="font-mono font-bold text-slate-200">--</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Combo Chart 24 Hours (Bar + Line) -->
            <div class="space-y-2">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-slate-400">
                    <span class="font-medium text-slate-300">Grafik Komparasi 24 Jam: Skor Kerawanan (%) vs Latensi (ms) &amp; Download (Mbps)</span>
                    <div class="flex items-center gap-3">
                        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-rose-500/80"></span> Skor Rawan (Bar)</span>
                        <span class="flex items-center gap-1 text-amber-400"><span class="w-2.5 h-1 bg-amber-400"></span> Avg Ping</span>
                        <span class="flex items-center gap-1 text-emerald-400"><span class="w-2.5 h-1 bg-emerald-400"></span> Avg Download</span>
                    </div>
                </div>
                <div class="h-72 w-full">
                    <canvas id="hourlyRiskChart"></canvas>
                </div>
            </div>

            <!-- 24-Hour Matrix Heatmap -->
            <div>
                <h3 class="text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                    <i class="fa-solid fa-table-cells text-indigo-400"></i> Matriks Jam 24 Jam (Klik untuk Rincian)
                </h3>
                <div id="hourlyMatrixGrid" class="grid grid-cols-6 sm:grid-cols-12 md:grid-cols-24 gap-1.5">
                    <!-- 24 items injected via JS -->
                </div>
            </div>
        </div>

        <!-- History Log Table -->
        <div class="glass-card rounded-2xl overflow-hidden">
            <div class="p-5 border-b border-slate-800/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-indigo-400"></i>
                        Riwayat Log Pengujian
                    </h2>
                    <p class="text-xs text-slate-400">Data tersimpan di tabel MariaDB <code class="text-indigo-400 bg-slate-800/80 px-1.5 py-0.5 rounded font-mono">db_monitoring.log_speedtest</code></p>
                </div>
                <div class="flex items-center space-x-3">
                    <button onclick="loadDashboardData()" class="p-2 text-slate-400 hover:text-white bg-slate-800 hover:bg-slate-700 rounded-lg text-sm border border-slate-700 transition" title="Refresh Data">
                        <i class="fa-solid fa-rotate"></i>
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900/80 text-xs uppercase font-semibold text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-mono"># ID</th>
                            <th class="py-3.5 px-4">Waktu (WITA)</th>
                            <th class="py-3.5 px-4">Ping</th>
                            <th class="py-3.5 px-4">Download</th>
                            <th class="py-3.5 px-4">Upload</th>
                            <th class="py-3.5 px-4">WiFi / SSID</th>
                            <th class="py-3.5 px-4">ISP</th>
                            <th class="py-3.5 px-4">Server</th>
                            <th class="py-3.5 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody" class="divide-y divide-slate-800/60 font-mono text-xs">
                        <tr>
                            <td colspan="9" class="text-center py-8 text-slate-500">Memuat data log...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800/80 bg-slate-950 py-4 mt-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-2">
            <div>
                &copy; <?= date('Y') ?> Speedtest Center • Managed by <strong>Tiara AI</strong> for <strong>Boss Rusyaid</strong>
            </div>
            <div class="flex items-center space-x-4">
                <span>Device: <code class="text-slate-400"><?= htmlspecialchars($deviceName) ?></code></span>
                <span>Host: <code class="text-slate-400"><?= htmlspecialchars($serverHost) ?></code></span>
            </div>
        </div>
    </footer>

    <!-- JavaScript Dashboard Controller -->
    <script>
        let speedChartInstance = null;
        let pingChartInstance = null;
        let hourlyRiskChartInstance = null;
        let isRunning = false;
        let availableMonthsLoaded = false;
        let availableIspsLoaded = false;
        let availableSsidsLoaded = false;

        // Inisialisasi Chart.js
        function initCharts() {
            const ctxSpeed = document.getElementById('speedChart').getContext('2d');
            const ctxPing = document.getElementById('pingChart').getContext('2d');
            const ctxHourly = document.getElementById('hourlyRiskChart').getContext('2d');

            speedChartInstance = new Chart(ctxSpeed, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Download (Mbps)',
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.12)',
                            borderWidth: 2.5,
                            tension: 0.35,
                            fill: true,
                            data: []
                        },
                        {
                            label: 'Upload (Mbps)',
                            borderColor: '#a855f7',
                            backgroundColor: 'rgba(168, 85, 247, 0.12)',
                            borderWidth: 2.5,
                            tension: 0.35,
                            fill: true,
                            data: []
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#151d30',
                            titleColor: '#fff',
                            bodyColor: '#cbd5e1',
                            borderColor: '#334155',
                            borderWidth: 1,
                            padding: 10
                        }
                    },
                    scales: {
                        x: { grid: { color: 'rgba(255, 255, 255, 0.04)' }, ticks: { color: '#94a3b8', font: { size: 10 } } },
                        y: { grid: { color: 'rgba(255, 255, 255, 0.04)' }, ticks: { color: '#94a3b8', font: { size: 10 } }, beginAtZero: true }
                    }
                }
            });

            pingChartInstance = new Chart(ctxPing, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Ping (ms)',
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245, 158, 11, 0.15)',
                            borderWidth: 2,
                            tension: 0.35,
                            fill: true,
                            data: []
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#151d30',
                            titleColor: '#fff',
                            bodyColor: '#cbd5e1',
                            borderColor: '#334155',
                            borderWidth: 1
                        }
                    },
                    scales: {
                        x: { grid: { color: 'rgba(255, 255, 255, 0.04)' }, ticks: { color: '#94a3b8', font: { size: 10 } } },
                        y: { grid: { color: 'rgba(255, 255, 255, 0.04)' }, ticks: { color: '#94a3b8', font: { size: 10 } }, beginAtZero: true }
                    }
                }
            });

            hourlyRiskChartInstance = new Chart(ctxHourly, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Skor Rawan (%)',
                            backgroundColor: [],
                            borderRadius: 4,
                            barPercentage: 0.6,
                            data: [],
                            yAxisID: 'yRisk'
                        },
                        {
                            type: 'line',
                            label: 'Avg Ping (ms)',
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245, 158, 11, 0.15)',
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            tension: 0.35,
                            data: [],
                            yAxisID: 'yPing'
                        },
                        {
                            type: 'line',
                            label: 'Avg Download (Mbps)',
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.15)',
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            tension: 0.35,
                            data: [],
                            yAxisID: 'ySpeed'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#151d30',
                            titleColor: '#fff',
                            bodyColor: '#cbd5e1',
                            borderColor: '#334155',
                            borderWidth: 1,
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const val = context.parsed.y;
                                    if (label.includes('Skor Rawan')) {
                                        return `🚨 ${label}: ${val}%`;
                                    } else if (label.includes('Ping')) {
                                        return `⏱️ ${label}: ${val} ms`;
                                    } else if (label.includes('Download')) {
                                        return `📥 ${label}: ${val} Mbps`;
                                    }
                                    return `${label}: ${val}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(255, 255, 255, 0.04)' },
                            ticks: { color: '#94a3b8', font: { size: 10 } }
                        },
                        yRisk: {
                            type: 'linear',
                            position: 'left',
                            min: 0,
                            max: 100,
                            grid: { color: 'rgba(255, 255, 255, 0.04)' },
                            ticks: {
                                color: '#94a3b8',
                                font: { size: 10 },
                                callback: val => val + '%'
                            }
                        },
                        ySpeed: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            display: false
                        },
                        yPing: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: true,
                            grid: { drawOnChartArea: false },
                            ticks: {
                                color: '#f59e0b',
                                font: { size: 10 },
                                callback: val => val + ' ms'
                            }
                        }
                    }
                }
            });
        }

        // Update Dropdown Filters
        function updateFilterOptions(filters) {
            const selectMonth = document.getElementById('filterMonth');
            const selectIsp = document.getElementById('filterIsp');
            const selectSsid = document.getElementById('filterSsid');

            if (!availableMonthsLoaded && filters.available_months) {
                const currentVal = selectMonth.value;
                selectMonth.innerHTML = '<option value="all">Semua Bulan</option>';
                filters.available_months.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.value;
                    opt.textContent = m.label;
                    if (m.value === currentVal) opt.selected = true;
                    selectMonth.appendChild(opt);
                });
                availableMonthsLoaded = true;
            }

            if (!availableIspsLoaded && filters.available_isps) {
                const currentIsp = selectIsp.value;
                selectIsp.innerHTML = '<option value="all">Semua Provider</option>';
                filters.available_isps.forEach(isp => {
                    const opt = document.createElement('option');
                    opt.value = isp;
                    opt.textContent = isp;
                    if (isp === currentIsp) opt.selected = true;
                    selectIsp.appendChild(opt);
                });
                availableIspsLoaded = true;
            }

            if (!availableSsidsLoaded && filters.available_ssids) {
                const currentSsid = selectSsid.value;
                selectSsid.innerHTML = '<option value="all">Semua WiFi / SSID</option>';
                filters.available_ssids.forEach(ssid => {
                    const opt = document.createElement('option');
                    opt.value = ssid;
                    opt.textContent = ssid;
                    if (ssid === currentSsid) opt.selected = true;
                    selectSsid.appendChild(opt);
                });
                availableSsidsLoaded = true;
            }
        }

        // Apply Filters
        function applyFilters() {
            const month = document.getElementById('filterMonth').value;
            const isp = document.getElementById('filterIsp').value;
            const ssid = document.getElementById('filterSsid').value;
            const badge = document.getElementById('filterActiveBadge');

            if (month !== 'all' || isp !== 'all' || ssid !== 'all') {
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }

            loadDashboardData();
        }

        // Reset Filters
        function resetFilters() {
            document.getElementById('filterMonth').value = 'all';
            document.getElementById('filterIsp').value = 'all';
            document.getElementById('filterSsid').value = 'all';
            document.getElementById('filterActiveBadge').classList.add('hidden');
            loadDashboardData();
        }

        // Fetch Dashboard Data
        async function loadDashboardData() {
            try {
                const month = document.getElementById('filterMonth').value;
                const isp = document.getElementById('filterIsp').value;
                const ssid = document.getElementById('filterSsid').value;

                let url = `api/data.php?limit=50&month=${encodeURIComponent(month)}&isp=${encodeURIComponent(isp)}&ssid=${encodeURIComponent(ssid)}`;
                const response = await fetch(url);
                const result = await response.json();

                if (result.status !== 'success') return;

                if (result.filters) {
                    updateFilterOptions(result.filters);
                }

                const summary = result.summary;
                const latest = summary.latest;

                // Update Cards
                if (latest) {
                    document.getElementById('statDownload').textContent = parseFloat(latest.download_mbps).toFixed(2);
                    document.getElementById('statUpload').textContent = parseFloat(latest.upload_mbps).toFixed(2);
                    document.getElementById('statPing').textContent = parseFloat(latest.ping_ms || 0).toFixed(2);
                    
                    const ssidSpan = document.getElementById('statSsid').querySelector('span');
                    if (ssidSpan) ssidSpan.textContent = latest.wifi_ssid || 'Koneksi Utama';
                    
                    document.getElementById('statIsp').textContent = 'ISP: ' + (latest.isp_name || '-');
                    document.getElementById('statIp').textContent = latest.client_ip || '-';
                    document.getElementById('statTime').textContent = latest.created_at;
                } else {
                    document.getElementById('statDownload').textContent = '0.00';
                    document.getElementById('statUpload').textContent = '0.00';
                    document.getElementById('statPing').textContent = '0.00';
                    const ssidSpan = document.getElementById('statSsid').querySelector('span');
                    if (ssidSpan) ssidSpan.textContent = 'Tidak Ada Data';
                    document.getElementById('statIsp').textContent = 'ISP: -';
                    document.getElementById('statIp').textContent = '-';
                    document.getElementById('statTime').textContent = '-';
                }

                document.getElementById('statMaxDownload').textContent = summary.max_download + ' M';
                document.getElementById('statAvgDownload').textContent = summary.avg_download + ' M';
                document.getElementById('statMaxUpload').textContent = summary.max_upload + ' M';
                document.getElementById('statAvgUpload').textContent = summary.avg_upload + ' M';
                document.getElementById('statAvgPing').textContent = summary.avg_ping + ' ms';
                document.getElementById('statTotalTests').textContent = summary.total_tests;

                // Update Charts
                if (speedChartInstance && result.chart) {
                    speedChartInstance.data.labels = result.chart.labels;
                    speedChartInstance.data.datasets[0].data = result.chart.downloads;
                    speedChartInstance.data.datasets[1].data = result.chart.uploads;
                    speedChartInstance.update();
                }

                if (pingChartInstance && result.chart) {
                    pingChartInstance.data.labels = result.chart.labels;
                    pingChartInstance.data.datasets[0].data = result.chart.pings;
                    pingChartInstance.update();
                }

                // Update Hourly Risk Section & Matrix
                if (result.hourly_risk) {
                    updateHourlyRiskSection(result.hourly_risk);
                }

                // Update Table
                renderTable(result.logs);

            } catch (err) {
                console.error("Gagal memuat data:", err);
            }
        }

        // Update Hourly Risk Summary & Heatmap Matrix
        function updateHourlyRiskSection(riskData) {
            if (!riskData) return;

            // 1. Worst Hour Card
            const worst = riskData.worst_hour;
            if (worst && worst.risk_score > 0) {
                document.getElementById('valWorstHour').textContent = worst.hour;
                document.getElementById('badgeWorstRisk').textContent = `${worst.risk_score}% (${worst.level})`;
                document.getElementById('badgeWorstRisk').className = `text-[10px] font-mono px-2 py-0.5 rounded border ${
                    worst.risk_score >= 60 ? 'bg-rose-500/20 text-rose-300 border-rose-500/30' : 'bg-amber-500/20 text-amber-300 border-amber-500/30'
                }`;
                document.getElementById('descWorstHour').innerHTML = `
                    <span class="text-amber-400 font-mono">Ping: ${worst.avg_ping} ms</span> • 
                    <span class="text-emerald-400 font-mono">DL: ${worst.avg_download} Mbps</span> (${worst.total_tests} tes)
                `;
            } else {
                document.getElementById('valWorstHour').textContent = '--:--';
                document.getElementById('badgeWorstRisk').textContent = 'N/A';
                document.getElementById('descWorstHour').textContent = 'Belum cukup data';
            }

            // 2. Best Hour Card
            const best = riskData.best_hour;
            if (best && best.risk_score >= 0 && worst) {
                document.getElementById('valBestHour').textContent = best.hour;
                document.getElementById('badgeBestRisk').textContent = `${best.risk_score}% (${best.level})`;
                document.getElementById('descBestHour').innerHTML = `
                    <span class="text-amber-400 font-mono">Ping: ${best.avg_ping} ms</span> • 
                    <span class="text-emerald-400 font-mono">DL: ${best.avg_download} Mbps</span> (${best.total_tests} tes)
                `;
            } else {
                document.getElementById('valBestHour').textContent = '--:--';
                document.getElementById('badgeBestRisk').textContent = 'N/A';
                document.getElementById('descBestHour').textContent = 'Belum cukup data';
            }

            // 3. Segment Averages
            const formatRiskBadge = (val) => {
                if (val === null || val === undefined) return '<span class="text-slate-500">N/A</span>';
                if (val >= 60) return `<span class="text-rose-400 font-bold">${val}% (Rawan)</span>`;
                if (val >= 35) return `<span class="text-amber-400 font-bold">${val}% (Padat)</span>`;
                return `<span class="text-emerald-400 font-bold">${val}% (Lancar)</span>`;
            };
            document.getElementById('valWorkRisk').innerHTML = formatRiskBadge(riskData.avg_work_risk);
            document.getElementById('valNightRisk').innerHTML = formatRiskBadge(riskData.avg_night_risk);
            document.getElementById('valOffpeakRisk').innerHTML = formatRiskBadge(riskData.avg_offpeak_risk);

            // 4. Update Combo Chart
            if (hourlyRiskChartInstance) {
                hourlyRiskChartInstance.data.labels = riskData.labels;
                hourlyRiskChartInstance.data.datasets[0].data = riskData.risk_scores;
                hourlyRiskChartInstance.data.datasets[0].backgroundColor = riskData.risk_colors;
                hourlyRiskChartInstance.data.datasets[1].data = riskData.pings;
                hourlyRiskChartInstance.data.datasets[2].data = riskData.downloads;
                hourlyRiskChartInstance.update();
            }

            // 5. Update Matrix Heatmap Grid (24 Jam)
            const gridContainer = document.getElementById('hourlyMatrixGrid');
            if (gridContainer && riskData.details) {
                let gridHtml = '';
                riskData.details.forEach(item => {
                    const hasData = item.has_data;
                    const bgClass = !hasData 
                        ? 'bg-slate-800/40 border-slate-800 text-slate-500 cursor-not-allowed opacity-50'
                        : item.risk_score >= 60 
                            ? 'bg-rose-500/20 border-rose-500/50 text-rose-300 hover:bg-rose-500/30 ring-1 ring-rose-500/30'
                            : item.risk_score >= 35 
                                ? 'bg-amber-500/20 border-amber-500/50 text-amber-300 hover:bg-amber-500/30'
                                : 'bg-emerald-500/20 border-emerald-500/50 text-emerald-300 hover:bg-emerald-500/30';

                    const clickAttr = hasData 
                        ? `onclick="showHourDetailModal('${item.hour}', ${item.risk_score}, '${item.risk_badge}', ${item.avg_download}, ${item.avg_upload}, ${item.avg_ping}, ${item.avg_jitter}, ${item.total_tests})"`
                        : '';

                    gridHtml += `
                        <button type="button" ${clickAttr} title="${item.hour} - ${item.risk_badge} (Ping: ${item.avg_ping}ms, DL: ${item.avg_download}Mbps)" 
                                class="flex flex-col items-center justify-center p-1.5 rounded-lg border text-center transition-all ${bgClass}">
                            <span class="text-[10px] font-mono font-bold">${item.hour.substring(0, 2)}</span>
                            <span class="text-[9px] font-mono mt-0.5">${hasData ? item.risk_score + '%' : '-'}</span>
                        </button>
                    `;
                });
                gridContainer.innerHTML = gridHtml;
            }
        }

        // Modal Rincian Jam Saat Grid Diklik
        function showHourDetailModal(hour, score, badge, dl, ul, ping, jitter, tests) {
            Swal.fire({
                title: `Analisis Jam ${hour} WITA`,
                html: `
                    <div class="text-sm space-y-2.5 text-left mt-3 font-mono">
                        <div class="flex justify-between border-b border-slate-700/60 pb-1.5"><span class="text-slate-400 font-sans">Status Jam:</span> <b class="${score >= 60 ? 'text-rose-400' : (score >= 35 ? 'text-amber-400' : 'text-emerald-400')}">${badge} (${score}%)</b></div>
                        <div class="flex justify-between border-b border-slate-700/60 pb-1.5"><span class="text-slate-400 font-sans">Rata-rata Ping:</span> <b class="text-amber-400">${ping} ms</b></div>
                        <div class="flex justify-between border-b border-slate-700/60 pb-1.5"><span class="text-slate-400 font-sans">Rata-rata Jitter:</span> <b class="text-slate-300">${jitter} ms</b></div>
                        <div class="flex justify-between border-b border-slate-700/60 pb-1.5"><span class="text-slate-400 font-sans">Rata-rata Download:</span> <b class="text-emerald-400">${dl} Mbps</b></div>
                        <div class="flex justify-between border-b border-slate-700/60 pb-1.5"><span class="text-slate-400 font-sans">Rata-rata Upload:</span> <b class="text-purple-400">${ul} Mbps</b></div>
                        <div class="flex justify-between"><span class="text-slate-400 font-sans">Total Sampel Pengujian:</span> <b class="text-indigo-300">${tests} kali</b></div>
                    </div>
                `,
                icon: score >= 60 ? 'warning' : 'info',
                background: '#151d30',
                color: '#fff',
                confirmButtonColor: '#4f46e5'
            });
        }

        // Render Data Table
        function renderTable(logs) {
            const tbody = document.getElementById('tableBody');
            if (!logs || logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center py-8 text-slate-500 font-sans">Tidak ada data pengujian yang cocok dengan filter.</td></tr>';
                return;
            }

            let html = '';
            logs.forEach(row => {
                const statusBadge = row.status === 'SUCCESS' 
                    ? '<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">SUCCESS</span>'
                    : '<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/30">FAILED</span>';

                html += `
                    <tr class="hover:bg-slate-800/40 transition">
                        <td class="py-3 px-4 font-bold text-slate-400">#${row.id}</td>
                        <td class="py-3 px-4 text-slate-300">${row.created_at}</td>
                        <td class="py-3 px-4 text-amber-400 font-semibold">${parseFloat(row.ping_ms || 0).toFixed(2)} ms</td>
                        <td class="py-3 px-4 text-emerald-400 font-bold">${parseFloat(row.download_mbps || 0).toFixed(2)} Mbps</td>
                        <td class="py-3 px-4 text-purple-400 font-bold">${parseFloat(row.upload_mbps || 0).toFixed(2)} Mbps</td>
                        <td class="py-3 px-4 text-indigo-300 font-sans truncate max-w-[140px]"><i class="fa-solid fa-wifi text-[10px] mr-1"></i>${row.wifi_ssid || '-'}</td>
                        <td class="py-3 px-4 text-slate-300 font-sans truncate max-w-[140px]">${row.isp_name || '-'}</td>
                        <td class="py-3 px-4 text-slate-400 font-sans truncate max-w-[140px]">${row.server_sponsor || row.server_name || '-'}</td>
                        <td class="py-3 px-4 text-center">${statusBadge}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        // Trigger Live Speedtest
        async function triggerSpeedtest() {
            if (isRunning) return;

            isRunning = true;
            const btn = document.getElementById('btnRunTest');
            const btnIcon = document.getElementById('btnIcon');
            const btnText = document.getElementById('btnText');
            const banner = document.getElementById('testProgressBanner');

            btn.disabled = true;
            btnIcon.className = "fa-solid fa-circle-notch fa-spin mr-2";
            btnText.textContent = "Menguji...";
            banner.classList.remove('hidden');

            try {
                const response = await fetch('api/run.php');
                const result = await response.json();

                if (result.status === 'success') {
                    Swal.fire({
                        title: 'Pengujian Sukses!',
                        html: `
                            <div class="text-sm space-y-2 text-left mt-2 font-mono">
                                <div class="flex justify-between border-b border-slate-700/60 pb-1.5"><span class="text-slate-400 font-sans">WiFi / SSID:</span> <b class="text-indigo-400">${result.data.wifi_ssid || '-'}</b></div>
                                <div class="flex justify-between border-b border-slate-700/60 pb-1.5"><span class="text-slate-400 font-sans">Provider:</span> <b class="text-white">${result.data.isp_name || '-'}</b></div>
                                <div class="flex justify-between border-b border-slate-700/60 pb-1.5"><span class="text-slate-400 font-sans">Download:</span> <b class="text-emerald-400 text-base">${result.data.download_mbps} Mbps</b></div>
                                <div class="flex justify-between border-b border-slate-700/60 pb-1.5"><span class="text-slate-400 font-sans">Upload:</span> <b class="text-purple-400 text-base">${result.data.upload_mbps} Mbps</b></div>
                                <div class="flex justify-between border-b border-slate-700/60 pb-1.5"><span class="text-slate-400 font-sans">Ping:</span> <b class="text-amber-400">${result.data.ping_ms} ms</b></div>
                                <div class="flex justify-between text-xs"><span class="text-slate-500 font-sans">Durasi & Engine:</span> <b class="text-slate-300">${result.duration_seconds}s (${result.engine || 'speedtest'})</b></div>
                            </div>
                        `,
                        icon: 'success',
                        background: '#151d30',
                        color: '#fff',
                        confirmButtonColor: '#4f46e5'
                    });
                } else {
                    Swal.fire({
                        title: 'Gagal Menjalankan Tes',
                        text: result.message || result.error || 'Terjadi kesalahan sistem.',
                        icon: 'error',
                        background: '#151d30',
                        color: '#fff',
                        confirmButtonColor: '#4f46e5'
                    });
                }
            } catch (err) {
                Swal.fire({
                    title: 'Koneksi Terputus',
                    text: 'Gagal menghubungi server untuk eksekusi pengujian.',
                    icon: 'warning',
                    background: '#151d30',
                    color: '#fff'
                });
            } finally {
                isRunning = false;
                btn.disabled = false;
                btnIcon.className = "fa-solid fa-bolt-lightning mr-2";
                btnText.textContent = "Uji Kecepatan";
                banner.classList.add('hidden');
                
                availableMonthsLoaded = false;
                availableIspsLoaded = false;
                availableSsidsLoaded = false;
                loadDashboardData();
            }
        }

        // Init on load
        document.addEventListener('DOMContentLoaded', () => {
            initCharts();
            loadDashboardData();
            setInterval(loadDashboardData, 30000);
        });
    </script>
</body>
</html>
