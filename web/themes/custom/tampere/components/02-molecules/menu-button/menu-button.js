/**
 * @file
 * A JavaScript file containing the menu button functionality.
 *
 */

(function (Drupal) {
  'use strict';

  Drupal.behaviors.menuButton = { // eslint-disable-line no-param-reassign
    attach(context) {
      context.querySelectorAll('.menu-button:not([data-js-bound])').forEach((button) => {
        button.setAttribute('data-js-bound', 'true');

        button.addEventListener('click', () => {
          const isExpanded = button.getAttribute('aria-expanded') === 'true';
          const newExpanded = !isExpanded;

          const labelSpan = button.querySelector('span');
          // Update button label based on data attributes
          const openText = labelSpan.getAttribute('data-open');
          const closeText = labelSpan.getAttribute('data-close');

          if (labelSpan && openText && closeText) {
            labelSpan.textContent = newExpanded ? closeText : openText;
          }
        });
      });
    },
  };
}(Drupal));
