#!/usr/bin/env bash
# Avvia inbox mail di test (SMTP 1025, UI 8025) per reset password e verifica nuovo tenant.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if docker info >/dev/null 2>&1; then
  echo "==> Mailpit via Docker (http://127.0.0.1:8025)"
  docker compose up -d mailpit
  exit 0
fi

echo "==> Docker non disponibile, avvio MailDev (npx) su SMTP 1025 / UI 8025"
echo "    Inbox: http://127.0.0.1:8025"
exec npx --yes maildev --smtp 1025 --web 8025 --ip 127.0.0.1
