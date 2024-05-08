/**
 * @file
 * A JavaScript file containing the search panel's search functionality.
 *
 */

/**
 * Resets the search.
 *
 * @param {Event} event
 *   The event on the reset button.
 */
function resetFilter(event) {
  const searchPanel = event.target.closest('.js-search-panel-list');
  const searchInput = searchPanel.querySelector('.search-bar__input');
  searchInput.value = '';
  const searchButton = searchPanel.querySelector('.search-bar__button');

  const searchFilterOptions = searchPanel.querySelectorAll('.form-item__select option');
  // eslint-disable-next-line no-plusplus
  for (let i = 0, l = searchFilterOptions.length; i < l; i++) {
    // eslint-disable-next-line security/detect-object-injection
    searchFilterOptions[i].selected = searchFilterOptions[i].value == 'All';
  }

  searchButton.click();
}

Drupal.behaviors.searchPanelListSearch = {
  attach(context) {
    let searchPanelsWithLists;
    // Once doesn't work in Storybook currently.
    try {
      searchPanelsWithLists = once('search-panel-lists', '.js-search-panel-list', context); // eslint-disable-line
    } catch (e) {
      searchPanelsWithLists = document.querySelectorAll(
        '.js-search-panel-list',
      );
    }

    if (searchPanelsWithLists) {
      searchPanelsWithLists.forEach((searchPanel) => {
        const resetFilterContainer = searchPanel.querySelector('.search-panel__reset-wrapper');
        const searchInput = searchPanel.querySelector('.search-bar__input');
        const searchFilters = searchPanel.querySelectorAll('.form-item__select');
        let displayReset = false;

        // eslint-disable-next-line no-plusplus
        for (let i = 0, l = searchFilters.length; i < l; i++) {
          // eslint-disable-next-line security/detect-object-injection
          if (searchFilters[i].value != 'All') {
            displayReset = true;
          }
        }

        /* eslint eqeqeq: 0 */
        if (searchInput.value.length != 0) {
          displayReset = true;
        }

        if (displayReset) {
          resetFilterContainer.style.display = 'block';
        }

        const filterResetButton = searchPanel.querySelector('.search-panel__filter-reset');
        filterResetButton.addEventListener('click', resetFilter);
      });
    }
  },
};
