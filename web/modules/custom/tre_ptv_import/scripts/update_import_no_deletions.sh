#!/usr/bin/env bash
# Removing 'set -e' to enable 'until' command work its magic.
# @see https://gist.github.com/mohanpedala/1e2ff5661761d3abd0385e8223e16425
# @see http://redsymbol.net/articles/unofficial-bash-strict-mode/
set -uo pipefail
IFS=$'\n\t'

# Script directory
SCRIPT_PATH=$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )

source "$SCRIPT_PATH/common.sh.inc"

# This script should be run from cron so that only stdout is logged (using the 1> operator).
# This will leave the stderr free for mailing through cron's standard handling of output from commands.
# Due to the nature of the 'until' command, this script should probably be run using the 'timeout' command to prevent it
# from running until eternity.
echo "$(date '+%Y-%m-%d %H:%M:%S') Running Drush command to refresh content from PTV into storage..."
refresh_retries=0
until
  sudo -u "$WEBSERVER_USER" "$DRUSH_BIN" tre_ptv_import:ptv_data_refresh --refresh-cache
do
  # Artificial resting break for the remote end.
  sleep 1
  ((refresh_retries++))
  echo "$(date '+%Y-%m-%d %H:%M:%S') Retry of data refresh number $refresh_retries..."
done
echo "$(date '+%Y-%m-%d %H:%M:%S') Drush refresh command run."
echo "$(date '+%Y-%m-%d %H:%M:%S') Running Drush command to import (but not delete) new and updated lines from PTV storage..."
for MIGRATION in ${MIGRATIONS[@]}; do
  migrate_retries=0
  until
    sudo -u "$WEBSERVER_USER" "$DRUSH_BIN" migrate:reset --quiet "$MIGRATION"
    sudo -u "$WEBSERVER_USER" "$DRUSH_BIN" migrate:import --no-progress "$MIGRATION"
  do
    # Artificial resting break for the remote end.
    sleep 1
    ((migrate_retries++))
    echo "$(date '+%Y-%m-%d %H:%M:%S') Retry of migration $MIGRATION number $migrate_retries..."
  done
done
echo "$(date '+%Y-%m-%d %H:%M:%S') Drush import command command run."
