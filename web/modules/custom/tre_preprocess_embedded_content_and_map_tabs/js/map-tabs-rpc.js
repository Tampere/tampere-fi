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
    const SEARCH_ZOOM_LEVELS = drupalSettings.tampere.embeddedContentAndMapTabs.zoomLevels;
    const PREFERRED_SEARCH_RESULT_REGION = 'Tampere';
    const getVectorLayerId = (baseName) => (id) => `${baseName}_${id}`;
    const mapElements = once('embedded-content-and-map-tabs-map-elements', '[id^="map-"]', context);
    // Contains locations for all the 'Embedded content and map tabs' maps on the current page.
    // Key'd by the ID of the containing paragraph and then by node IDs.
    // One node can be connected to multiple locations.
    const locations = drupalSettings.tampere.embeddedContentAndMapTabs.locations;

    const showHours = drupalSettings.tampere.showHours;

    // Keeping track of items opened on the list side.
    let lastOpened = {}

    /**
     * Update location pins based on tab list result.
     *
     * @param {Object} channel
     *   The channel connected with the map.
     * @param {string} containerParagraphId
     *   The map container paragraph ID.
     * @param {Object} locations
     *   The location data.
     */
    function updateLocationPins(channel, containerParagraphId, filteredLocationsForCurrentMap) {
      
      const keys = Object.keys(filteredLocationsForCurrentMap);
      const vectorLayerId = getVectorLayerId(LOCATION_PIN_VECTOR_LAYER_ID_BASE)(containerParagraphId);
      const features = [];

      // Clear current location pins before adding the locations from tab list result.
      channel.postRequest('MapModulePlugin.RemoveFeaturesFromMapRequest', []);

      for (const item of keys) {
        const locationLongitude = filteredLocationsForCurrentMap[item].longitude;
        const locationLatitude = filteredLocationsForCurrentMap[item].latitude;
        const locationNodeId = filteredLocationsForCurrentMap[item].nid.toString();

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
    
    /**
     * Toggles given feature location pin styles.
     *
     * @param {Object} channel
     *   The channel connected with the map.
     * @param {Object} feature
     *   The feature to update.
     * @param {string} activeFeatureId
     *   The active feature's ID.
     * @param {boolean} turnOffPreviousPin
     *   If previously active pins should be deactivated.
     */
    function toggleLocationPin(channel, feature, activeFeatureId, turnOffPreviousPin = true) {
      const featureId = feature.id;
      const { layerId } = feature;

      const properties = feature?.geojson
        ? feature.geojson.features[0].properties
        : feature.properties;
      const isFeatureActive = properties.active;

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

      if (turnOffPreviousPin && activeFeatureId !== featureId) {
        // Get all active location pins and set them back to default state.
        channel.getFeatures([true], function (data) {
          const activeFeatures = data[layerId].features.filter((feature) => feature.properties.active);
          activeFeatures.forEach((activeFeature) => {
            channel.postRequest('MapModulePlugin.AddFeaturesToMapRequest', [
              {
                id: [
                  {
                    value: activeFeature.properties.id,
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
          });
        });
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

      toggleInformationCard(feature, channel, mapElement);
    }

    /**
     * Handle toggling the location information card for map.
     *
     * @param {Object} feature
     * @param {Object} channel
     * @param {HTMLElement} mapElement
     */
    function toggleInformationCard(feature, channel, mapElement) {
      const properties = feature?.geojson
        ? feature.geojson.features[0].properties
        : feature.properties;

      const isFeatureActive = properties.active;
      const informationCard = mapElement
        .closest('.js-search-panel-map')
        .querySelector('.search-panel__popup-item');
      const loadingText = mapElement
        .closest('.js-search-panel-map')
        .querySelector('.search-panel__loading-text');
      const activeFeatureId = informationCard.getAttribute('data-featureId');
      toggleLocationPin(channel, feature, activeFeatureId);

      const locationNid = properties.nid;

      loadingText.hidden = false;

      informationCard.innerHTML = '';

      if (!isFeatureActive) {
        let baseUrl = '/map-data-from-node/';
        if (CURRENT_LANGUAGE == 'en') {
          baseUrl = '/en/map-data-from-node/';
        }

        jQuery(informationCard).load(`${baseUrl}${locationNid}/${showHours}`, function() {
          loadingText.hidden = true;
          // Making sure the behavior of collapsing/expanding accordion
          // item works properly for the dynamically-added item.
          Drupal.attachBehaviors();
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
     * Handles search events when getting autocomplete suggestions.
     *
     * @param {Event} event
     *   The Search Event to react to.
     * @param {Object} channel
     *   The channel connected with the event.
     * @param {string} id
     *   An identifier for the map.
     */
    function handleAutocomplete(event, channel, id) {
      const mapElement = mapElements.find((mapElement) => mapElement.id === `map-${id}`);
      const mapContainer = mapElement.closest('.tabbed-container__content');
      const searchField = mapContainer.querySelector('.form-item__textfield.search-bar__input');
      const suggestionsWrapper = mapContainer.querySelector('.map-search-suggestions');
      // Reset the list when the list of suggestions changes.
      suggestionsWrapper.innerHTML = '';

      // Filter list of suggestions to a manageable level and to preferred region.
      const suggestions = event.result.locations
        .filter((suggestion) => suggestion.region === PREFERRED_SEARCH_RESULT_REGION)
        .slice(0, 10)
        .map((location) => location.name)

      // Create suggestions list.
      suggestions.forEach((location, i) => {
        const suggestionElement = document.createElement('li');
        suggestionElement.classList.add('ui-menu-item');
        suggestionElement.textContent = location;
        suggestionElement.tabIndex = -1;
        suggestionElement.dataset.index = i;

        // Handle showing which list element is active.
        suggestionElement.addEventListener('mouseover', function (e) {
          e.target.focus();
          currentIndex = e.target.dataset.index;
        });

        suggestionElement.addEventListener('focus', function (e) {
          e.target.classList.add('ui-state-active');
        });

        suggestionElement.addEventListener('blur', function (e) {
          e.target.classList.remove('ui-state-active');
        });

        suggestionsWrapper.append(suggestionElement);
      });

      // Handle moving on the list with arrow keys.
      const listItems = suggestionsWrapper.querySelectorAll('li');
      currentIndex = 0;

      function focusListItem(index) {
        if (index < 0 || index >= listItems.length) return;
        // Focus on the new item
        listItems[index].focus();
      }

      searchField.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          listItems[0].focus();
        }
      });

      suggestionsWrapper.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          currentIndex = (currentIndex + 1) % listItems.length;
          focusListItem(currentIndex);
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          currentIndex = (currentIndex - 1 + listItems.length) % listItems.length;
          focusListItem(currentIndex);
        } else if (e.key === 'Enter') {
          listItems[currentIndex].click();
        }
      });

      // Close autocomplete if clicked outside the list.
      document.addEventListener('click', function(e) {
        if (!suggestionsWrapper.contains(e.target)) {
          suggestionsWrapper.innerHTML = '';
        }
      });

      // Remove the suggestions list and performa a search when a suggestion
      // is clicked.
      suggestionsWrapper.addEventListener('click', function (e) {
        searchField.value = e.target.textContent;
        suggestionsWrapper.innerHTML = '';
        channel.postRequest('SearchRequest', [e.target.textContent]);
      });
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

        const zoomLevelForCurrentMap = SEARCH_ZOOM_LEVELS[id] || 10;
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
              maxZoomLevel: zoomLevelForCurrentMap,
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
     * Extract the related locations to the paragraph from all the locations.
     *
     * @param {String} containerParagraphId
     *   The target containerParagraphId to extract locations for.
     * @param {Object} allLocations
     *   All the locations history for all the views.
     * @param {Object} paragraphToViewHashTable
     *   The hash table to map a paragraph id to its view dom id.
     */
    function getParagraphFilteredLocations(containerParagraphId, allLocations, paragraphToViewHashTable) {
      // Because the view is in AJAX mode, when interacting with the view, i.e. changing the filters,
      // only the view gets re-rendered, not the parent paragraph, so here we use the view Dom Id to 
      // extract the related locations.
      const domId = paragraphToViewHashTable[containerParagraphId];
      const filteredLocationsArray = allLocations[domId];

      // The history of all locations is kept based on a timestamp, so we can get the latest one.
      const latestLocations = Math.max(...Object.keys(filteredLocationsArray).map(Number));
      return filteredLocationsArray[latestLocations];
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
        const mapTabPanel = mapElement.closest('.tabbed-container__tab-panel');
        const mapTabIsHidden = mapTabPanel.getAttribute('hidden');

        const mapContainer = mapElement.closest('.tabbed-container__content');
        const mapTabButton = mapContainer.querySelector('.tabbed-container__tab-list-item');
        
        const mapId = mapElement.id;
        lastOpened[mapId] = [];

        // Only if the Map Tab is already hidden. i.e. it is not the default tab.
        // In this case, we manually display the map,
        // until the map connection is ready, then we will hide it again.
        if (mapTabIsHidden !== null) {
          mapTabPanel.removeAttribute('hidden');
          // We can't hide the map, so place it out of the window.
          mapTabPanel.style.position = 'absolute';
          mapTabPanel.style.left = '-9999px';
          mapTabButton.style.display = 'none';
        }

        const containerParagraphId = mapElement.dataset.paragraph;
        const channel = OskariRPC.connect(mapElement, IFRAME_DOMAIN);

        // Called when the connection to the map has been established successfully.
        channel.onReady((info) => {
          // Hide the map tab, only if the Map Tab is supposed to be hidden.
          if (mapTabIsHidden !== null) {
            mapTabPanel.setAttribute('hidden', 'true');
            mapTabPanel.style.position = '';
            mapTabPanel.style.left = '';
            mapTabButton.style.display = '';
          }

          const locationsForCurrentMap = locations[containerParagraphId];
          const locationsExistForMap =
            Object.keys(locationsForCurrentMap).length !== 0;

          channel.handleEvent('FeatureEvent', function(channel, map, event) {
            handleFeatureEvent(event, channel, map);
          }.bind(channel, channel, mapElement));

          channel.handleEvent('SearchResultEvent', function(channel, id, event) {
            if (event.options.autocomplete) {
              handleAutocomplete(event, channel, id);
            } else {
              handleSearch(event, channel, id);
            }
          }.bind(channel, channel, containerParagraphId));


          const containerSearchPanel = mapElement.closest('.js-search-panel-map');
          if (containerSearchPanel) {
            const searchButton = containerSearchPanel.querySelector('.search-bar__button');
            const input = containerSearchPanel.querySelector('input');
            const informationCardCloseButtons = once('embedded-content-and-map-tabs-information-card-close-toggles', '.search-panel__popup-item button', containerSearchPanel);

            // Clear the lastOpened list when page changes.
            jQuery(`#paragraph-${containerParagraphId}`).on('click', '.pager', function() {
              lastOpened[mapId] = [];
            });

            // Keeping track of opening and closing list items, used for opening the correct infobox on the map side.
            jQuery(`#paragraph-${containerParagraphId}`).on('click', '.search-panel__results-section .accordion__heading', function() {
              if (this.classList.contains('is-active'))  {
                lastOpened[mapId].push(this.getAttribute('data-nid'));
              } else {
                lastOpened[mapId] = lastOpened[mapId].filter((nid) => nid !== this.getAttribute('data-nid'));
              }
            });

            // When tab is changed from list to map and a location was open on the list, open the same location
            // on the map.
            const mapTabButton = mapContainer.querySelector('.tabbed-container__tab-list-item');
            
            // Since the map tab can be selected by keyboard arrows as well,
            // we consider both clicking and pressing keys.
            ['click', 'keyup'].forEach(event =>
              mapTabButton.addEventListener(event, function () {
                var filteredLocations = []
                const tabbedContainer = mapElement.closest('.tabbed-container__content');
                const searchPanel = tabbedContainer.querySelector('.js-search-panel-list');
                const totalCount = searchPanel.querySelector('.js-total-count');

                // If JS-total-count element exists, it means the result has at least one row.
                // So in that case we should upadte the locations.
                // Otherwise, there must be no location on the map.
                if (totalCount) {
                  const allFilteredLocations = drupalSettings.tampere.embeddedContentAndMapTabs.filteredLocations;
                  const paragraphToViewHashTable = drupalSettings.tampere.embeddedContentAndMapTabs.paragraphToViewHashTable;
                  filteredLocations = getParagraphFilteredLocations(containerParagraphId, allFilteredLocations, paragraphToViewHashTable)
                }
  
                updateLocationPins(channel, containerParagraphId, filteredLocations);

                if (lastOpened[mapId].length === 0) {
                  return;
                }

                // Get the last clicked element from the list.
                const lastOpenedItem = lastOpened[mapId][lastOpened[mapId].length - 1];
                const activeListElement = mapContainer.querySelector(`[data-nid="${lastOpenedItem}"]`);

                // Couldn't find the item, remove it from the list.
                if (!activeListElement || !activeListElement.getAttribute('data-nid')) {
                  lastOpened[mapId].pop();
                  return;
                }

                const activeNid = activeListElement.getAttribute('data-nid');

                channel.getFeatures([true], function (data) {
                  const vectorLayerId = getVectorLayerId(
                    LOCATION_PIN_VECTOR_LAYER_ID_BASE
                  )(containerParagraphId);
                  // Get the right feature based on the node id.
                  const targetFeature = data[vectorLayerId]?.features.find(
                    (feature) =>
                      feature.properties.nid === activeNid
                  );

                  const targetFeatures = data[vectorLayerId]?.features.filter(
                    (feature) =>
                      feature.properties.nid === activeNid
                  );

                  targetFeatures.forEach((targetFeature, i) => {
                    if (!targetFeature.layerId) {
                      targetFeature.layerId = vectorLayerId;
                    }
                    // If one item on the list has multiple locations on the map, we toggle multiple
                    // pins to active for all of them.
                    if (i === 0) {
                      toggleInformationCard(targetFeature, channel, mapElement);
                    } else {
                      toggleLocationPin(channel, targetFeature, null, false);
                    }
                  })
                });
              })
            );

            searchButton.addEventListener('click', (event) => {
              const searchValue = event.currentTarget.previousElementSibling.value.toLowerCase();
              if (searchValue.length > 0) {
                channel.postRequest('SearchRequest', [searchValue]);
              } else {
                clearSearchResults(channel, containerParagraphId);
              }
            });

            function autocompleteSearch (searchValue, channel) {
              // Append asterisk to the query, so we can get results for partial words.
              // Also add autocomplete params so we can differentiate from a normal search.
              channel.postRequest('SearchRequest', [`${searchValue}*`, { autocomplete: true }]);
            };

            let typingTimeout;
            input.addEventListener('input', (event) => {
              // Fetch suggestions for autocomplete as user types, after a small
              // delay to avoid request spam.
              clearTimeout(typingTimeout);
              const searchValue = event.currentTarget.value.toLowerCase();
              typingTimeout = setTimeout(() => autocompleteSearch(searchValue, channel), 200);

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
