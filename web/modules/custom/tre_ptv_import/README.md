TRE PTV Data Import
===================

This module has the configuration that handles data imports from the PTV API (Palvelutietovaranto).

For now there is just one process to run the updates, and removed data is not handled in any automated fashion.

## Cronjob suggestions

Note that these suggestions assume that there exists at least a directory for the logs (see stdout redirection in each cron command). Since the output is 'concatenated', it is advisable to even create the file in advance.

### Suggestion for twice per hour update import

```
MAILTO=example@example.com
10,40 * * * * root /var/www/html/web/modules/custom/tre_ptv_import/scripts/update_import_no_deletions.sh >> /var/log/drupal/tre_ptv_import/daily_updates.log
```
