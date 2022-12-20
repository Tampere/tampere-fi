TRE HR Data Import
==================

This module has the configuration and scripts that handle data imports from the Tampere-owned IPaaS system's CSV files into Drupal.

There are two processes run in parallel, as follows:

## Full import, weekly

1. IPaaS uploads a 'full' import every Saturday.
2. A cronjob runs the script `scripts/full_import_with_deletions.sh` in the evening each Saturday. You can find suggestions for cronjobs below.

## Updates, daily

1. IPaaS uploads a file with updates every day, if there are changes in the data.
2. A cronjob runs the script `scripts/update_import_no_deletions.sh` in the evening every day. You can find suggestions for cronjobs below.
