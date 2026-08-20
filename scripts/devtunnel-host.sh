#!/usr/bin/env bash
# Espone la porta 5173 via Microsoft Dev Tunnel (URL pubblico per test/demo).
set -euo pipefail

PORT="${1:-5173}"

if ! command -v devtunnel >/dev/null 2>&1; then
  echo "Dev Tunnel CLI non trovato. Installalo con: winget install Microsoft.devtunnel"
  exit 1
fi

if ! devtunnel user show >/dev/null 2>&1; then
  echo "Accesso Dev Tunnel richiesto. Si aprirà il browser..."
  devtunnel user login -g
fi

echo "==> Hosting porta $PORT (anonimo, descrizione: Servio demo)"
echo
echo "Prima di avviare il tunnel:"
echo "  1. bash scripts/start-test-env.sh   (backend :8001 + frontend :5173)"
echo "     oppure npm run dev:tunnel nella cartella frontend + php artisan serve"
echo "  2. Copia l'URL https://...devtunnels.ms che apparirà sotto"
echo "  3. In backend/.env imposta:"
echo "       FRONTEND_URL=https://TUO-ID-${PORT}.euw.devtunnels.ms"
echo "       PUBLIC_URL=https://TUO-ID-${PORT}.euw.devtunnels.ms"
echo "  4. Riavvia il backend: php artisan serve (o riavvia start-test-env.sh)"
echo
echo "Admin pagamenti Nexi: https://...devtunnels.ms/admin/settings/payments"
echo "Reverb deve essere attivo: php artisan reverb:start (cartella backend)"
echo

exec devtunnel host \
  -p "$PORT" \
  -a \
  --description "Servio demo" \
  --labels servio demo
