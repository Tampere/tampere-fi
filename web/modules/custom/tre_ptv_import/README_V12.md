# TRE PTV Import - API VERSION 12

This module imports service data from the PTV API into Drupal.

The module has two main responsibilities:

1. Fetch data from the PTV API and store it in intermediate storage.
2. Expose that stored data to Drupal Migrate source plugins so Drupal nodes can be created or updated.

## High-level flow

The import flow is:

```text
PTV API
  -> fetch services
  -> fetch connections for those services
  -> fetch the service channels referenced by those connections
  -> fetch TPR accessibility data for referenced Service Location addresses
  -> write intermediate storage
  -> run Drupal migrations
  -> create/update Drupal nodes
```

In more detail:

1. The module fetches all relevant PTV Services for the configured organization.
2. The module collects the `contentId` values from those Services.
3. The module fetches PTV Connections for those Service content IDs.
4. Each Connection tells which Service is connected to which Service Channel.
5. The module collects the unique `channelContentId` values from the Connections.
6. The module fetches only those Service Channels that are actually referenced by the imported Services.
7. For Service Location addresses that contain an `accessibilityRegisterServicePointId`, the module fetches localized accessibility sentence groups from the TPR accessibility API.
8. Services, Service Channels, Service-to-Channel connection information and Service Location accessibility information are written into intermediate storage.
9. Drupal Migrate source plugins read from intermediate storage and map the data into Drupal source rows.
10. Drupal migrations create or update Service, Service Channel and Service Location nodes.

## Directory structure

The `src/Service` directory is split into logical subdirectories to make the module easier to follow.

```text
src/Service/
  Api/
    PTV AND TPR (TPR only for accessibility data) API communication and generated API client integration.

  Storage/
    Intermediate storage and storage record decoding.

  Migration/
    Mapping PTV data into the existing Drupal migration source structure.
```

These classes are Drupal services and are registered in `tre_ptv_import.services.yml`.

## `Service/Api`

This area contains the API-facing logic.

### Responsibilities

* Building generated PTV API client instances.
* Reading API host, API key and related settings from `settings.php`.
* Fetching Services from the PTV API.
* Fetching Connections from the PTV API.
* Fetching Service Channels from the PTV API.
* Fetching accessibility sentence groups from the TPR accessibility API for Service Location addresses.

### Important classes

| Class | Purpose |
|---|---|
| `PtvApiClientFactory` | Creates configured instances of the generated PTV API client classes. |
| `PtvApiFetcher` | Coordinates PTV API requests for Services, Connections and Service Channels. It also triggers TPR accessibility fetching for Service Location addresses. |
| `TprAccessibilityFetcher` | Fetches accessibility sentence groups from the TPR accessibility API and maps the localized Finnish and English values into the storage structure used by the module. |
| `PtvApiSettings` | Reads and validates PTV API and TPR accessibility API configuration from Drupal settings. |
| `PtvChannelResponseDeserializer` | Deserializes raw Service Channel API JSON responses into the correct generated DTO class. This is needed because the generated API client does not handle the polymorphic Service Channel response reliably. See [Service Channel type handling](#service-channel-type-handling). |

## `Service/Storage`

This area contains the intermediate storage logic.

### Typical responsibilities

* Writing fetched PTV data into intermediate database tables.
* Reading stored PTV records back for migrations.
* Encoding generated DTOs into storage-safe JSON.
* Storing optional TPR accessibility information alongside Service Location channel payloads.
* Decoding stored JSON records back into generated DTOs.
* Keeping the API-fetching phase separate from the migration phase.

### Important classes

| Class | Purpose |
|---|---|
| `PtvIntermediateStorage` | Prepares fetched PTV DTO data for JSON storage, writes it into intermediate database tables and reads stored records back for migration source plugins. |
| `PtvStorageRecordDecoder` | Decodes intermediate storage JSON back into generated PTV DTOs and connection data so migration source mappers can work with typed API models instead of raw arrays. |

Intermediate storage stores JSON instead of PHP DTO objects. This means fetched generated DTOs are converted into storage-safe JSON before being written to the database.

Later, when a migration source plugin reads the stored record, `PtvStorageRecordDecoder` converts that JSON back into the correct generated DTO and returns the related connection data. This keeps API fetching and migration execution separate while still allowing the migration mappers to work with generated PTV model objects.

In practice, this allows the migration mappers to use the generated DTO getter methods instead of reading values from raw arrays.

TPR accessibility information is already mapped into ordinary PHP arrays when it is fetched. It is therefore stored directly alongside the sanitized Service Location payload and does not need to be converted back into a generated DTO.

## `Service/Migration`

This area contains the logic that maps stored PTV data into the existing Drupal migration source contract.

### Typical responsibilities

* Mapping decoded PTV Service DTOs into Service migration source rows.
* Mapping decoded PTV Service Channel DTOs into Service Channel migration source rows.
* Mapping decoded PTV Service Location DTOs into Service Location migration source rows.
* Mapping PTV service hours into Office Hours source values.
* Resolving organization names from organization content IDs.
* Validating import package consistency.
* Supporting targeted single-item migration updates.

The migration mappers usually receive generated DTO objects that have been decoded from intermediate storage JSON. This allows the mappers to use typed DTO getter methods while still keeping API fetching separate from migration execution.

### Important classes

| Class | Purpose |
|---|---|
| `PtvServiceSourceMapper` | Maps a decoded PTV Service DTO, its Connections and channel type data into one Service migration source row. |
| `PtvChannelSourceMapper` | Maps decoded non-location Service Channel DTOs into Service Channel migration source rows. |
| `PtvServiceLocationSourceMapper` | Maps decoded `ServiceLocation` channel DTOs into Service Location / place of business migration source rows, including addresses, coordinates, map point source values and opening hours. |
| `PtvServiceHoursMapper` | Maps PTV service hour DTO structures into the Office Hours source arrays expected by the migrations. |
| `PtvOrganizationNameResolver` | Resolves PTV organization content IDs into localized organization names used by migration source rows. |
| `PtvImportValidator` | Validates that fetched Services, Connections and Service Channels form a consistent import package before migrations are queued. |
| `SingleItemUpdater` | Finds the PTV migration source ID for an existing Drupal node and creates a targeted PTV update queue item. |

## Generated PTV API client

The module uses a generated PHP API client for the PTV API.

The generated client is used for normal API operations such as:

```text
Service search
Service fetch
Connection search
Organization fetch
Generated DTO models
```

This keeps API request construction and model definitions aligned with the PTV OpenAPI schema.

The generated client returns DTO objects such as Service DTOs, Connection DTOs and Service Channel DTOs. These DTOs are used during the API-fetching phase and again during the migration-mapping phase.

## DTO serialization and deserialization

Intermediate storage stores JSON, not PHP objects. For that reason, generated DTOs need to be converted when they cross the storage boundary.

The flow is:

```text
Generated DTO from API
  -> sanitized/serialized into JSON-compatible data
  -> stored in intermediate storage
  -> read later by migration source plugins
  -> decoded back into generated DTOs
  -> mapped into Drupal migration source rows
```

This conversion is intentional.

It allows the module to:

* Fetch API data once and reuse it for multiple migrations.
* Inspect stored API data directly when debugging.
* Keep migrations deterministic during a single import run.
* Avoid live API calls from migration source plugins.
* Preserve the existing Drupal migration source contract.
* Use generated DTO getter methods in mappers instead of reading loosely structured raw arrays.

## Intermediate storage format

The module stores Services and Service Channels separately.

A Service storage record contains the Service payload and the Connections that belong to that Service.

Example:

```json
{
  "payload": {
    "contentId": "S1",
    "languageVersions": {
      "fi": {
        "name": "Example service"
      }
    }
  },
  "connections": [
    {
      "serviceContentId": "S1",
      "channelContentId": "C1"
    },
    {
      "serviceContentId": "S1",
      "channelContentId": "C2"
    }
  ]
}
```

A Service Channel storage record contains the Service Channel payload.

Example:

```json
{
  "payload": {
    "contentId": "C1",
    "serviceChannelType": "WebPage",
    "languageVersions": {
      "fi": {
        "name": "Example web page channel"
      }
    }
  }
}
```

A Service Location channel record can also contain accessibility information fetched from the TPR accessibility API. The data is keyed by the address-level `accessibilityRegisterServicePointId` and then by language.

Example:

```json
{
  "payload": {
    "contentId": "C2",
    "serviceChannelType": "ServiceLocation",
    "location": {
      "streetAddresses": [
        {
          "accessibilityRegisterServicePointId": "SERVICE_POINT_1"
        }
      ]
    }
  },
  "accessibility": {
    "SERVICE_POINT_1": {
      "fi": [
        {
          "title": "Pääsisäänkäynti",
          "sentences": [
            "Esteettömyyttä kuvaava lause."
          ]
        }
      ],
      "en": [
        {
          "title": "Main entrance",
          "sentences": [
            "An accessibility sentence."
          ]
        }
      ]
    }
  }
}
```

The accessibility data is stored outside the generated PTV payload. This preserves the generated DTO serialization and deserialization flow while still keeping the TPR data in the same intermediate storage record as the related Service Location.

The Service-to-Channel relationship is stored through the Service record’s `connections` list. The Service Channel type is stored on the Service Channel payload and also in the channel storage metadata.

## Service and Service Channel relationship

PTV Services and Service Channels have a many-to-many relationship.

A Service can be connected to multiple Service Channels, and the same Service Channel can be connected to multiple Services.

This relationship is represented by PTV Connection objects.

Conceptually:

```text
Service S1
  -> Connection: S1 to C1
  -> Connection: S1 to C2

Channel C1
  -> serviceChannelType: WebPage

Channel C2
  -> serviceChannelType: TelephoneService
```

When the Service migration source row is built, the mapper reads the Service’s Connections and groups channel references by Service Channel type.

The mapping is:

```text
ServiceLocation  -> service_locations
EService         -> eservice_channels
TelephoneService -> phone_service_channels
WebPage          -> web_page_service_channels
PrintableForm    -> form_service_channels
```

For example:

```text
Connection:
  S1 -> C1

Channel type:
  C1 = WebPage

Migration source field:
  web_page_service_channels = [C1]
```

And:

```text
Connection:
  S1 -> C2

Channel type:
  C2 = TelephoneService

Migration source field:
  phone_service_channels = [C2]
```

The Service migration can then use those source IDs to create Drupal entity references through migration lookup.

## Service Channel type handling

Service Channels are polymorphic in the PTV API. Different channel types have different fields.

The relevant PTV channel types are:

```text
EService
TelephoneService
PrintableForm
ServiceLocation
WebPage
```

The generated PHP API client does not reliably deserialize the polymorphic Service Channel response into the correct concrete DTO model. This is related to the OpenAPI polymorphism definition, such as `oneOf` / `anyOf` / discriminator-style channel responses.

Because of that, the module uses custom channel deserialization logic.

Instead of trusting the generated client to deserialize the channel response automatically, the module:

1. Builds the generated channel request.
2. Sends the request and reads the raw JSON response.
3. Reads `serviceChannelType` from the response.
4. Maps the type to the correct generated DTO class.
5. Uses the generated `ObjectSerializer` to deserialize into that concrete DTO.

The custom mapping is:

```text
EService         -> EServiceChannelResponse
TelephoneService -> PhoneChannelResponse
PrintableForm    -> PrintableFormChannelResponse
ServiceLocation  -> ServiceLocationChannelResponse
WebPage          -> WebPageChannelResponse
```

This custom logic keeps the rest of the module working with generated DTO classes while avoiding incorrect or incomplete deserialization from the generated client.

## Single item refresh and queue flow

The module supports targeted single item updates from the Drupal UI.

For example, an editor can click a "refresh from PTV" action for an existing PTV-based Drupal node. The node is not updated directly during the form submit. Instead, the module creates queue items and lets queue workers handle the refresh in two phases.

The queue flow is:

```text
Editor clicks "refresh from PTV"
  -> resolve the node's PTV migration source ID
  -> create item in ptv_api_migrations queue
  -> PtvApiQueueWorker fetches fresh data from the PTV API
  -> refreshed data is written into intermediate storage
  -> PtvApiQueueWorker creates items in ptv_node_migrations queue
  -> PtvNodeQueueWorker runs targeted Drupal migrations
  -> Drupal node is updated from refreshed intermediate storage data
```

### `ptv_api_migrations`

The `ptv_api_migrations` queue is responsible for refreshing source data from the PTV API.

It receives lightweight `PtvUpdateQueueItem` objects containing the language and one or more PTV source IDs.

Depending on the item type, the API queue worker fetches:

* selected Services,
* selected Service Channels,
* selected Service Locations.

When a fetched Service Location contains address-level `accessibilityRegisterServicePointId` values, the API worker also fetches the related accessibility sentence groups from the TPR accessibility API before writing the refreshed channel record into intermediate storage.

When a Service is refreshed, the API worker also fetches its Connections and the connected Service Channels. This is needed because a Service node can reference Service Channels and Service Locations through PTV Connection data.

After the fresh API data has been fetched, the worker writes it into intermediate storage and creates node migration queue items.

### `ptv_node_migrations`

The `ptv_node_migrations` queue is responsible for updating Drupal content.

It does not fetch data from the PTV API. Instead, it reads the already refreshed data from intermediate storage through the migration source plugins.

The node queue worker runs the correct targeted migration based on the queue item:

| Queue item contains | Migration |
|---|---|
| Service IDs | `ptv_services` / `ptv_services_en` |
| Service Channel IDs | `ptv_service_channels` / `ptv_service_channels_en` |
| Service Location IDs | `ptv_service_locations` / `ptv_service_locations_en` |

This two-phase queue flow keeps API fetching separate from Drupal node updates.

The API queue refreshes the source data. The node queue applies that refreshed source data to Drupal nodes through migrations.

### Queue-related classes

| Class | Purpose |
|---|---|
| `SingleItemUpdater` | Finds the PTV migration source ID for an existing Drupal node and creates the first API refresh queue item. |
| `PtvUpdateQueueItem` |Lightweight queue item model containing the language and PTV source IDs that need refreshing or migration. |
| `PtvApiQueueWorker` | Handles `ptv_api_migrations`, fetches fresh data from PTV, writes it to intermediate storage and queues node migrations. |
| `PtvNodeQueueWorker` | Handles `ptv_node_migrations` and runs targeted Drupal migrations for refreshed source IDs. |

## Service Location map point logic

Service Locations can contain physical address and coordinate data from the PTV API.

The module maps this data into the existing Drupal migration source structure so location nodes can receive address and map point information.

The general flow is:

```text
PTV ServiceLocation DTO
  -> read location street addresses
  -> read EPSG:3067 coordinates when available
  -> build address source values
  -> derive geographical area from coordinates
  -> create EPSG point source values
  -> migration creates/updates Drupal location map data
```

The Service Location mapper reads physical street addresses from the decoded `ServiceLocation` DTO. When an address contains coordinates in the `EPSG:3067` coordinate system, the mapper uses those values as map point source data.

The mapper also uses the coordinate conversion service to resolve the coordinate into a region name. That region value is added to the migration source data as a geographical area.

Conceptually:

```text
PTV street address
  -> street name
  -> postal code
  -> additional address information
  -> EPSG:3067 easting/northing
  -> Drupal address source value
  -> Drupal map point source value
  -> geographical area source value
```

The mapper also creates an address hash from the normalized address data. This helps the migration identify stable address and map point values across repeated imports.

In practice, this means Service Location nodes can receive both human-readable address data and coordinate-based map point data from the PTV ServiceLocation payload.

### TPR accessibility information

Accessibility sentences are not included directly in the PTV Service Location payload. Instead, a physical street address can contain an `accessibilityRegisterServicePointId` that identifies the corresponding service point in the TPR accessibility register.

The accessibility request requires two identifiers:

```text
systemId
  -> configured client system ID from Drupal settings

servicePointId
  -> accessibilityRegisterServicePointId from the PTV street address
```

The request flow is:

```text
PTV ServiceLocation street address
  -> read accessibilityRegisterServicePointId
  -> call the TPR accessibility API with systemId and servicePointId
  -> map Finnish and English sentence groups
  -> store the mapped data alongside the Service Location payload
  -> decode the stored accessibility data in the migration source plugin
  -> select the current migration language
  -> encode the sentence groups into the existing accessibility JSON format
  -> write the value to the related Drupal map point
```

`TprAccessibilityFetcher` maps the TPR response into the existing structure expected by the site:

```json
[
  {
    "title": "Pääsisäänkäynti",
    "sentences": [
      "Ensimmäinen saavutettavuutta kuvaava lause.",
      "Toinen saavutettavuutta kuvaava lause."
    ]
  }
]
```

Only Finnish and English values are stored because those are the languages used by the PTV migrations in this module.

The accessibility information is associated with an individual physical street address through its service point ID. When `PtvServiceLocationSourceMapper` maps the address, it selects the matching service point and the current migration language and exposes the JSON string as `accessibility_information`.

For a new map point, the migration uses this source value when creating the map point and writes it into `field_access_info_sentences_json`.

For an existing map point, the mapper finds the map point using the stable address hash and updates `field_access_info_sentences_json` on the matching language translation. This direct update is needed because the existing migration reuses map points found by the address hash and does not otherwise update the generated map point values.

Accessibility information is added after the address hash has been calculated. Changes in accessibility sentences therefore do not change the address hash or create a new map point for an otherwise unchanged address.

If an address has no accessibility register service point ID, or no localized accessibility groups are available for the current language, `accessibility_information` remains empty.

## Migrations

The Drupal migrations read from intermediate storage.

The general migration order is:

```text
ptv_service_locations
ptv_service_channels
ptv_services
ptv_service_locations_en
ptv_service_channels_en
ptv_services_en
```

Service Locations and Service Channels need to exist before Services can reference them.

The Service migration source mapper uses Connection data to determine which Service Channel source IDs should be placed into each Service reference field.

## Running queues manually

During local debugging, queues can be run manually with Drush.

Run the API queue first:

```bash
ddev drush queue:run ptv_api_migrations
```

Then run the node migration queue:

```bash
ddev drush queue:run ptv_node_migrations
```

The expected flow is that `ptv_api_migrations` fetches fresh data and creates items in `ptv_node_migrations`. Then `ptv_node_migrations` applies the refreshed data to Drupal nodes by running targeted migrations.

Drupal cron can also process queue workers when cron is configured to run in the environment.