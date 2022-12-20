/**
 * @file
 * A JavaScript file containing the map functionality for liftups.
 *
 */

Drupal.behaviors.liftupMaps = {
  attach(context, drupalSettings) {
    // The domain must match the iframe source URL, per https://github.com/oskariorg/rpc-client#usage
    const IFRAME_DOMAIN = drupalSettings.tampere.mmlMapIframeDomain;
    const LOCATION_PIN_VECTOR_LAYER_ID = 'LOCATION_PIN_VECTOR_LAYER';
    const LOCATION_PIN_SHAPE =
      '<svg width="32" height="32" viewBox="0 0 32.28 45" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd"><path d="M29.102 25.769 16.144 45 3.186 25.769a.418.418 0 0 0-.042-.06C-2.141 18.533-.606 8.43 6.57 3.145S23.85-.606 29.136 6.57a16.139 16.139 0 0 1 0 19.139l-.034.059z" fill="#293B6E"/><path d="M24.237 14.47c-.64.002-1.157.519-1.16 1.159a6.965 6.965 0 1 1-3.065-5.762 1.156 1.156 0 1 0 1.298-1.915c-4.245-2.856-10-1.728-12.857 2.517a9.265 9.265 0 0 0 2.517 12.856 9.265 9.265 0 0 0 14.433-7.692 1.15 1.15 0 0 0-1.14-1.16h-.019" fill="#FFF"/><path d="M23.313 10.656h-.023a1.16 1.16 0 0 0 0 2.32h.023a1.16 1.16 0 0 0 0-2.32" fill="#FFF"/></g></svg>';
    const LOCATION_PIN_OFFSET_X = 18;
    const LOCATION_PIN_OFFSET_Y = 4;
    const LOCATION_PIN_SIZE = 5;
    const mapElements = once('liftup-map-elements',
      document.querySelectorAll(
        '.liftup-map, .content-map',
        context
      )
    );
    // Contains locations for all the liftup maps on the current page.
    // Key'd by the node ID.
    const locations = drupalSettings.tampere.nodeLiftupLocations;

    if (mapElements.length > 0 && IFRAME_DOMAIN) {
      mapElements.forEach((mapElement) => {
        const nodeId = mapElement.dataset.nodeId;
        const channel = OskariRPC.connect(mapElement, IFRAME_DOMAIN);

        channel.onReady(function (info) {
          // Connection to the map has been established successfully.
          const locationsForCurrentMap = locations[nodeId];
          const locationsExistForMap =
            Object.keys(locationsForCurrentMap).length !== 0;
          const features = [];

          if (locationsExistForMap) {
            const keys = Object.keys(locationsForCurrentMap);

            for (const item of keys) {
              const locationLongitude = locationsForCurrentMap[item].longitude;
              const locationLatitude = locationsForCurrentMap[item].latitude;

              // Adding each location for the current map to the available features.
              features.push({
                type: 'Feature',
                geometry: {
                  type: 'Point',
                  coordinates: [locationLongitude, locationLatitude],
                },
              });
            }

            const geojson = {
              type: 'FeatureCollection',
              crs: {
                type: 'name',
                properties: {
                  name: 'EPSG:3067',
                },
              },
              features,
            };

            const featureStyle = {
              image: {
                shape: LOCATION_PIN_SHAPE,
                size: LOCATION_PIN_SIZE,
                offsetX: LOCATION_PIN_OFFSET_X,
                offsetY: LOCATION_PIN_OFFSET_Y,
              },
            };

            const addFeaturesParams = [
              geojson,
              {
                layerId: LOCATION_PIN_VECTOR_LAYER_ID,
                centerTo: true,
                featureStyle,
                maxZoomLevel: 11,
              },
            ];

            // Recommendable practice is to prepare a layer with VectorLayerRequest before adding features.
            // https://oskari.org/api/requests#2.6.0/mapping/mapmodule/request/vectorlayerrequest.md
            channel.postRequest('VectorLayerRequest', [
              {
                layerId: LOCATION_PIN_VECTOR_LAYER_ID,
                opacity: 100,
              },
            ]);

            // Adding features to the map.
            // https://oskari.org/api/requests#2.6.0/mapping/mapmodule/request/addfeaturestomaprequest.md
            channel.postRequest(
              'MapModulePlugin.AddFeaturesToMapRequest',
              addFeaturesParams
            );
          }
        });
      });
    }
  },
};
