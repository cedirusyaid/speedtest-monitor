#!/data/data/com.termux/files/usr/share/apache2/default-site/htdocs/speedtest/pull.sh
# ==============================================================================
# CEPAD Deployment Tool: Smart Git Pull & Migration Sync
# ==============================================================================

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR" || exit 1

echo "=== CEPAD Git Sync (Pull) ==="

REMOTE_URL=$(git remote get-url origin 2>/dev/null)
if [ -n "$REMOTE_URL" ]; then
    echo "[+] Mengunduh pembaruan terbaru dari remote..."
    git pull --no-rebase origin "$(git branch --show-current 2>/dev/null || echo 'main')"
else
    echo "[INFO] Remote origin belum diatur. Melewati git pull."
fi

# Jalankan migrasi database jika ada
if [ -f "init_db.php" ]; then
    echo "[+] Menjalankan migrasi database..."
    php init_db.php
fi

echo "[OK] Sinkronisasi selesai!"
