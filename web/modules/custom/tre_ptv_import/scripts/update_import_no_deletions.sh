#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# Script directory
SCRIPT_PATH=$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )

source "$SCRIPT_PATH/common.sh.inc"

# The set -e will prevent the following lines from running in case drush returns with a non-zero exit code.
# This can happen e.g. when the script runs into a database deadlock situation, which is beyond our control.
# Therefore we append a '|| true' to make it appear to bash like everything is good.
# This means that this script should be run from cron so that only stdout is logged (using the 1> operator).
# This will leave the stderr free for mailing through cron's standard handling of output from commands.
echo "$(date '+%Y-%m-%d %H:%M:%S') Running Drush command to refresh content from PTV into storage..."
sudo -u "$WEBSERVER_USER" "$DRUSH_BIN" --root="$DOCROOT" tre_ptv_import:ptv_data_refresh --refresh-cache || true
echo "$(date '+%Y-%m-%d %H:%M:%S') Drush refresh command run."
echo "$(date '+%Y-%m-%d %H:%M:%S') Running Drush command to import (but not delete) new and updated lines from PTV storage..."
for MIGRATION in ${MIGRATIONS[@]}; do
  sudo -u "$WEBSERVER_USER" "$DRUSH_BIN" --root="$DOCROOT" migrate:import --no-progress $MIGRATION || true
done
echo "$(date '+%Y-%m-%d %H:%M:%S') Drush import command command run."
