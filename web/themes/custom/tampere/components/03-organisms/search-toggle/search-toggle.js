/**
 * @file
 * A JavaScript file containing the search toggle functionality.
 *
 */

/**
 * Toggles search bar visibility for given search toggle button.
 *
 * @param {HTMLElement} button
 *   The search toggle button that controls a search bar.
 */
function toggleSearchBar(button) {
  const isAriaExpanded = button.getAttribute('aria-expanded') === 'true';
  const closestMinisiteHeader = button.closest('.minisite-header');
  const isMobile = button.dataset.toggleMode === 'mobile';
  const headerClass = isMobile
    ? 'mobile-search-bar-visible'
    : 'desktop-search-bar-visible';

  let ariaLabel;

  try {
    ariaLabel = !isAriaExpanded
      ? Drupal.t('Hide search bar')
      : Drupal.t('Show search bar');
  } catch (e) {
    ariaLabel = !isAriaExpanded ? 'Hide search bar' : 'Show search bar';
  }

  button.classList.toggle('is-expanded');
  button.setAttribute('aria-expanded', !isAriaExpanded ? 'true' : 'false');
  button.setAttribute('aria-label', ariaLabel);

  if (closestMinisiteHeader) {
    closestMinisiteHeader.classList.toggle(headerClass);
  }
}

/**
 * Handles interaction with the search toggle button.
 *
 * @param {Event} event
 *   The event on the search toggle button.
 */
function handleSearchToggleButtonInteraction(event) {
  const selectedSearchToggle = event.currentTarget;

  toggleSearchBar(selectedSearchToggle);
}

/**
 * Handles interaction with the body element.
 *
 * Hides search bar if click/tap occurs outside the search toggle container.
 *
 * @param {Event} event
 *   The event on body element.
 */
function handleSearchToggleBodyInteraction(event) {
  const selectedSearchToggle = event.target.closest('.search-toggle');

  const expandedSearchToggleButtons = document.querySelectorAll(
    '.search-toggle__button[aria-expanded=true]'
  );

  if (!selectedSearchToggle && expandedSearchToggleButtons.length > 0) {
    expandedSearchToggleButtons.forEach((button) => {
      toggleSearchBar(button);
    });
  }
}

Drupal.behaviors.searchToggle = {
  attach(context) {
    let bodyElem;
    let searchToggles;

    // Once doesn't work in Storybook currently.
    try {
      searchToggles = once('search-toggle', document.querySelectorAll('.search-toggle__button'), context); // eslint-disable-line
    } catch (e) {
      searchToggles = document.querySelectorAll('.search-toggle__button');
    }

    if (searchToggles) {
      try {
        bodyElem = once('search-toggle-body', document.body, context).shift(); // eslint-disable-line
      } catch (e) {
        bodyElem = document.body;
      }

      searchToggles.forEach((searchToggle) => {
        searchToggle.addEventListener(
          'click',
          handleSearchToggleButtonInteraction
        );
        searchToggle.addEventListener(
          'tap',
          handleSearchToggleButtonInteraction
        );
      });

      if (bodyElem) {
        bodyElem.addEventListener('click', handleSearchToggleBodyInteraction);
        bodyElem.addEventListener('tap', handleSearchToggleBodyInteraction);
      }
    }
  },
};
