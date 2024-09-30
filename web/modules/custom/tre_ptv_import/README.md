TRE PTV Data Import
===================

This module has the configuration that handles data imports from the PTV API (Palvelutietovaranto).

For now there is just one process to run the updates, and removed data is not handled in any automated fashion.

## The PHP client library for PTV API

This module uses a PHP client library, tampere/ptv-php-client-v11, that can be installed using composer. The library is
not listed as a dependency in this module's composer.json but the correct version at the time of this writing is `2.x-dev#cf87d33`.

### Testing updates to the PHP client library

In the future as this library gets more updates, the more recent versions may be installed using the project's root composer.json and then the validity of the update can be verified using a sequence as stated here.

First, run the regular update of PTV content and save the results into JSON files (combine all of the code into a
one-liner for convenience):

```
ddev drush tre_ptv_import:ptv_data_refresh --refresh-cache && \
ddev drush migrate:import --update ptv_service_locations,ptv_service_channels,ptv_services,ptv_service_locations_en,ptv_service_channels_en,ptv_services_en && \
mkdir -p tests/ptv_content_orig && \
for ct in place_of_business ptv_service service_channel map_point; do
  for lang in fi en; do
    ddev drush tre_node_json_api_static:writefile --orderby=uuid:ASC "${ct}" "${lang}";
    cp "web/sites/default/files/api_json/${ct}_${lang}.json" "tests/ptv_content_orig/";
  done;
done
```

Then, update the library using composer and run the regular update of PTV content and save the results into JSON files
in a different directory (again, combine all of the code into a one-liner for convenience):

```
ddev drush tre_ptv_import:ptv_data_refresh --refresh-cache && \
ddev drush migrate:import --update ptv_service_locations,ptv_service_channels,ptv_services,ptv_service_locations_en,ptv_service_channels_en,ptv_services_en && \
mkdir -p tests/ptv_content_new && \
for ct in place_of_business ptv_service service_channel map_point; do
  for lang in fi en; do
    ddev drush tre_node_json_api_static:writefile --orderby=uuid:ASC "${ct}" "${lang}";
    cp "web/sites/default/files/api_json/${ct}_${lang}.json" "tests/ptv_content_new/";
  done;
done
```

After that, the validity of the update can be verified by doing a simple comparison between the original and the new
JSON content. If there are no differences, the update has been valid and the updated version should be safe to use.

```
diff -qr tests/ptv_content_orig/ tests/ptv_content_new/
```

You may also consider additional exported fields e.g. in the place_of_business method
`\Drupal\tre_node_json_api_static\Commands\JsonApiReplacementBatch::getDataForPlaceOfBusiness()` for the duration of the
testing. There are examples for reference fields in the other methods (e.g. `field_addresses` in `place_of_business` may
be of interest).

## Cronjob suggestions

Note that these suggestions assume that there exists at least a directory for the logs (see stdout redirection in each cron command). Since the output is 'concatenated', it is advisable to even create the file in advance.

### Suggestion for once-a-day update import

Since there now exists an update functionality for individual nodes, there should be no need to update the complete service list more than once a day.

```
MAILTO=example@example.com
25 0 * * * root timeout 60m /var/www/html/web/modules/custom/tre_ptv_import/scripts/update_import_no_deletions.sh >> /var/log/drupal/tre_ptv_import/daily_updates.log 2>&1 && echo "Ok! Imported PTV data on $(date '+\%F \%T \%z')!" || echo "Error! Time ran out after 60 minutes for PTV daily updates on $(date '+\%F \%T \%z')."
```

### Suggestion for twice per hour update import

```
MAILTO=example@example.com
10,40 * * * * root /var/www/html/web/modules/custom/tre_ptv_import/scripts/update_import_no_deletions.sh >> /var/log/drupal/tre_ptv_import/daily_updates.log
```

### Suggestion for running the individual update queues more often

```
MAILTO=example@example.com
* * * * * root /usr/local/bin/drush -r /var/www/html/web queue:run ptv_api_migrations --time-limit=40 >> /var/log/drupal/tre_ptv_import/queue_api_updates.log
* * * * * root /usr/local/bin/drush -r /var/www/html/web queue:run ptv_node_migrations --time-limit=40 >> /var/log/drupal/tre_ptv_import/queue_node_updates.log
```

## Future improvements

There are two typical points where the PTV API may time out: one is when service channels are being requested in a loop
and the other is when organizations are being fetched as part of the service_channel source plugin processing. The
errors produced look a bit different from each other but each has a place where they could be addressed in our code by,
for example, retrying the request if it returns with an exception. The error messages are as follows, respectively:

```
In CorrectedServiceChannelApi.php line 201:
 [504] Server error: `GET https://api.palvelutietovaranto.suomi.fi/api/v11/ServiceChannel/list?guids=d9f4928b-5686-4e5b-bedb-94451f2815ba%2Ced8a
  774a-c376-4acb-b407-de0e43463ec6%2C17239243-e74a-420e-a15e-29fcc4371810%2Cddabd4f5-1e3a-4ef8-822f-44b1c472ad8c%2Cf5dbbc4e-8be1-4c2f-98b1-c51a46
  0ec725%2C40bf7c4b-32a9-491b-88b2-27701785cd6a%2C22c04679-3741-4589-af72-8e617d3b9281%2C84e54b36-472d-4b9f-a44b-84a9a26b8479%2Cb6c033e8-c30b-450
  0-ad2b-5ec757836644%2Cfaf910c2-06c0-4b9d-9805-df71e44acbc2%2C8075dd30-9f35-4882-9d8d-cdb2a70135d4%2C05a60058-36fe-4184-a653-b2e240c435bb%2Cec55
  a62f-11f5-4e9f-8875-ec91636868e8%2C5ed134bb-c79f-4f05-8542-0eaa3cab05af%2C50d47c30-bd22-4a21-9f11-17fe477321af%2Cc7134c9e-8dd8-4311-873f-6a0fc7
  3a410b%2C263984e3-aadc-428a-878e-3171162ab30c%2C829da63b-e9cb-4c93-b275-8a7f1c14f4f8%2C81d59de2-ea27-4437-8e87-0d9ad0107db5%2C8765a6f0-649f-46f
  0-b556-4314c14ee544&showHeader=true` resulted in a `504 Gateway Time-out` response:
  <html>
  <head><title>504 Gateway Time-out</title></head>
  <body>
  <center><h1>504 Gateway Time-out</h1></center>
  </body (truncated...)
```
This error could be checked for in `\Drupal\tre_ptv_import\Service\PtvServiceChannelListIterator::getPageItems()`.

```
error]  Migration failed with source plugin exception: [504] Server error: `GET https://api.palvelutietovaranto.suomi.fi/api/v11/Organization/e6352b54-0121-438b-8ff7-dc02967d72fa?showHeader=false` resulted in a `504 Gateway Time-out` response:
&lt;html&gt;
&lt;head&gt;&lt;title&gt;504 Gateway Time-out&lt;/title&gt;&lt;/head&gt;
&lt;body&gt;
&lt;center&gt;&lt;h1&gt;504 Gateway Time-out&lt;/h1&gt;&lt;/center&gt;
&lt;/body (truncated...)
 in /var/www/html/drupal/vendor/tampere/ptv-php-client-v11/src/PtvApi/OrganizationApi.php line 2111
```
This error could be checked for in `\Drupal\tre_ptv_import\Service\PtvDataHelpers::getOrganizationNameByLanguage()`.
