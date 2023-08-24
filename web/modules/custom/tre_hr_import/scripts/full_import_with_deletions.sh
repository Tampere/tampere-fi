#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# Script directory
SCRIPT_PATH=$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )

source "$SCRIPT_PATH/common.sh.inc"

TODAYS_DATE=$(date '+%Y%m%d')
DATE_TO_PROCESS=${1:-$TODAYS_DATE}

echo "$(date '+%Y-%m-%d %H:%M:%S') Starting processing of full import for $DATE_TO_PROCESS..."
FILENAME_PATTERN="$CSV_INBOUND_DIR/sap-hr-export_full_$DATE_TO_PROCESS*.csv"

# The $FILENAME_PATTERN cannot be wrapped in quotes here since we want it to expand to a list of filenames.
echo "$(date '+%Y-%m-%d %H:%M:%S') Copying the combination of $FILENAME_PATTERN to $COMBINED_CSV_PATH..."
cat $FILENAME_PATTERN > "$COMBINED_CSV_PATH"

# If the number of lines to delete exceeds 10 % of the existing amount of 'person' nodes, we skip automatic
# handling and alert via Slack.
NUMBER_OF_ROWS_STRING=$(wc -l <"$COMBINED_CSV_PATH")
echo "$(date '+%Y-%m-%d %H:%M:%S') Found $NUMBER_OF_ROWS_STRING lines in the inbound CSV files in total."
PERCENTAGE=$("$DRUSH_BIN" --root="$DOCROOT" tre_hr_import:calculate_persons_percentage $NUMBER_OF_ROWS_STRING)
echo "$(date '+%Y-%m-%d %H:%M:%S') Found that the number of lines is $PERCENTAGE % of existing 'person' nodes currently in Drupal."

MINIMUM_PERCENTAGE=${2:-90}
if [ "$MINIMUM_PERCENTAGE" -lt 50 ]; then
  echo "$(date '+%Y-%m-%d %H:%M:%S') Minimum percentages below 50 are not acceptable. Please use a higher minimum percentage limit."
elif [ "$PERCENTAGE" -lt "$MINIMUM_PERCENTAGE" ]; then
  echo "$(date '+%Y-%m-%d %H:%M:%S') Under $MINIMUM_PERCENTAGE % of lines in CSV, so bailing out. Mailing summary of percentage to $MAIL_ERROR to signal that no further action was taken."
  echo "$(date '+%Y-%m-%d %H:%M:%S') Only $NUMBER_OF_ROWS_STRING lines in the CSV ($COMBINED_CSV_PATH), which would mean that only $PERCENTAGE percent of person nodes would remain in the system after the import (the rest would get deleted)." |mail -s 'TRE HR Import canceled' "$MAIL_ERROR"
else
  # Since we're using migrate_tools and since it overrides Drush built-in commands, the switch to use is --sync.
  # The set -e will prevent the following lines from running in case drush returns with a non-zero exit code.
  # This can happen e.g. when the script runs into a database deadlock situation, which is beyond our control.
  # Therefore we append a '|| true' to make it appear to bash like everything is good.
  # This means that this script should be run from cron so that only stdout is logged (using the 1> operator).
  # This will leave the stderr free for mailing through cron's standard handling of output from commands.
  echo "$(date '+%Y-%m-%d %H:%M:%S') Running Drush command to reset migration just in case it has gotten stuck previously..."
  sudo -u "$WEBSERVER_USER" "$DRUSH_BIN" --root="$DOCROOT" migrate:reset ipaas_import_csv || true
  echo "$(date '+%Y-%m-%d %H:%M:%S') Running Drush command to import new and updated lines, and NOT delete (for the summer 2023) the ones missing, from CSV..."
  # Temporarily disabled the --sync option for summer 2023 until we get to fixing the underlying issue.
  sudo -u "$WEBSERVER_USER" "$DRUSH_BIN" --root="$DOCROOT" migrate:import --update --skip-progress-bar ipaas_import_csv || true
  echo "$(date '+%Y-%m-%d %H:%M:%S') Drush command run."
fi

echo "$(date '+%Y-%m-%d %H:%M:%S') Moving $FILENAME_PATTERN away from $CSV_INBOUND_DIR to $CSV_HANDLED_DIR..."
mv $FILENAME_PATTERN "$CSV_HANDLED_DIR"
echo "$(date '+%Y-%m-%d %H:%M:%S') All done for $DATE_TO_PROCESS."
