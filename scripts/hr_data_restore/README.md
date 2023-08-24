# HR Data restoration project

On 2023-06-20 a production update deleted duplicate nodes imported via the
ipaas_import_csv integration. However, we made a mistake at that time assuming
that only direct references used in Drupal were the nodes that mattered. In
actual fact the correct course of action would have been to leave behind all
person content in published state.

This series of scripts fixed the situation partially: the
person_data_gatherer.php script was run against the backup before the
production update, generating files used by the person_data_updater.php to
update the publishing status and the enrichment data of the content in the new
production database.

The scripts were run against the 2023-06-20 backup of the database (the
person_data_gatherer script) and then against production (the
person_data_updater script) on 2023-07-04. This resulted in 541 new published
person nodes that were originally imported via the integration and 30 new
manually created person nodes to be published in production.
