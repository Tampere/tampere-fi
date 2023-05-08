Drupal.behaviors.horizontalAccordion = {
  attach(context) {
    /**
     * Toggles state for given horizontal accordion button while closing all others.
     *
     * @param {HTMLElement} selectedAccordionButton
     *   The selected horizontal accordion button element.
     */
    function toggleHorizontalAccordionButton(selectedAccordionButton) {
      const selectedAccordionButtonId = selectedAccordionButton.getAttribute('id');
      const horizontalAccordion = selectedAccordionButton.closest('.horizontal-accordion');
      const horizontalAccordionButtons = horizontalAccordion?.querySelectorAll(
        '.horizontal-accordion__button'
      );

      horizontalAccordionButtons?.forEach(horizontalAccordionButton => {
        const accordionButtonId = horizontalAccordionButton.getAttribute('id');
        const accordionContentId = horizontalAccordionButton.getAttribute('aria-controls');
        const accordionContent = document.querySelector(
          `#${accordionContentId}`
        );

        // Toggle state only for selected accordion button.
        if (selectedAccordionButtonId === accordionButtonId) {
          const isExpanded =
            horizontalAccordionButton.getAttribute('aria-expanded') === 'true' || false;

          horizontalAccordionButton?.setAttribute('aria-expanded', !isExpanded);
          horizontalAccordionButton?.classList.toggle('is-active');
          accordionContent?.setAttribute('aria-hidden', isExpanded);
          accordionContent?.classList.toggle('active');
        }
        else {
          horizontalAccordionButton?.setAttribute('aria-expanded', false);
          horizontalAccordionButton?.classList.remove('is-active');
          accordionContent?.setAttribute('aria-hidden', true);
          accordionContent?.classList.remove('active');
        }
      });
    }

    /**
     * Handles horizontal accordion button interaction.
     *
     * @param {Event} event
     *   The event on the horizontal accordion button.
     */
    function handleHorizontalAccordionButtonClick(event) {
      toggleHorizontalAccordionButton(event.currentTarget);
    }

    let accordionButtons;
    try {
      accordionButtons = once('horizontal-accordion-buttons', '.horizontal-accordion__button', context);
    } catch (error) {
      accordionButtons = document.querySelectorAll('.horizontal-accordion__button');
    }

    accordionButtons?.forEach(heading => {
      heading.addEventListener('click', handleHorizontalAccordionButtonClick);
    });
  },
};
