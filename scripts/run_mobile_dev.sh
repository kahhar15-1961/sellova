#!/usr/bin/env bash
set -euo pipefail

# One-command Flutter dev run with API_BASE_URL.
# Usage:
#   scripts/run_mobile_dev.sh
#   scripts/run_mobile_dev.sh http://127.0.0.1:8080
#   FLUTTER_DEVICE=chrome scripts/run_mobile_dev.sh

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MOBILE_DIR="${ROOT_DIR}/mobile"

API_BASE_URL="${1:-${API_BASE_URL:-http://127.0.0.1:8000}}"
FLUTTER_DEVICE="${FLUTTER_DEVICE:-}"

cd "${MOBILE_DIR}"

if [[ -n "${FLUTTER_DEVICE}" ]]; then
  flutter run -d "${FLUTTER_DEVICE}" --dart-define="API_BASE_URL=${API_BASE_URL}"
else
  flutter run --dart-define="API_BASE_URL=${API_BASE_URL}"
fi
