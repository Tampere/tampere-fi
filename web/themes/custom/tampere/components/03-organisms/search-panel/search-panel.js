/**
 * @file
 * A JavaScript file containing the search panel's search functionality.
 *
 */

/**
 * Updates search panel description to display information on search results.
 *
 * @param {HTMLElement} searchPanel
 *   The search panel element to update.
 */
function updateSearchResultDescription(searchPanel) {
  const hiddenContentItemAmount = searchPanel.querySelectorAll(
    '.search-panel__results-section .accordion__item.js-hidden'
  ).length;

  const defaultTextElement = searchPanel.querySelector(
    '.search-panel__text--no-filters-applied'
  );

  const resetFilterContainer = searchPanel.querySelector(
    '.search-panel__reset-wrapper'
  );

  if (hiddenContentItemAmount > 0) {
    const allContentItemAmount = searchPanel.querySelectorAll(
      '.search-panel__results-section .accordion__item'
    ).length;

    const resultsTextElement = searchPanel.querySelector(
      '.search-panel__text--results'
    );

    const noResultsTextElement = searchPanel.querySelector(
      '.search-panel__text--no-results'
    );

    resetFilterContainer.style.display = 'block';

    // No results to display.
    if (hiddenContentItemAmount === allContentItemAmount) {
      noResultsTextElement.style.display = '';
      defaultTextElement.style.display = 'none';
      resultsTextElement.style.display = 'none';
    }
    // Some results to display.
    else {
      const currentResultCountDisplayElement = resetFilterContainer.querySelector(
        '.js-result-count'
      );

      const visibleHeadingsAmount = searchPanel.querySelectorAll(
        '.search-panel__results-section .accordion__item:not(.js-hidden)'
      ).length;

      noResultsTextElement.style.display = 'none';
      defaultTextElement.style.display = 'none';
      resultsTextElement.style.display = '';

      currentResultCountDisplayElement.textContent = visibleHeadingsAmount;
    }
  } else {
    // All results to display.
    defaultTextElement.style.display = '';
    resetFilterContainer.style.display = 'none';
  }
}

/**
 * Handles search panel events by searching the input value from headings.
 *
 * @param {Event} event
 *   The event on a search panel element.
 */
function searchFromList(event) {
  const searchPanel = event.target.closest('.js-search-panel-list');
  const input = searchPanel.querySelector('input');
  const searchValue = input.value.toLowerCase();
  const searchResultAccordionItems = searchPanel.querySelectorAll(
    '.search-panel__results-section .accordion__item'
  );

  if (searchResultAccordionItems) {
    searchResultAccordionItems.forEach((accordionItem) => {
      const accordionItemTextContent = accordionItem.textContent;
      const searchValueIndex = accordionItemTextContent
        .toLowerCase()
        .indexOf(searchValue);

      if (searchValueIndex !== -1) {
        accordionItem.style.display = ''; // eslint-disable-line
        accordionItem.classList.remove('js-hidden'); // eslint-disable-line
      } else {
        accordionItem.style.display = 'none'; // eslint-disable-line
        accordionItem.classList.add('js-hidden'); // eslint-disable-line
      }
    });

    updateSearchResultDescription(searchPanel);
  }
}

/**
 * Handles input events.
 *
 * @param {Event} event
 *   The event on an input element.
 */
function handleInputEvent(event) {
  if (event.key === 'Enter') {
    searchFromList(event);
  }
}

/**
 * Resets the search.
 *
 * @param {Event} event
 *   The event on the reset button.
 */
function resetSearch(event) {
  const searchPanel = event.target.closest('.js-search-panel-list');
  const input = searchPanel.querySelector('input');

  input.value = '';

  searchFromList(event);
}

Drupal.behaviors.searchPanelListSearch = {
  attach(context) {
    let searchPanelsWithLists;

    // Once doesn't work in Storybook currently.
    try {
      searchPanelsWithLists = once('search-panel-lists', '.js-search-panel-list', context); // eslint-disable-line
    } catch (e) {
      searchPanelsWithLists = document.querySelectorAll(
        '.js-search-panel-list'
      );
    }

    if (searchPanelsWithLists) {
      searchPanelsWithLists.forEach((searchPanel) => {
        const searchButton = searchPanel.querySelector('.search-bar__button');
        const resetButton = searchPanel.querySelector(
          '.search-panel__filter-reset'
        );
        const input = searchPanel.querySelector('input');

        searchButton.addEventListener('click', searchFromList);
        resetButton.addEventListener('click', resetSearch);
        input.addEventListener('keydown', handleInputEvent);
      });
    }
  },
};
