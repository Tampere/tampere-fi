/**
 * @file
 * A JavaScript file containing the facet accordion functionality.
 *
 */

Drupal.behaviors.facetAccordion = {
  // eslint-disable-next-line no-unused-vars
  attach(context) {
    const facetInactive = document.querySelectorAll('.facet-inactive.hidden');

    /**
     * Toggles state for given facet accordion button while closing all others.
     *
     * @param {HTMLElement} selectedAccordionButton
     *   The selected facet accordion button element.
     */
    function toggleFacetAccordionButton(selectedAccordionButton) {
      const selectedAccordionButtonId = selectedAccordionButton.getAttribute('id');
      const facetAccordionButtons = document.querySelectorAll(
        '.facet-accordion-item__heading',
      );

      facetAccordionButtons?.forEach((facetAccordionButton) => {
        const accordionButtonId = facetAccordionButton.getAttribute('id');
        const accordionContentId = facetAccordionButton.getAttribute('aria-controls');
        const accordionContent = document.querySelector(
          `#${accordionContentId}`,
        );

        // Toggle state only for selected accordion button.
        if (selectedAccordionButtonId === accordionButtonId) {
          const isExpanded = facetAccordionButton.getAttribute('aria-expanded') === 'true' || false;

          facetAccordionButton?.setAttribute('aria-expanded', !isExpanded);
          facetAccordionButton?.classList.toggle('is-active');
          accordionContent?.setAttribute('aria-hidden', isExpanded);
          accordionContent?.classList.toggle('active');
        } else {
          facetAccordionButton?.setAttribute('aria-expanded', false);
          facetAccordionButton?.classList.remove('is-active');
          accordionContent?.setAttribute('aria-hidden', true);
          accordionContent?.classList.remove('active');
        }
      });
    }

    /**
     * Handles facet accordion button interaction.
     *
     * @param {Event} event
     *   The event on the facet accordion button.
     */
    function handleFacetAccordionButtonClick(event) {
      toggleFacetAccordionButton(event.currentTarget);
    }

    /**
     * Counts active facets for selected heading and displays the amount.
     *
     * @param {HTMLElement} heading
     *   The selected facet accordion heading.
     */
    function countActiveFacetsForHeading(heading) {
      const contentId = heading.getAttribute('aria-controls');

      const content = document.querySelector(
        `#${contentId}`,
      );

      const activeItems = content.querySelectorAll(
        '.facet-accordion-item__list-item .is-active',
      );

      // Add 'is-active' class to all active item parents for styling.
      activeItems.forEach((item) => item.parentElement?.classList.add('is-active'));

      if (activeItems.length > 0) {
        const headingFacetNumber = heading.querySelector('.facet-accordion-item__facet-number');
        const headingFacetNumberDescription = heading.querySelector('.facet-accordion-item__count-desc');

        headingFacetNumber.textContent = activeItems.length;
        headingFacetNumber.style.display = 'block';
        headingFacetNumberDescription.style.display = 'block';
      }
    }

    let accordionHeadings;
    try {
      accordionHeadings = once('facet-accordion-item-headings', '.facet-accordion-item__heading', context);
    } catch (error) {
      accordionHeadings = document.querySelectorAll('.facet-accordion-item__heading');
    }

    accordionHeadings?.forEach((heading) => {
      // Move facet accordion headings inside 'facet-accordion__headings' class
      // in order to style them separately from facet accordion items.
      const facetAccordionHeadingsContainer = document.querySelector('.facet-accordion__headings');
      facetAccordionHeadingsContainer.appendChild(heading);

      heading.addEventListener('click', handleFacetAccordionButtonClick);
      countActiveFacetsForHeading(heading);
    });

    // Make added facet item content active
    facetInactive.forEach((inactive) => {
      inactive.classList.remove('facet-inactive');
      inactive.classList.remove('hidden');
      inactive.classList.add('facet-active');
    });
  },
};
