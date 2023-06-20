/**
 * @file
 * A JavaScript file containing the map functionality for the map tabs.
 *
 */

Drupal.behaviors.mapTabsContent = {
  attach(context, drupalSettings) {
    // The domain must match the iframe source URL, per https://github.com/oskariorg/rpc-client#usage
    const IFRAME_DOMAIN = drupalSettings.tampere.mmlMapIframeDomain;
    const CURRENT_LANGUAGE = drupalSettings.tampere.currentLanguage;
    const LOCATION_PIN_VECTOR_LAYER_ID_BASE = 'MAP_TABS_LOCATION_PIN_VECTOR_LAYER';
    const SEARCH_VECTOR_LAYER_ID_BASE = 'MAP_TABS_SEARCH_VECTOR_LAYER';
    const ACTIVE_SEARCH_MARKER_SHAPE =
      '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="#C83E36" stroke="#C83E36" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-circle"><circle cx="12" cy="12" r="10"></circle></svg>';
    const ACTIVE_LOCATION_PIN_SHAPE =
      '<svg width="32" height="32" viewBox="0 0 32.28 45" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd"><path d="M29.102 25.769 16.144 45 3.186 25.769a.418.418 0 0 0-.042-.06C-2.141 18.533-.606 8.43 6.57 3.145S23.85-.606 29.136 6.57a16.139 16.139 0 0 1 0 19.139l-.034.059z" fill="#C83E36"/><g stroke="#FFF" stroke-linecap="round" stroke-width="2"><path d="M16.14 6.446v17M22.64 18.446l-6.5 6-6.5-6"/></g></g></svg>';
    const LOCATION_PIN_SHAPE =
      '<svg width="32" height="32" viewBox="0 0 32.28 45" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd"><path d="M29.102 25.769 16.144 45 3.186 25.769a.418.418 0 0 0-.042-.06C-2.141 18.533-.606 8.43 6.57 3.145S23.85-.606 29.136 6.57a16.139 16.139 0 0 1 0 19.139l-.034.059z" fill="#293B6E"/><path d="M24.237 14.47c-.64.002-1.157.519-1.16 1.159a6.965 6.965 0 1 1-3.065-5.762 1.156 1.156 0 1 0 1.298-1.915c-4.245-2.856-10-1.728-12.857 2.517a9.265 9.265 0 0 0 2.517 12.856 9.265 9.265 0 0 0 14.433-7.692 1.15 1.15 0 0 0-1.14-1.16h-.019" fill="#FFF"/><path d="M23.313 10.656h-.023a1.16 1.16 0 0 0 0 2.32h.023a1.16 1.16 0 0 0 0-2.32" fill="#FFF"/></g></svg>';
    const LOCATION_PIN_OFFSET_X = 18;
    const LOCATION_PIN_OFFSET_Y = 4;
    const LOCATION_PIN_SIZE = 5;
    const MAX_ZOOM_LEVEL = 10;
    const PREFERRED_SEARCH_RESULT_REGION = 'Tampere';
    const getVectorLayerId = (baseName) => (id) => `${baseName}_${id}`;
    const mapElements = once('embedded-content-and-map-tabs-map-elements', '[id^="map-"]', context);
    // Contains locations for all the 'Embedded content and map tabs' maps on the current page.
    // Key'd by the ID of the containing paragraph and then by node IDs.
    // One node can be connected to multiple locations.
    const locations = drupalSettings.tampere.embeddedContentAndMapTabs.locations;

    /**
     * Toggles given feature location pin styles.
     *
     * @param {Object} channel
     *   The channel connected with the map.
     * @param {Object} feature
     *   The feature to update.
     * @param {string} activeFeatureId
     *   The active feature's ID.
     */
    function toggleLocationPin(channel, feature, activeFeatureId) {
      const featureId = feature.id;
      const { layerId } = feature;
      const isFeatureActive = feature.geojson.features[0].properties.active;

      const activePinStyle = {
        image: {
          shape: ACTIVE_LOCATION_PIN_SHAPE,
        },
      };

      const defaultPinStyle = {
        image: {
          shape: LOCATION_PIN_SHAPE,
        },
      };

      const featureStyle = isFeatureActive ? defaultPinStyle : activePinStyle;

      // Make sure previously active feature is switched back to default state.
      if (activeFeatureId !== featureId) {
        channel.postRequest('MapModulePlugin.AddFeaturesToMapRequest', [
          {
            id: [
              {
                value: activeFeatureId,
                properties: {
                  active: false,
                },
              },
            ],
          },
          {
            layerId,
            featureStyle: defaultPinStyle,
          },
        ]);

        activeFeatureId = featureId;
      }

      // Updating existing feature on the map.
      // Matching feature by 'id' and toggling the active state.
      // https://oskari.org/api/requests#2.6.0/mapping/mapmodule/request/addfeaturestomaprequest.md
      channel.postRequest('MapModulePlugin.AddFeaturesToMapRequest', [
        {
          id: [
            {
              value: featureId,
              properties: {
                active: !isFeatureActive,
              },
            },
          ],
        },
        {
          layerId,
          featureStyle,
        },
      ]);
    }

    /**
     * Handles feature events by toggling location pins and information cards.
     *
     * @param {Event} event
     *   The Feature Event to react to.
     * @param {Object} channel
     *   The channel connected with the event.
     * @param {HTMLElement} mapElement
     *   The associated map element.
     */
    function handleFeatureEvent(event, channel, mapElement) {
      const isClickEvent = event.operation === 'click';

      if (!isClickEvent) {
        return false;
      }

      const feature = event.features[0];

      const clickedFeatureLayer = feature.layerId;
      const isLocationPinLayer = clickedFeatureLayer.includes(
        LOCATION_PIN_VECTOR_LAYER_ID_BASE
      );

      // Make sure the clicked feature is one of the location pins.
      if (!isLocationPinLayer) {
        return false;
      }

      const isFeatureActive = feature.geojson.features[0].properties.active;
      const informationCard = mapElement.closest('.js-search-panel-map').querySelector('.search-panel__popup-item');
      const loadingText = mapElement.closest('.js-search-panel-map').querySelector('.search-panel__loading-text');
      const activeFeatureId = informationCard.getAttribute('data-featureId');
      toggleLocationPin(channel, feature, activeFeatureId);

      const locationNid = feature.geojson.features[0].properties.nid;

      loadingText.hidden = false;

      informationCard.innerHTML = '';

      if (!isFeatureActive) {
        let baseUrl = '/map-data-from-node/';
        if (CURRENT_LANGUAGE == 'en') {
          baseUrl = '/en/map-data-from-node/';
        }
        jQuery(informationCard).load(`${baseUrl}${locationNid}`, function() {
          loadingText.hidden = true;
        });
      } else {
        loadingText.hidden = true;
      }

      informationCard.setAttribute('data-nid', locationNid);
      informationCard.setAttribute('data-featureId', feature.id);
      informationCard.hidden = isFeatureActive;

      return false;
    }

    /**
     * Handles search events.
     *
     * @param {Event} event
     *   The Search Event to react to.
     * @param {Object} channel
     *   The channel connected with the event.
     * @param {string} id
     *   An identifier for the map.
     */
    function handleSearch(event, channel, id) {
      if (event.success && event.result.locations.length > 0) {
        const vectorLayerId = getVectorLayerId(SEARCH_VECTOR_LAYER_ID_BASE)(id);
        let result;

        const keys = Object.keys(event.result.locations);
        for (const item of keys) {
          const { region } = event.result.locations[item];

          if (region === PREFERRED_SEARCH_RESULT_REGION) {
            result = event.result.locations[item];

            break;
          }
        }

        // If preferred search region result wasn't found, use first result.
        if (!result) {
          result = event.result.locations[0];
        }

        if (result && 'lon' in result && 'lat' in result) {
          channel.postRequest('VectorLayerRequest', [
            {
              layerId: vectorLayerId,
              opacity: 100,
            },
          ]);

          const geojson = {
            type: 'FeatureCollection',
            crs: {
              type: 'name',
              properties: {
                name: 'EPSG:3067',
              },
            },
            features: [
              {
                type: 'Feature',
                geometry: {
                  type: 'Point',
                  coordinates: [result.lon, result.lat],
                },
              },
            ],
          };

          const featureStyle = {
            image: {
              shape: ACTIVE_SEARCH_MARKER_SHAPE,
              size: 1,
            },
          };

          const addResultFeaturesParams = [
            geojson,
            {
              layerId: vectorLayerId,
              centerTo: true,
              clearPrevious: true,
              featureStyle,
              maxZoomLevel: MAX_ZOOM_LEVEL,
            },
          ];

          channel.postRequest('MapModulePlugin.AddFeaturesToMapRequest', addResultFeaturesParams);
        }
      }
    }

    /**
     * Clears search results from map.
     *
     * @param {Object} channel
     *   The channel connected with the map.
     * @param {string} id
     *   An identifier for the map.
     */
    function clearSearchResults(channel, id) {
      const vectorLayerId = getVectorLayerId(SEARCH_VECTOR_LAYER_ID_BASE)(id);

      channel.postRequest('MapModulePlugin.RemoveFeaturesFromMapRequest', [
        null,
        null,
        vectorLayerId,
      ]);
    }

    /**
     * Handles interaction with the close button on cards.
     *
     * @param {Event} event
     *   The event on the button.
     * @param {Object} channel
     *   The channel connected with the map.
     */
    function handleCardButtonInteraction(event, channel) {
      const popupItem = event.target.closest('.search-panel__popup-item');
      const mapElement = event.target.closest('.js-search-panel-map').querySelector('[id^="map"]');

      if (popupItem && mapElement) {
        popupItem.hidden = !popupItem.hidden;

        const locationNid = popupItem.dataset.nid;
        const paragraphId = mapElement.dataset.paragraph;
        const layerId = getVectorLayerId(LOCATION_PIN_VECTOR_LAYER_ID_BASE)(paragraphId);

        const featureStyle = {
          image: {
            shape: LOCATION_PIN_SHAPE,
          },
        };

        // The 'active' property on the features matching the nid have to be updated
        // when the information card has been closed.
        channel.postRequest('MapModulePlugin.AddFeaturesToMapRequest', [
          {
            nid: [
              {
                value: locationNid,
                properties: {
                  active: false,
                },
              },
            ],
          },
          {
            layerId,
            featureStyle,
          },
        ]);

      }
    }

    if (mapElements.length > 0 && IFRAME_DOMAIN) {
      mapElements.forEach((mapElement) => {
        const containerParagraphId = mapElement.dataset.paragraph;
        const channel = OskariRPC.connect(mapElement, IFRAME_DOMAIN);

        // Called when the connection to the map has been established successfully.
        channel.onReady((info) => {
          const locationsForCurrentMap = locations[containerParagraphId];
          const locationsExistForMap =
            Object.keys(locationsForCurrentMap).length !== 0;

          channel.handleEvent('FeatureEvent', function(channel, map, event) {
            handleFeatureEvent(event, channel, map);
          }.bind(channel, channel, mapElement));

          channel.handleEvent('SearchResultEvent', function(channel, id, event) {
            handleSearch(event, channel, id);
          }.bind(channel, channel, containerParagraphId));

          const containerSearchPanel = mapElement.closest('.js-search-panel-map');
          if (containerSearchPanel) {
            const searchButton = containerSearchPanel.querySelector('.search-bar__button');
            const input = containerSearchPanel.querySelector('input');
            const informationCardCloseButtons = once('embedded-content-and-map-tabs-information-card-close-toggles', '.search-panel__popup-item button', containerSearchPanel);

            searchButton.addEventListener('click', (event) => {
              const searchValue = event.currentTarget.previousElementSibling.value.toLowerCase();
              if (searchValue.length > 0) {
                channel.postRequest('SearchRequest', [searchValue]);
              } else {
                clearSearchResults(channel, containerParagraphId);
              }
            });

            input.addEventListener('keydown', (event) => {
              const searchValue = event.currentTarget.value.toLowerCase();
              if (event.key === 'Enter') {
                if (searchValue.length > 0) {
                  channel.postRequest('SearchRequest', [searchValue]);
                } else {
                  clearSearchResults(channel, containerParagraphId);
                }
              }
            });

            informationCardCloseButtons.forEach((button) => {
              button.addEventListener('click', function(channel, event) {
                handleCardButtonInteraction(event, channel);
              }.bind(button, channel));
            });

            containerSearchPanel.addEventListener('click', (event) => {
              if (event.target.closest('.popup-card__button') || event.target.classList.contains('popup-card__button')) {
                handleCardButtonInteraction(event, channel);
              }
          });

          }

          if (locationsExistForMap) {
            const keys = Object.keys(locationsForCurrentMap);
            const vectorLayerId = getVectorLayerId(LOCATION_PIN_VECTOR_LAYER_ID_BASE)(containerParagraphId);
            const features = [];

            for (const item of keys) {
              const locationLongitude = locationsForCurrentMap[item].longitude;
              const locationLatitude = locationsForCurrentMap[item].latitude;
              const locationNodeId = locationsForCurrentMap[item].nid.toString();

              // Adding each location for the current map to the available features.
              features.push({
                type: 'Feature',
                geometry: {
                  type: 'Point',
                  coordinates: [locationLongitude, locationLatitude],
                },
                properties: {
                  active: false,
                  nid: locationNodeId,
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
                layerId: vectorLayerId,
                centerTo: true,
                cursor: 'pointer',
                featureStyle,
                maxZoomLevel: MAX_ZOOM_LEVEL,
              },
            ];

            // Recommendable practice is to prepare a layer with VectorLayerRequest before adding features.
            // https://oskari.org/api/requests#2.6.0/mapping/mapmodule/request/vectorlayerrequest.md
            channel.postRequest('VectorLayerRequest', [
              {
                layerId: vectorLayerId,
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
