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
  const labelSpan = button.querySelector('span');
  // Update button label based on data attributes
  const openText = labelSpan.getAttribute('data-open');
  const closeText = labelSpan.getAttribute('data-close');

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

  // Open/ Close text for menu
  if (isAriaExpanded) {
    labelSpan.textContent = openText;
  } else {
    labelSpan.textContent = closeText;
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

    /*
    * Once doesn't work in Storybook currently.
    * Catch only catches JS errors not logical shortcomings,
    * so add a fallback inside Try to make sure site header is not undefined.
    */
    try {
      siteHeader = once('site-header', document.getElementById('site-header'), context).shift() || document.getElementById('site-header'); // eslint-disable-line
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

      // Set menu buttons and body explicitly here if they happen to return
      // as undefined from once and dont end up in the catch.
      if (!menuButtons || menuButtons.length === 0) {
        menuButtons = document.querySelectorAll('.menu-button');
      }

      if (!bodyElem || bodyElem.length === 0) {
        bodyElem = document.body;
      }

      if (menuButtons) {
        menuButtons.forEach((menuButton) => {
          menuButton.setAttribute('data-js-bound', 'true');
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

    const translateBlockForMinisite = document.querySelector('.minisite-header__translate');
    if (translateBlockForMinisite) {
      const desktopTarget = document.querySelector('.minisite-header__language-switcher');
      const mobileTarget = document.querySelector('.minisite-header__language-switcher--mobile');

      // eslint-disable-next-line
      function moveTranslateBlock() {
        const mediaQuery = window.matchMedia('(max-width: 61.56rem)');
        const isMobile = mediaQuery.matches;

        const target = isMobile ? mobileTarget : desktopTarget;

        if (translateBlockForMinisite && target
          && translateBlockForMinisite.parentElement !== target.parentElement) {
          // Move translateBlock right after the target element
          target.insertAdjacentElement('afterend', translateBlockForMinisite);
        }
      }

      // Initial move on load
      moveTranslateBlock();

      // Move again on resize
      window.addEventListener('resize', moveTranslateBlock);
    }

    const initialPageLang = document.documentElement.lang;

    once('toggle-translate', '.site-header__translate, .minisite-header__translate, .minisite-header__translate-mobile').forEach((translateBlock) => {
      const translateButton = translateBlock.querySelector(
        '.header-translate-expand',
      );

      const translateMenu = translateBlock.querySelector(
        '.header-translate-block',
      );

      const manageCookiesSection = translateBlock.querySelector(
        '.manage-cookies-section',
      );

      const googleTranslate = translateBlock.querySelector(
        '#block-tampere-openygoogletranslate',
      );

      // Helper for checking if the lang attribute has changed from initial value.
      function isTranslationActive() {
        return document.documentElement.lang !== initialPageLang;
      }

      // Returns user cookie consent status.
      function hasConsent() {
        const cookieTypes = ['cookie_cat_necessary', 'cookie_cat_functional'];
        return cookieTypes.every((type) =>
          // eslint-disable-next-line
          CookieInformation.getConsentGivenFor(type));
      }

      // Function creates an observer that looks for the Google translate
      // banner in the DOM. Once its injected by the script, this updates
      // it to hidden / visible based on user consent and translation selection.
      function setupBannerObserver() {
        // eslint-disable-next-line no-unused-vars
        const observer = new MutationObserver((mutations, obs) => {
          const banner = document.querySelector('[class="skiptranslate"]');
          if (banner) {
            const consentGiven = hasConsent();
            const translationActive = isTranslationActive();

            if (consentGiven && translationActive) {
              banner.firstChild.setAttribute('style', 'visibility:visibile');
              document.body.classList.remove('no-translate-banner');
            } else {
              banner.firstChild.setAttribute('style', 'visibility:hidden');
              document.body.classList.add('no-translate-banner');
            }
          }
        });

        observer.observe(document.body, { childList: true, subtree: true });
      }

      // Toggles translate widget / cookie menu based on user consent.
      function updateWidgetState() {
        const consentGiven = hasConsent();

        if (consentGiven) {
          manageCookiesSection.hidden = true;
          googleTranslate.hidden = false;
        } else {
          manageCookiesSection.hidden = false;
          googleTranslate.hidden = true;
        }
      }

      updateWidgetState();
      setupBannerObserver();

      // When cookie consent changes, update state again.
      window.addEventListener('CookieInformationConsentGiven', () => {
        updateWidgetState();
        setupBannerObserver();
      });

      // Event listener for toggling the translate menu.
      translateButton.addEventListener('click', () => {
        translateMenu.hidden = !translateMenu.hidden;

        const googleTranslateDropDown = translateMenu.querySelector(
          '.goog-te-combo',
        );

        // Add translated text after the dropdown
        if (googleTranslateDropDown
          && (!googleTranslateDropDown.nextElementSibling
          || !googleTranslateDropDown.nextElementSibling.classList.contains('translator-message'))) {
          googleTranslateDropDown.insertAdjacentHTML(
            'afterend',
            `<div class="translator-message">
              ${Drupal.t('The City of Tampere is not responsible for translations made by Google Translate.')}
            </div>`,
          );
        }
      });

      // Close the translate menu if clicked outside.
      document.addEventListener('click', (event) => {
        if (!translateBlock.contains(event.target)) {
          translateMenu.hidden = true;
        }
      });
    });
  },
};
