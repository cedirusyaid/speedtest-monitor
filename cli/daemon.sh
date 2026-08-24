#!/data/data/com.termux/files/usr/bin/bash
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
    echo "  start [menit]   Jalankan background daemon loop (default interval: 30 menit)"
    echo "  stop            Hentikan background daemon"
    echo "  status          Cek status daemon & koneksi"
    echo "  log             Pantau file log secara real-time"
    echo "  install-cron [m] Pasang jadwal otomatis ke crontab Termux (default: */30 * * * *)"
    echo "  remove-cron     Hapus jadwal dari crontab"
    echo ""
}

run_now() {
    $PHP_BIN "$DIR/test_runner.php"
}

view_logs() {
    local limit="${1:-10}"
    $PHP_BIN "$DIR/view_logs.php" "$limit"
}

start_daemon() {
    local interval="${1:-30}"
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
    local minutes="${1:-30}"
    local cron_cmd="*/$minutes * * * * $PHP_BIN $DIR/test_runner.php >> $LOG_FILE 2>&1"
    
    (crontab -l 2>/dev/null | grep -v "$DIR/test_runner.php" ; echo "$cron_cmd") | crontab -
    echo "[OK] Cron job berhasil dipasang setiap $minutes menit!"
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
