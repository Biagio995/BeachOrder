#!/usr/bin/env bash
# Avvia backend + Reverb + frontend in modalità adatta a test locali e DevTunnel.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo "==> Backend Laravel (porta 8000)"
cd "$ROOT/backend"
php artisan serve --host=0.0.0.0 --port=8000 &
BACKEND_PID=$!

echo "==> WebSocket Reverb (porta 8080)"
php artisan reverb:start &
REVERB_PID=$!

echo "==> Frontend Vite preview (porta 5173, build + proxy /api)"
cd "$ROOT/frontend"
npm run dev:tunnel &
FRONTEND_PID=$!

cleanup() {
  echo
  echo "==> Arresto server..."
  kill "$BACKEND_PID" "$REVERB_PID" "$FRONTEND_PID" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

echo
echo "Test locale:  http://localhost:5173"
echo "Demo QR:      http://localhost:5173/q/umbrella12"
echo "Admin:        admin@beachorder.test / password"
echo
echo "Per condividere online: npm run tunnel (dalla cartella frontend)"
echo "Premi Ctrl+C per fermare tutti i server."
echo

wait
