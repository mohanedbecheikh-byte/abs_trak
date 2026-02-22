#!/usr/bin/env bash
set -euo pipefail

BRANCH="${1:-main}"
COMPOSE_FILE="${2:-docker-compose.prod.yml}"

if ! command -v git >/dev/null 2>&1; then
  echo "git is required." >&2
  exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
  echo "docker is required." >&2
  exit 1
fi

if [ ! -f ".env" ]; then
  cp .env.example .env
  echo "Created .env from .env.example. Update secrets before exposing this server publicly." >&2
fi

git fetch origin
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

docker compose -f "$COMPOSE_FILE" up -d --build
docker compose -f "$COMPOSE_FILE" ps

echo "Deployment completed for branch: $BRANCH"
