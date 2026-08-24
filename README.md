# ⚡ Speedtest Monitoring Center & Observability Dashboard

Sistem komprehensif pengujian kecepatan internet, observability jaringan, dan analitik bandwidth untuk server lokal Termux LAMP Stack & perangkat Android.

---

## 📁 Struktur Repositori Terpadu

```
~/htdocs/speedtest/
├── .gitignore              # Mengabaikan file log, PID, & konfigurasi lokal
├── README.md               # Dokumentasi sistem
├── push.sh                 # Otomasi add, commit (standar format), smart tagging, & push
├── pull.sh                 # Otomasi git pull sync & migrasi DB
├── schema.sql              # Skema tabel database MariaDB (db_monitoring.log_speedtest)
├── config.php              # Global configuration
├── init_db.php             # Database migration / setup
├── index.php               # Frontend Dashboard UI (Tailwind CSS, FontAwesome, Chart.js)
├── api/
│   ├── data.php            # Endpoint analitik (Filter Bulan, SSID WiFi, & ISP)
│   └── run.php             # Endpoint trigger pengujian kecepatan on-demand (HTTPS secure)
└── cli/
    ├── daemon.sh           # CLI Service & Daemon manager
    ├── test_runner.php     # Background test runner dengan auto-failover engine
    └── view_logs.php       # CLI Table log viewer
```

---

## 🗄️ Skema Database (`db_monitoring.log_speedtest`)

| Kolom | Tipe Data | Keterangan |
| :--- | :--- | :--- |
| `id` | BIGINT AUTO_INCREMENT | Primary Key |
| `ping_ms` | DECIMAL(8,2) | Latency / Ping (ms) |
| `jitter_ms` | DECIMAL(8,2) | Jitter (ms) |
| `download_mbps` | DECIMAL(8,2) | Kecepatan Download (Mbps) |
| `upload_mbps` | DECIMAL(8,2) | Kecepatan Upload (Mbps) |
| `packet_loss_pct`| DECIMAL(5,2) | Packet loss (%) |
| `wifi_ssid` | VARCHAR(100) | Nama SSID WiFi / Tipe Koneksi Jaringan |
| `isp_name` | VARCHAR(150) | Provider Internet (ISP) |
| `server_name` | VARCHAR(150) | Nama Server Pengujian |
| `server_sponsor` | VARCHAR(150) | Sponsor Server |
| `client_ip` | VARCHAR(45) | IP Publik Klien |
| `status` | ENUM('SUCCESS', 'FAILED') | Status Pengujian |
| `created_at` | DATETIME | Waktu Pengujian (WITA) |

---

## 🌐 Akses Web Dashboard

- **Tailscale**: `http://cepad/speedtest/` (atau `http://100.122.111.21/speedtest/`)
- **Lokal**: `http://localhost/speedtest/` (Port 80 / 8080)

---

## 🛠️ Penggunaan CLI Daemon (`cli/daemon.sh`)

```bash
cd ~/htdocs/speedtest/cli

# 1. Jalankan pengujian 1x seketika
./daemon.sh run

# 2. Lihat riwayat log dari MariaDB di terminal
./daemon.sh view

# 3. Pasang cron job otomatis 24/7 (misal: tiap 30 menit)
./daemon.sh install-cron 30
./daemon.sh remove-cron

# 4. Mode Background Daemon (Nohup Loop)
./daemon.sh start 15
./daemon.sh stop

# 5. Cek status
./daemon.sh status
```

---

## 🚀 Git Deployment

```bash
cd ~/htdocs/speedtest

# Commit & Push otomatis
./push.sh feat "Integrasi filter SSID WiFi & perbaikan engine HTTPS"

# Pull pembaruan & sinkronisasi database
./pull.sh
```
