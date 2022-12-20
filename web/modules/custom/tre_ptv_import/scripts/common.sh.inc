#!/usr/bin/env bash

DOCROOT=/var/www/html/web
APPDIR=$(dirname $(readlink -f $DOCROOT 2>/dev/null) 2>/dev/null) || APPDIR=/var/www/html
DRUSH_BIN="$APPDIR/vendor/bin/drush"
WEBSERVER_USER="apache"
MAIL_ERROR=example@example.com
MIGRATIONS=(ptv_service_locations ptv_service_channels ptv_services ptv_service_locations_en ptv_service_channels_en ptv_services_en)
