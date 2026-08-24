#!/usr/bin/env bash
# ==============================================================================
# CEPAD Deployment Tool: Smart Git Push
# Format Commit: YYMMDD - [Tipe]: Deskripsi
# ==============================================================================

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR" || exit 1

# Pastikan git diinisialisasi
if [ ! -d ".git" ]; then
    echo "[!] Git repository belum diinisialisasi. Menjalankan git init..."
    git init
    git branch -M main
fi

# Cek perubahan
if [ -z "$(git status --porcelain)" ]; then
    echo "[INFO] Tidak ada perubahan file yang perlu di-commit."
    exit 0
fi

# Tipe commit
COMMIT_TYPE="${1:-feat}"
COMMIT_DESC="${2:-Update speedtest monitoring center & dashboard}"
DATE_PREFIX=$(date +'%y%m%d')

COMMIT_MSG="$DATE_PREFIX - [$COMMIT_TYPE]: $COMMIT_DESC"

echo "=== CEPAD Git Deployment (Push) ==="
echo "Pesan Commit: $COMMIT_MSG"
echo "-----------------------------------"

git add .
git commit -m "$COMMIT_MSG"

# Cek apakah remote origin sudah dipasang
REMOTE_URL=$(git remote get-url origin 2>/dev/null)
if [ -n "$REMOTE_URL" ]; then
    echo "[+] Melakukan push ke origin..."
    git push origin "$(git branch --show-current)"
else
    echo "[i] Remote origin belum diatur. Untuk menghubungkan ke GitHub:"
    echo "    git remote add origin git@github.com:USERNAME/REPO.git"
    echo "    git push -u origin main"
fi

echo "[OK] Commit lokal berhasil dibuat!"
