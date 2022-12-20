#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# Script directory
SCRIPT_PATH=$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )

source "$SCRIPT_PATH/common.sh.inc"

TODAYS_DATE=$(date '+%Y%m%d')
DATE_TO_PROCESS=${1:-$TODAYS_DATE}

echo "$(date '+%Y-%m-%d %H:%M:%S') Starting processing of updates for $DATE_TO_PROCESS..."
FILENAME_PATTERN="$CSV_INBOUND_DIR/sap-hr-export_partial_$DATE_TO_PROCESS*.csv"

# The $FILENAME_PATTERN cannot be wrapped in quotes here since we want it to expand to a list of filenames.
echo "$(date '+%Y-%m-%d %H:%M:%S') Copying the combination of $FILENAME_PATTERN to $COMBINED_CSV_PATH..."
cat $FILENAME_PATTERN > "$COMBINED_CSV_PATH"

# The set -e will prevent the following lines from running in case drush returns with a non-zero exit code.
# This can happen e.g. when the script runs into a database deadlock situation, which is beyond our control.
# Therefore we append a '|| true' to make it appear to bash like everything is good.
# This means that this script should be run from cron so that only stdout is logged (using the 1> operator).
# This will leave the stderr free for mailing through cron's standard handling of output from commands.
echo "$(date '+%Y-%m-%d %H:%M:%S') Running Drush command to reset migration just in case it has gotten stuck previously..."
sudo -u "$WEBSERVER_USER" "$DRUSH_BIN" --root="$DOCROOT" migrate:reset ipaas_import_csv || true
echo "$(date '+%Y-%m-%d %H:%M:%S') Running Drush command to import (but not delete) new and updated lines from CSV..."
sudo -u "$WEBSERVER_USER" "$DRUSH_BIN" --root="$DOCROOT" migrate:import --no-progress ipaas_import_csv || true
echo "$(date '+%Y-%m-%d %H:%M:%S') Drush command run."

echo "$(date '+%Y-%m-%d %H:%M:%S') Moving $FILENAME_PATTERN away from $CSV_INBOUND_DIR to $CSV_HANDLED_DIR..."
mv $FILENAME_PATTERN "$CSV_HANDLED_DIR"
echo "$(date '+%Y-%m-%d %H:%M:%S') All done for $DATE_TO_PROCESS."
