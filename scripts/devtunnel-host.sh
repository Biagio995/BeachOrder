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

echo "==> Hosting porta $PORT (anonimo, descrizione: BeachOrder demo)"
echo "    Assicurati che frontend sia in esecuzione su http://localhost:$PORT"
echo "    (npm run dev:tunnel nella cartella frontend)"
echo "    Reverb deve essere attivo: php artisan reverb:start (cartella backend)"
echo

exec devtunnel host \
  -p "$PORT" \
  -a \
  --description "BeachOrder demo" \
  --labels beachorder demo presaordini
