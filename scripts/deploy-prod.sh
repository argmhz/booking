#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="${ROOT_DIR}/docker-compose.prod.yml"

SKIP_BUILD=0
SKIP_MIGRATE=0
ALLOW_DIRTY=0

print_usage() {
  cat <<'USAGE'
Usage: scripts/deploy-prod.sh [options]

Options:
  --skip-build      Skip docker image build step
  --skip-migrate    Skip artisan migrate --force step
  --allow-dirty     Allow running even if git working tree has changes
  -h, --help        Show this help message
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --skip-build)
      SKIP_BUILD=1
      shift
      ;;
    --skip-migrate)
      SKIP_MIGRATE=1
      shift
      ;;
    --allow-dirty)
      ALLOW_DIRTY=1
      shift
      ;;
    -h|--help)
      print_usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      print_usage
      exit 1
      ;;
  esac
done

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing required command: $1" >&2
    exit 1
  fi
}

require_cmd git
require_cmd docker

if [[ ! -f "$COMPOSE_FILE" ]]; then
  echo "Cannot find compose file: $COMPOSE_FILE" >&2
  exit 1
fi

cd "$ROOT_DIR"

if [[ "$ALLOW_DIRTY" -ne 1 ]] && [[ -n "$(git status --porcelain)" ]]; then
  echo "Git working tree is dirty. Commit/stash changes or run with --allow-dirty." >&2
  git status --short
  exit 1
fi

echo "==> Fetching latest main branch"
git fetch origin main
git checkout main
git pull --ff-only origin main

echo "==> Building and restarting production services"
if [[ "$SKIP_BUILD" -eq 0 ]]; then
  docker compose -f "$COMPOSE_FILE" build --pull app
fi

docker compose -f "$COMPOSE_FILE" up -d --remove-orphans --force-recreate

if [[ "$SKIP_MIGRATE" -eq 0 ]]; then
  echo "==> Running database migrations"
  docker compose -f "$COMPOSE_FILE" exec -T app php artisan migrate --force
fi

echo "==> Current container status"
docker compose -f "$COMPOSE_FILE" ps

echo "Deploy complete."

