/**
 * @file
 * A JavaScript file containing the site header functionality.
 *
 */

/**
 * Handles keydown events in the site header.
 *
 * Traps focus inside the open header menu overlay when on mobile.
 *
 * @param {Event} event
 *   The event on site header.
 */
function handleHeaderKeydown(event) {
  const isNotTabKey = event.key !== 'Tab';

  if (isNotTabKey) {
    return;
  }

  const openMobileMenuButton = document.querySelector('.menu-button--mobile.is-open');

  if (!openMobileMenuButton) {
    return;
  }

  const header = event.currentTarget;
  // Not an exhaustive list of focusable elements. Just ones relevant to the
  // 'site-header' component.
  const focusableElements = header.querySelectorAll('button, [href], input, [tabindex]:not([tabindex="-1"])');
  const firstFocusable = focusableElements[0];
  const lastFocusable = focusableElements[focusableElements.length - 1];

  if (event.shiftKey) {
    // When shift + tab is used, move to last focusable element if currently on
    // the first element.
    if (document.activeElement === firstFocusable) {
      lastFocusable.focus();
      event.preventDefault();
    }
  } else {
    // Otherwise, move to first focusable element if currently on the last
    // element.
    // eslint-disable-next-line
    if (document.activeElement === lastFocusable) {
      firstFocusable.focus();
      event.preventDefault();
    }
  }
}

/**
 * Toggles menu container visibility for given menu button.
 *
 * @param {HTMLElement} button
 *   The menu button that controls a container.
 */
function toggleMenuContainer(button) {
  const { body } = document;
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

  // Mobile menu needs some extra classes that desktop menu doesn't use.
  if (controlledContainerId.includes('mobile')) {
    if (isAriaExpanded) {
      body.classList.add('mobile-menu-closed');
      body.classList.remove('mobile-menu-open');
      header.classList.add('mobile-menu-closed');
      header.classList.remove('mobile-menu-open');
    } else {
      body.classList.add('mobile-menu-open');
      body.classList.remove('mobile-menu-closed');
      header.classList.add('mobile-menu-open');
      header.classList.remove('mobile-menu-closed');
    }
  }

  navigationContainer.classList.toggle('is-closed');
  button.classList.toggle('is-open');

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
 * Closes desktop or minisite navigation container if click/tap occurs outside
 * the container.
 *
 * @param {Event} event
 *   The event on body element.
 */
function handleHeaderBodyInteraction(event) {
  const selectedHeaderMenuContainer = event.target.closest('.site-header__navigation-container')
    || event.target.closest('.site-header__menu')
    || event.target.closest('.minisite-header__navigation');

  const expandedNavContainerButton = document.querySelector(
    '.menu-button--desktop[aria-expanded=true], .menu-button--minisite[aria-expanded=true]',
  );

  if (!selectedHeaderMenuContainer && expandedNavContainerButton) {
    toggleMenuContainer(expandedNavContainerButton);
  }
}

/**
 * Handles focus leaving the site header.
 *
 * Closes desktop and minisite navigation container if it is open.
 *
 * @param {Event} event
 *   The event on site header.
 */
function handleHeaderFocusOut(event) {
  const header = event.currentTarget;

  if (header.contains(event.relatedTarget)) {
    return;
  }

  const expandedNavContainerButton = header.querySelector(
    '.menu-button--desktop[aria-expanded=true], .menu-button--minisite[aria-expanded=true]',
  );

  if (expandedNavContainerButton) {
    toggleMenuContainer(expandedNavContainerButton);
  }
}

/**
 * Handles resize events.
 *
 * Ensures mobile menu related classes aren't active outside its intended
 * viewport size.
 */
function handleResize() {
  const mediaQuery = window.matchMedia('(max-width: 61.56rem)');

  if (mediaQuery.matches) {
    return;
  }

  const { body } = document;
  const header = document.getElementById('site-header');

  if (
    body.classList.contains('mobile-menu-closed')
    && header.classList.contains('mobile-menu-closed')
  ) {
    return;
  }

  const button = header.querySelector('.menu-button--mobile');
  const controlledContainerId = button.getAttribute('aria-controls');
  const navigationContainer = document.getElementById(controlledContainerId);

  body.classList.add('mobile-menu-closed');
  body.classList.remove('mobile-menu-open');
  button.classList.remove('is-open');
  header.classList.add('mobile-menu-closed');
  header.classList.remove('mobile-menu-open');
  navigationContainer.classList.add('is-closed');

  button.setAttribute('aria-expanded', 'false');
  button.setAttribute('aria-label', Drupal.t('Show menu'));
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

      // Add main site site-header specific event listeners.
      const isMinisite = siteHeader.classList.contains('minisite-header');
      if (!isMinisite) {
        siteHeader.addEventListener('keydown', handleHeaderKeydown);
        window.addEventListener('resize', () => requestAnimationFrame(handleResize));
      }
    }
  },
};
