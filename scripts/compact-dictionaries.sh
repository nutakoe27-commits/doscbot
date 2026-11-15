#!/usr/bin/env bash
# Run on collector VPS-1. Restores only services that were active on entry.
set -euo pipefail
[[ $EUID == 0 ]] || { echo 'Run as root on collector VPS-1.'; exit 1; }
repo_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
cd "$repo_dir"
test -x .venv/bin/python
state_dir=/var/lib/doscbot-collector/data
backup_dir="/var/lib/doscbot-collector/compactions/before-$(date -u +%Y%m%dT%H%M%S)-$$"
test "$(systemctl show doscbot-collector.service -p LoadState --value)" = loaded
restart_units=()
restore_services() {
  local result=$?
  trap - EXIT
  for unit in "${restart_units[@]}"; do
    systemctl start "$unit" || { echo "Failed to restart $unit" >&2; result=1; }
  done
  exit "$result"
}
trap restore_services EXIT
for unit in prognozich-model-worker.timer prognozich-model-worker.service doscbot-collector.service; do
  if [[ $(systemctl show "$unit" -p LoadState --value) == loaded ]]; then
    if systemctl is-active --quiet "$unit"; then restart_units+=("$unit"); fi
    systemctl stop "$unit"
  fi
done
printf 'Full backup: %s\n' "$backup_dir"
.venv/bin/python -m collector --state-dir "$state_dir" compact-dictionaries --apply --backup-output "$backup_dir"
