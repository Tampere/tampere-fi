/**
 * @file
 * A JavaScript file containing the site header functionality.
 *
 */

/**
 * Toggles menu container visibility for given menu button.
 *
 * @param {HTMLElement} button
 *   The menu button that controls a container.
 */
function toggleMenuContainer(button) {
  const header = document.getElementById('site-header');
  const isAriaExpanded = button.getAttribute('aria-expanded') === 'true';
  const controlledContainerId = button.getAttribute('aria-controls');
  const navigationContainer = document.getElementById(controlledContainerId);

  let ariaLabel;

  try {
    ariaLabel = !isAriaExpanded
      ? Drupal.t('Hide menu')
      : Drupal.t('Show menu');
  } catch (e) {
    ariaLabel = !isAriaExpanded ? 'Hide menu' : 'Show menu';
  }

  navigationContainer.classList.toggle('is-closed');
  button.classList.toggle('is-open');
  header.classList.toggle('menu-closed');

  button.setAttribute('aria-expanded', !isAriaExpanded ? 'true' : 'false');
  button.setAttribute('aria-label', ariaLabel);
}

/**
 * Handles interaction with the menu button.
 *
 * @param {Event} event
 *   The event on the menu button.
 */
function handleMenuButtonInteraction(event) {
  const menuButton = event.currentTarget;

  event.stopImmediatePropagation();

  toggleMenuContainer(menuButton);
}

/**
 * Handles interaction with the body element.
 *
 * Closes navigation container if click/tap occurs outside the container.
 * The container is different on desktop and mobile.
 *
 * @param {Event} event
 *   The event on body element.
 */
function handleHeaderBodyInteraction(event) {
  const selectedHeaderMenuContainer =
    event.target.closest('.site-header__navigation-container') ||
    event.target.closest('.site-header__menu') ||
    event.target.closest('.minisite-header__navigation');

  const expandedNavContainerButtons = document.querySelectorAll(
    '.menu-button[aria-expanded=true]'
  );

  if (!selectedHeaderMenuContainer && expandedNavContainerButtons.length > 0) {
    expandedNavContainerButtons.forEach((button) => {
      toggleMenuContainer(button);
    });
  }
}

/**
 * Handles focus leaving the site header.
 *
 * @param {Event} event
 *   The event on site header.
 */
function handleHeaderFocusOut(event) {
  const header = document.getElementById('site-header');
  // The site-header navigation container on mobile now contains
  // accordions that will trigger the focusout event. It is necessary to ensure
  // that the event wasn't caused by the accordions so that the menu button
  // works correctly on iOS devices.
  const focusOutNotCausedByNavigationAccordion = !event.originalTarget.classList.contains('accordion__heading');

  if (!header.contains(event.relatedTarget) && focusOutNotCausedByNavigationAccordion) {
    const expandedNavContainerButton = header.querySelector(
      '.menu-button[aria-expanded=true]'
    );

    if (expandedNavContainerButton) {
      toggleMenuContainer(expandedNavContainerButton);
    }
  }
}

Drupal.behaviors.siteHeader = {
  attach(context) {
    let bodyElem;
    let menuButtons;
    let siteHeader;

    // Once doesn't work in Storybook currently.
    try {
      siteHeader = once('site-header', document.getElementById('site-header'), context).shift(); // eslint-disable-line
    } catch (e) {
      siteHeader = document.getElementById('site-header');
    }

    if (siteHeader) {
      try {
        menuButtons = once('site-header-menu-buttons', '.menu-button', context); // eslint-disable-line
        bodyElem = once('site-header-body', document.body, context).shift(); // eslint-disable-line
      } catch (e) {
        menuButtons = document.querySelectorAll('.menu-button');
        bodyElem = document.body;
      }

      if (menuButtons) {
        menuButtons.forEach((menuButton) => {
          menuButton.addEventListener('click', handleMenuButtonInteraction);
        });
      }

      if (bodyElem) {
        bodyElem.addEventListener('click', handleHeaderBodyInteraction);
      }

      siteHeader.addEventListener('focusout', handleHeaderFocusOut);
    }
  },
};
