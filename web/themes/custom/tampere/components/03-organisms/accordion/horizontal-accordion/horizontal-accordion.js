Drupal.behaviors.horizontalAccordion = {
  attach(context) {
    const accordionHeadings = context.querySelectorAll(
      '.horizontal-accordion__button'
    );
    const accordionContents = context.querySelectorAll(
      '.horizontal-accordion__content'
    );
    const horizontalAccordion = context.querySelectorAll(
      '.horizontal-accordion'
    );
    let activeIndex = 0;
    let nestedActiveIndex = null;

    /**
     * toggleAccordion
     * @description Toggles specific accordion based on index. Returns nothing.
     * @param {Number} index The index of the accordion to open.
     */
    function toggleAccordion(index) {
      if (
        index !== activeIndex &&
        index >= 0 &&
        index <= accordionHeadings.length &&
        !accordionHeadings[index].classList.contains('nested-accordion')
      ) {
        accordionHeadings[activeIndex].classList.remove('is-active');
        accordionHeadings[activeIndex].setAttribute('aria-expanded', false);
        accordionContents[activeIndex].classList.remove('active');
        accordionContents[activeIndex].setAttribute('aria-hidden', true);
        accordionHeadings[index].classList.add('is-active');
        accordionHeadings[index].setAttribute('aria-expanded', true);
        accordionContents[index].classList.add('active');
        accordionContents[index].setAttribute('aria-hidden', false);
        activeIndex = index;
      } else if (
        index !== nestedActiveIndex &&
        index >= 0 &&
        index <= accordionHeadings.length &&
        accordionHeadings[index].classList.contains('nested-accordion')
      ) {
        if (nestedActiveIndex !== null) {
          accordionHeadings[nestedActiveIndex].classList.remove('is-active');
          accordionHeadings[nestedActiveIndex].setAttribute(
            'aria-expanded',
            false
          );
          accordionContents[nestedActiveIndex].classList.remove('active');
          accordionContents[nestedActiveIndex].setAttribute(
            'aria-hidden',
            true
          );
        }
        accordionHeadings[index].classList.add('is-active');
        accordionHeadings[index].setAttribute('aria-expanded', true);
        accordionContents[index].classList.add('active');
        accordionContents[index].setAttribute('aria-hidden', false);
        nestedActiveIndex = index;
      } else {
        if (accordionHeadings[index].classList.contains('is-active')) {
          accordionHeadings[index].setAttribute('aria-expanded', false);
        } else {
          accordionHeadings[index].setAttribute('aria-expanded', true);
        }
        accordionHeadings[index].classList.toggle('is-active');
        if (accordionContents[index].classList.contains('active')) {
          accordionContents[index].setAttribute('aria-hidden', true);
        } else {
          accordionContents[index].setAttribute('aria-hidden', false);
        }
        accordionContents[index].classList.toggle('active');
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
      link.addEventListener('click', (e) => {
        e.preventDefault();
        toggleAccordion(index);
      });
    }

    for (let i = 0; i < horizontalAccordion.length; i += 1) {
      if (
        horizontalAccordion[i].closest('.accordion') ||
        horizontalAccordion[i].closest('.process-accordion')
      ) {
        const nestedAccordionHeadings = horizontalAccordion[i].querySelectorAll(
          '.accordion-heading'
        );
        for (let j = 0; j < nestedAccordionHeadings.length; j += 1) {
          nestedAccordionHeadings[j].classList.add('nested-accordion');
        }
      }
    }

    for (let i = 0; i < accordionHeadings.length; i += 1) {
      const link = accordionHeadings[i];
      handleClick(link, i);
    }
  },
};
