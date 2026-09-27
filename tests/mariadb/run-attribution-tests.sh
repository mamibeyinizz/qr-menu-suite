#!/usr/bin/env bash
# Phase 6.2 — MariaDB attribution test koşucusu (tests/docker db servisi).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REPO="$(cd "$ROOT/.." && pwd)"
COMPOSE_DIR="$ROOT/docker"

cd "$COMPOSE_DIR"
if [[ ! -f .env ]]; then
	cp .env.example .env
fi

docker compose up -d db

echo "MariaDB healthcheck bekleniyor..."
for _ in $(seq 1 40); do
	if docker compose exec -T db healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then
		break
	fi
	sleep 2
done

export QRMS_MARIADB_HOST="${QRMS_MARIADB_HOST:-127.0.0.1}"
export QRMS_MARIADB_PORT="${QRMS_MARIADB_PORT:-13306}"
export QRMS_MARIADB_NAME="${QRMS_MARIADB_NAME:-qrms_test}"
export QRMS_MARIADB_USER="${QRMS_MARIADB_USER:-qrms_test}"
export QRMS_MARIADB_PASSWORD="${QRMS_MARIADB_PASSWORD:-qrms_test_pw}"

cd "$REPO"
php tests/test-attribution-mariadb.php
