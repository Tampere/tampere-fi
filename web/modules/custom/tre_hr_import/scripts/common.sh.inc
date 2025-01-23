#!/usr/bin/env bash

DOCROOT=/var/www/html/web
APPDIR=$(dirname $(readlink -f $DOCROOT 2>/dev/null) 2>/dev/null) || APPDIR=/var/www/html
DRUSH_BIN="$APPDIR/vendor/bin/drush"
WEBSERVER_USER="apache"
MAIL_ERROR=example@example.com

# Set CSV_INBOUND_DIR only if it's not already set
CSV_INBOUND_DIR=${CSV_INBOUND_DIR:=/var/www/sftp/tietoevry/uploads}
CSV_HANDLED_DIR=/var/www/ipaas_processed
mkdir -p "$CSV_HANDLED_DIR"

# This path will need to exist in Drupal's settings(.local).php too, overriding
# the config:
# $config['migrate_plus.migration.ipaas_import_csv']['source']['path']
COMBINED_CSV_PATH=/var/www/html/shared/files_temporary/ipaas_data.csv
