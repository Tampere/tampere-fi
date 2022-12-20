TRE WP Sites Import
===================

This module has the configuration that handles the import of data regarding the WordPress cluster of sites managed by Tampere.

## Cronjob suggestions

### Suggestion for daily update import

```
MAILTO=example@example.com
*/5 * * * * apache /usr/local/bin/drush -r /var/www/html/web migrate:import --no-progress tre_wp_sites_import
```
