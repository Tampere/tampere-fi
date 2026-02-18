/**
 * @file
 * A JavaScript file containing the accordion functionality.
 */

/**
 * Closes other accordions inside given accordion.
 *
 * @param {HTMLElement} accordionItem
 *   The accordion item element that may contain active nested accordions.
 */
function closeNestedAccordions(accordionItem) {
  const openNestedAccordionButtons = accordionItem.querySelectorAll(
    '.nested-accordion.is-active',
  );

  if (openNestedAccordionButtons && openNestedAccordionButtons.length > 0) {
    openNestedAccordionButtons.forEach((accordionButton) => {
      // eslint-disable-next-line no-use-before-define
      toggleAccordion(accordionButton);
    });
  }
}

/**
 * Handles button updating.
 * @param {HTMLElement} controlButton - The button that was clicked to trigger this action.
 * @param {HTMLElement} accordionContainer - The container for the accordions.
 */
function updateToggleAllButtons(accordionContainer, controlButton, state) {
  if (!accordionContainer || !controlButton) return;

  const buttonSiblingClass = state === 'open' ? 'close' : 'open';
  const buttonSibling = accordionContainer.querySelector(
    `.accordion__${buttonSiblingClass}-all-button`,
  );

  if (buttonSibling) {
    buttonSibling.classList.remove('hidden');
  }

  if (state === 'close') {
    accordionContainer.setAttribute('data-js-expanded-items', 'false');
    accordionContainer.classList.remove('accordion--expanded-items');

    controlButton.classList.add('hidden');
  } else {
    accordionContainer.setAttribute('data-js-expanded-items', 'true');
    accordionContainer.classList.add('accordion--expanded-items');

    controlButton.classList.add('hidden');
  }
}

/**
 * Toggles accordion state.
 *
 * @param {HTMLElement} accordionButton
 *   The accordion button element.
 */
function toggleAccordion(accordionButton, forceState) {
  // If forceState is defined, use it; otherwise, toggle based on current state
  const shouldExpand = forceState !== undefined
    ? forceState
    : !(accordionButton.getAttribute('aria-expanded') === 'true');

  const accordionItem = accordionButton.closest('.accordion__item');
  const accordionContentId = accordionButton.getAttribute('aria-controls');
  const accordionContent = accordionItem.querySelector(
    `#${accordionContentId}`,
  );
  const accordionContainer = accordionButton.closest('.paragraph--type-accordions')
    || accordionButton.closest('.paragraph')
    || accordionButton.closest('.accordion');

  const closeAllButton = accordionContainer.querySelector(
    '.accordion__close-all-button',
  );
  const openAllButton = accordionContainer.querySelector(
    '.accordion__open-all-button',
  );

  if (!shouldExpand) {
    if (closeAllButton) {
      closeAllButton.classList.add('hidden');
    }
    if (openAllButton) openAllButton.classList.remove('hidden');
  }

  const openIconText = accordionButton.querySelector('.icon-text--open');
  const closeIconText = accordionButton.querySelector('.icon-text--close');
  if (openIconText && closeIconText) {
    if (shouldExpand) {
      openIconText.setAttribute('aria-hidden', 'true');
      closeIconText.setAttribute('aria-hidden', 'false');
    } else {
      openIconText.setAttribute('aria-hidden', 'false');
      closeIconText.setAttribute('aria-hidden', 'true');
    }
  }

  if (accordionButton && accordionContent) {
    accordionButton.setAttribute('aria-expanded', shouldExpand);
    accordionButton.classList.toggle('is-active', shouldExpand);
    accordionContent.setAttribute('aria-hidden', !shouldExpand);
    accordionContent.classList.toggle('active', shouldExpand);
  }

  // Close nested accordions if the accordion is being closed
  if (!shouldExpand) {
    closeNestedAccordions(accordionItem);
  }

  const allAccordionButtons = accordionContainer.querySelectorAll(
    '.accordion__heading',
  );
  const allOpen = Array.from(allAccordionButtons).every(
    (button) => button.getAttribute('aria-expanded') === 'true',
  );

  if (allOpen) {
    updateToggleAllButtons(accordionContainer, openAllButton, 'open'); //
  }
}

/**
 * Handles accordion button interaction.
 *
 * @param {Event} event
 *   The event on the accordion button.
 */
function handleAccordionClick(event) {
  toggleAccordion(event.currentTarget);
}

/**
 * Closes all accordion items within the same container.
 * @param {HTMLElement} controlButton - The button that was clicked to trigger this action.
 */
function closeAllAccordions(controlButton) {
  const accordionContainer = controlButton.closest('.paragraph--type-accordions')
    || controlButton.closest('.paragraph')
    || controlButton.closest('.accordion');
  const accordionButtons = accordionContainer.querySelectorAll(
    '.accordion__heading[aria-expanded="true"]',
  );
  accordionButtons.forEach((button) => toggleAccordion(button, false)); // Explicitly close each

  updateToggleAllButtons(accordionContainer, controlButton, 'close');
}

/**
 * Opens all accordion items within the same container.
 * @param {HTMLElement} controlButton - The button that was clicked to trigger this action.
 */
function openAllAccordions(controlButton) {
  const accordionContainer = controlButton.closest('.paragraph--type-accordions')
    || controlButton.closest('.paragraph')
    || controlButton.closest('.accordion');
  const accordionButtons = accordionContainer.querySelectorAll(
    '.accordion__heading:not([aria-expanded="true"])',
  );
  accordionButtons.forEach((button) => toggleAccordion(button, true)); // Explicitly open each

  updateToggleAllButtons(accordionContainer, controlButton, 'open');
}

/*
* Handles embedded accordion behaviour
*/
function embeddedAccordion() {
  // Get the Breakpoint logic
  if (!window.themeBreakpoints || !window.themeBreakpoints.Breakpoints[2]) return;

  const mqlString = window.themeBreakpoints.Breakpoints[2].mediaQuery; // Medium breakpoint
  const mql = window.matchMedia(mqlString);

  // Define the logic that applies the state (Desktop vs Mobile)
  const applyState = (isDesktop) => {
    const containerSelector = '.view-urban-planning-embedded-content-tab .embedded-content-tab-accordion';
    const accordionTitles = document.querySelectorAll(`${containerSelector} .accordion__title-wrapper`);
    const accordionContents = document.querySelectorAll(`${containerSelector} .accordion__content`);
    const accordionButtons = document.querySelectorAll(`${containerSelector} .accordion__heading`);
    const iconOpen = document.querySelectorAll(`${containerSelector} .accordion__icon-text--open`);
    const iconClose = document.querySelectorAll(`${containerSelector} .accordion__icon-text--close`);

    if (isDesktop) {
      // Desktop state (accordion open, title hidden)
      accordionTitles.forEach((title) => title.classList.add('hidden'));

      accordionButtons.forEach((button) => {
        button.classList.add('is-active');
        button.setAttribute('aria-expanded', 'true');
      });

      for (let i = 0; i < accordionContents.length; i += 1) {
        const content = accordionContents[i];
        content.classList.add('active');
        content.setAttribute('aria-hidden', 'false');
        content.style.display = '';
      }
    } else {
      // Mobile state (force closed accordion, show title)
      accordionTitles.forEach((title) => title.classList.remove('hidden'));

      accordionButtons.forEach((button) => {
        button.classList.remove('is-active');
        button.setAttribute('aria-expanded', 'false');
      });

      for (let i = 0; i < accordionContents.length; i += 1) {
        const content = accordionContents[i];
        content.classList.remove('active');
        content.setAttribute('aria-hidden', 'true');
        content.style.display = '';
      }

      // Reset icons to closed state
      iconOpen.forEach((icon) => icon.setAttribute('aria-hidden', 'false'));
      iconClose.forEach((icon) => icon.setAttribute('aria-hidden', 'true'));
    }
  };

  // Run immediately on load
  applyState(mql.matches);

  // Attach Resize Listener
  // Use a global flag to ensure we don't attach this listener more than once
  if (!window.embeddedAccordionListenerAttached) {
    // For modern browsers
    try {
      mql.addEventListener('change', (event) => applyState(event.matches));
    } catch (e) {
      // Fallback for older browsers
      mql.addListener((event) => applyState(event.matches));
    }
    window.embeddedAccordionListenerAttached = true;
  }
}

Drupal.behaviors.accordion = {
  attach(context) {
    let accordionButtons;

    try {
      // eslint-disable-next-line no-undef
      accordionButtons = once(
        'accordion-buttons',
        '.accordion__heading',
        context,
      );
    } catch (e) {
      accordionButtons = document.querySelectorAll(
        '.accordion__heading',
        context,
      );
    }

    if (accordionButtons && accordionButtons.length > 0) {
      accordionButtons.forEach((accordionButton) => {
        accordionButton.addEventListener('click', handleAccordionClick);
      });
    }

    // Handle close all button
    const closeAllButtons = context.querySelectorAll(
      '.accordion__close-all-button',
    );
    if (closeAllButtons) {
      closeAllButtons.forEach((button) => {
        button.addEventListener('click', () => closeAllAccordions(button));
      });
    }

    // Handle open all button
    const openAllButtons = context.querySelectorAll(
      '.accordion__open-all-button',
    );
    if (openAllButtons) {
      openAllButtons.forEach((button) => {
        button.addEventListener('click', () => openAllAccordions(button));
      });
    }

    // Handles embedded accordion behaviour
    embeddedAccordion();
  },
};
