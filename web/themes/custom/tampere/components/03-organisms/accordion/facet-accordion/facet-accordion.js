/**
 * @file
 * A JavaScript file containing the facet accordion functionality.
 *
 */

Drupal.behaviors.facetAccordion = {
  // eslint-disable-next-line no-unused-vars
  attach(context) {
    const facetItems = context.querySelectorAll('a');
    const removeFacets = document.querySelectorAll('.remove-facets');
    const facetInactive = document.querySelectorAll('.facet-inactive.hidden');
    const accordionHeadings = document.querySelectorAll(
      '.facet-accordion-item__heading'
    );
    const accordionContents = document.querySelectorAll(
      '.facet-accordion-item__content'
    );

    /**
     * toggleAccordion
     * @description Toggles specific accordion based on index. Returns nothing.
     * @param {Number} index The index of the accordion to open.
     */
    function toggleAccordion(index) {
      for (let i = 0; i < accordionHeadings.length; i += 1) {
        if (i !== index) {
          accordionHeadings[i].setAttribute('aria-expanded', false);
          accordionHeadings[i].classList.remove('is-active');
          accordionContents[i].setAttribute('aria-hidden', true);
          accordionContents[i].classList.remove('active');
        } else {
          accordionHeadings[i].setAttribute('aria-expanded', true);
          accordionHeadings[i].classList.add('is-active');
          accordionContents[i].setAttribute('aria-hidden', false);
          accordionContents[i].classList.add('active');
        }
      }
    }

    /**
     * handleClick
     * @description Handles click event listeners on each of the Headings in the
     *   tab accordion. Returns nothing.
     * @param {HTMLElement} link The button to listen for events on
     * @param {Number} index The index of that button
     */
    function handleClick(link, index) {
      const listener = (e) => {
        e.preventDefault();
        toggleAccordion(index);
      };
      // This handler function is run numerous times during the page render and
      // it seems necessary to remove any old copies of the event listener as
      // the amount of items in accordionHeadings and accordionContents change
      // between runs.
      link.removeEventListener('click', listener);
      link.addEventListener('click', listener);
    }

    /**
     * checkChosenFacetItems
     * @description Checks active facet items. Adds the number of active
     *   facet items in the heading when parent id is identical to heading
     *   aria-controls. Returns nothing.
     * @param {Number} index The index of the heading in question
     */
    function checkChosenFacetItems(index) {
      let facetItemChosen = 0;
      let parentId = null;
      const parentIds = [];

      for (let j = 0; j < facetItems.length; j += 1) {
        if (facetItems[j].classList.contains('is-active')) {
          facetItems[j].parentNode.classList.add('is-active');
          parentId = facetItems[j].parentNode.parentNode.id;
          parentIds.push(parentId);
          facetItemChosen += 1;
          if (removeFacets[0]) {
            removeFacets[0].style.display = 'block';
          }
        }
      }
      // Add amount of active facets to the heading when aria-controls and parent id is identical
      // Also make heading facet number visible
      if (accordionHeadings[index].getAttribute('aria-controls') === parentId) {
        accordionHeadings[index].children[1].textContent = facetItemChosen;
        accordionHeadings[index].children[1].style.display = 'block';
      }
    }

    for (let i = 0; i < accordionHeadings.length; i += 1) {
      const link = accordionHeadings[i];
      handleClick(link, i);
      // Move facet accordion headings inside facet-accordion__headings class
      // in order to style them separately from facet accordion items.
      document
        .getElementsByClassName('facet-accordion__headings')[0]
        .appendChild(link);

      checkChosenFacetItems(i);
    }

    // Make added facet item content active
    facetInactive.forEach((inactive) => {
      inactive.classList.remove('facet-inactive');
      inactive.classList.remove('hidden');
      inactive.classList.add('facet-active');
    });
  },
};
