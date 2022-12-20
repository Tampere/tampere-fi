/**
 * @file
 * A JavaScript file containing the accordion functionality.
 *
 */

/**
 * Closes other accordions inside given accordion.
 *
 * @param {HTMLElement} accordionItem
 *   The accordion item element that may contain active nested accordions.
 */
function closeNestedAccordions(accordionItem) {
  const openNestedAccordionButtons = accordionItem.querySelectorAll(
    '.nested-accordion.is-active'
  );

  if (openNestedAccordionButtons && openNestedAccordionButtons.length > 0) {
    openNestedAccordionButtons.forEach((accordionButton) => {
      // eslint-disable-next-line no-use-before-define
      toggleAccordion(accordionButton);
    });
  }
}

/**
 * Toggles accordion state.
 *
 * @param {HTMLElement} accordionButton
 *   The accordion button element.
 */
function toggleAccordion(accordionButton) {
  const accordionItem = accordionButton.closest('.accordion__item');
  const accordionContentId = accordionButton.getAttribute('aria-controls');
  const accordionContent = accordionItem.querySelector(
    `#${accordionContentId}`
  );
  const isExpanded =
    accordionButton.getAttribute('aria-expanded') === 'true' || false;

  if (accordionButton && accordionContent) {
    accordionButton.setAttribute('aria-expanded', !isExpanded);
    accordionButton.classList.toggle('is-active');
    accordionContent.setAttribute('aria-hidden', isExpanded);
    accordionContent.classList.toggle('active');
  }

  if (isExpanded) {
    closeNestedAccordions(accordionItem);
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

Drupal.behaviors.accordion = {
  attach(context) {
    let accordionButtons;

    try {
      // eslint-disable-next-line no-undef
      accordionButtons = once(
        'accordion-buttons',
        '.accordion__heading',
        context
      );
    } catch (e) {
      accordionButtons = document.querySelectorAll(
        '.accordion__heading',
        context
      );
    }

    if (accordionButtons && accordionButtons.length > 0) {
      accordionButtons.forEach((accordionButton) => {
        accordionButton.addEventListener('click', handleAccordionClick);
      });
    }
  },
};
