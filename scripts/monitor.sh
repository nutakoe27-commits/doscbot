#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if [[ -z "${DOSCBOT_DATA_DIR:-}" && -f /var/lib/doscbot-collector/data/dataset.sqlite3 ]]; then
  export DOSCBOT_DATA_DIR=/var/lib/doscbot-collector/data
fi
exec .venv/bin/python -m collector monitor "$@"
