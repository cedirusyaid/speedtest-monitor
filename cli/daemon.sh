#!/usr/bin/env bash
# ==============================================================================
# Speedtest Center CLI Management & Daemon
# ==============================================================================

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$DIR")"
PID_FILE="$DIR/daemon.pid"
LOG_FILE="$ROOT_DIR/speedtest.log"
PHP_BIN="$(command -v php)"

show_help() {
    echo "Speedtest Center CLI Manager"
    echo "Penggunaan: ./daemon.sh [perintah]"
    echo ""
    echo "Perintah yang tersedia:"
    echo "  run             Jalankan pengujian kecepatan sekarang (1x test & simpan DB)"
    echo "  view [limit]    Tampilkan riwayat hasil pengujian dari database (default: 10)"
    echo "  notif-status    Periksa status pengiriman notifikasi Telegram (apakah sudah terkirim hari ini)"
    echo "  test-notif      Jalankan tes & paksa kirim notifikasi Telegram seketika"
    echo "  start [menit]   Jalankan background daemon loop (default interval: 60 menit)"
    echo "  stop            Hentikan background daemon"
    echo "  status          Cek status daemon & crontab"
    echo "  log             Pantau file log secara real-time"
    echo "  install-cron [menit] Pasang jadwal otomatis di crontab (default: 30 menit -> */30 * * * *)"
    echo "  remove-cron     Hapus jadwal dari crontab"
    echo ""
}

run_now() {
    $PHP_BIN "$DIR/test_runner.php"
}

test_notif() {
    echo "[+] Menjalankan pengujian & memicu pengiriman Telegram..."
    $PHP_BIN "$DIR/test_runner.php" --force-telegram
}

view_logs() {
    local limit="${1:-10}"
    $PHP_BIN "$DIR/view_logs.php" "$limit"
}

check_notif_status() {
    $PHP_BIN -r "
        \$config = require '$ROOT_DIR/config.php';
        \$tgCfg  = \$config['telegram'] ?? [];
        require '$DIR/telegram_helper.php';
        
        \$tzName = \$config['app']['timezone'] ?? 'Asia/Makassar';
        \$tzLabel = (\$tzName === 'Asia/Makassar') ? 'WITA' : \$tzName;
        \$today  = date('Y-m-d');
        \$key    = \$today . ' 09';
        \$nowH   = date('H:i');
        
        echo \"=== Status Notifikasi Telegram Speedtest ===\n\";
        echo \"Zona Waktu    : {\$tzName} ({\$tzLabel} / {\$config['app']['mysql_tz_offset']})\n\";
        echo \"Waktu Saat Ini: \$today \$nowH {\$tzLabel}\n\";
        echo \"Target Jadwal : Setiap hari jam 09:00 - 09:59 {\$tzLabel}\n\";
        echo \"--------------------------------------------\n\";

        try {
            \$pdo = get_speedtest_db_pdo(\$config);
            \$res = check_telegram_already_sent(\$key, 'DAILY_09AM', \$pdo, \$tgCfg['cache_file'] ?? null);
            
            if (\$res['already_sent']) {
                echo \"[STATUS] SUDAH TERKIRIM HARI INI ✅\n\";
                echo \"Waktu Kirim : {\$res['sent_at']} {\$tzLabel}\n\";
                echo \"Speedtest ID: #{\$res['speedtest_id']}\n\";
                echo \"Terverifikasi: via {\$res['source']}\n\";
            } else {
                echo \"[STATUS] BELUM DIKIRIM HARI INI ⏳\n\";
                echo \"Keterangan : Akan otomatis dikirim pada eksekusi jam 09:xx {\$tzLabel}.\n\";
            }
            
            echo \"\n--- 5 Riwayat Notifikasi Telegram Terakhir ---\n\";
            \$stmt = \$pdo->query(\"SELECT id, notif_type, period_key, speedtest_id, sent_at, status FROM log_speedtest_notif ORDER BY id DESC LIMIT 5\");
            \$rows = \$stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty(\$rows)) {
                echo \"Belum ada catatan riwayat notifikasi.\n\";
            } else {
                printf(\"%-4s | %-12s | %-14s | %-6s | %-19s | %-7s\n\", 'ID', 'Tipe', 'Periode', 'TestID', 'Waktu Kirim', 'Status');
                echo \"----------------------------------------------------------------------\n\";
                foreach (\$rows as \$r) {
                    printf(\"%-4d | %-12s | %-14s | #%-5s | %-19s | %-7s\n\",
                        \$r['id'], \$r['notif_type'], \$r['period_key'], \$r['speedtest_id'] ?? '-', \$r['sent_at'], \$r['status']
                    );
                }
            }
            echo \"============================================\n\";
        } catch (Exception \$e) {
            echo \"[ERROR] Gagal membaca status: \" . \$e->getMessage() . \"\n\";
        }
    "
}

start_daemon() {
    local interval="${1:-60}"
    if [ -f "$PID_FILE" ]; then
        local pid=$(cat "$PID_FILE")
        if kill -0 "$pid" 2>/dev/null; then
            echo "[INFO] Daemon sudah aktif dengan PID: $pid"
            return 0
        fi
    fi

    echo "[+] Memulai Speedtest Background Daemon (Interval: $interval menit)..."
    nohup bash -c "
        while true; do
            $PHP_BIN \"$DIR/test_runner.php\" >> \"$LOG_FILE\" 2>&1
            sleep $(( interval * 60 ))
        done
    " > /dev/null 2>&1 &
    
    local new_pid=$!
    echo "$new_pid" > "$PID_FILE"
    echo "[OK] Daemon aktif dengan PID: $new_pid"
}

stop_daemon() {
    if [ -f "$PID_FILE" ]; then
        local pid=$(cat "$PID_FILE")
        if kill -0 "$pid" 2>/dev/null; then
            kill "$pid" 2>/dev/null
            rm -f "$PID_FILE"
            echo "[OK] Daemon (PID: $pid) berhasil dihentikan."
        else
            rm -f "$PID_FILE"
            echo "[INFO] Daemon tidak aktif (PID stale dibersihkan)."
        fi
    else
        echo "[INFO] Tidak ada daemon yang sedang berjalan."
    fi
}

check_status() {
    echo "=== Status Speedtest Center Daemon ==="
    if [ -f "$PID_FILE" ]; then
        local pid=$(cat "$PID_FILE")
        if kill -0 "$pid" 2>/dev/null; then
            echo "Daemon Service: RUNNING (PID: $pid)"
        else
            echo "Daemon Service: STOPPED (Stale PID file)"
        fi
    else
        echo "Daemon Service: STOPPED"
    fi

    local cron_entry=$(crontab -l 2>/dev/null | grep "$DIR/test_runner.php")
    if [ -n "$cron_entry" ]; then
        echo "Crontab Job   : ACTIVE ($cron_entry)"
    else
        echo "Crontab Job   : NOT CONFIGURED"
    fi

    echo ""
    view_logs 5
}

tail_log() {
    if [ -f "$LOG_FILE" ]; then
        tail -n 30 -f "$LOG_FILE"
    else
        echo "[INFO] Belum ada log tercatat ($LOG_FILE)."
    fi
}

install_cron() {
    local interval="${1:-30}"
    local cron_schedule
    if [ "$interval" -ge 60 ]; then
        cron_schedule="0 * * * *"
    else
        cron_schedule="*/${interval} * * * *"
    fi
    local cron_cmd="$cron_schedule $PHP_BIN $DIR/test_runner.php >> $LOG_FILE 2>&1"
    
    (crontab -l 2>/dev/null | grep -v "$DIR/test_runner.php" ; echo "$cron_cmd") | crontab -
    echo "[OK] Cron job berhasil dipasang setiap $interval menit!"
    echo "Jadwal: $cron_schedule"
    echo "Entry: $cron_cmd"
}

remove_cron() {
    (crontab -l 2>/dev/null | grep -v "$DIR/test_runner.php") | crontab -
    echo "[OK] Cron job speedtest monitor dihapus dari crontab."
}

case "$1" in
    run)
        run_now
        ;;
    view)
        view_logs "$2"
        ;;
    notif-status)
        check_notif_status
        ;;
    test-notif)
        test_notif
        ;;
    start)
        start_daemon "$2"
        ;;
    stop)
        stop_daemon
        ;;
    status)
        check_status
        ;;
    log)
        tail_log
        ;;
    install-cron)
        install_cron "$2"
        ;;
    remove-cron)
        remove_cron
        ;;
    *)
        show_help
        ;;
esac
