#!/usr/bin/env bash
# ==============================================================================
# CEPAD Deployment Tool: Smart Git Pull & Migration Sync
# ==============================================================================

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR" || exit 1

echo "=== CEPAD Git Sync (Pull) ==="

REMOTE_URL=$(git remote get-url origin 2>/dev/null)
if [ -n "$REMOTE_URL" ]; then
    BRANCH_NAME=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "main")
    echo "[+] Mengunduh pembaruan terbaru dari remote ($BRANCH_NAME)..."
    git pull --no-rebase origin "$BRANCH_NAME"
else
    echo "[INFO] Remote origin belum diatur. Melewati git pull."
fi

# Jalankan migrasi database jika ada
if [ -f "init_db.php" ]; then
    echo "[+] Menjalankan migrasi database..."
    php init_db.php
fi

echo "[OK] Sinkronisasi selesai!"
