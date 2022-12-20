/**
 * @file
 * A JavaScript file containing the language dropdown functionality.
 *
 */

/**
 * Handles interaction with the language dropdown button.
 *
 * @param {Event} event
 *   The event on the language dropdown button.
 */
function handleLanguageDropdownButtonInteraction(event) {
  const button = event.currentTarget;
  const dropdown = button.parentNode;
  const isExpanded = button.getAttribute('aria-expanded') === 'true' || false;
  const languageLink = dropdown.querySelector('.language-link');

  dropdown.classList.toggle('is-expanded');
  button.setAttribute('aria-expanded', !isExpanded);

  if (languageLink) {
    languageLink.focus();
  }
}

/**
 * Handles interaction with the body element.
 *
 * Closes language dropdown if click/tap occurs outside the dropdown.
 *
 * @param {Event} event
 *   The event on body element.
 */
function handleLanguageDropdownBodyInteraction(event) {
  const { button, dropdown } = event.currentTarget;
  const selectedLanguageDropdown = event.target.closest('#language-dropdown');

  if (!selectedLanguageDropdown) {
    dropdown.classList.remove('is-expanded');
    button.setAttribute('aria-expanded', false);
  }
}

Drupal.behaviors.languageDropdown = {
  attach(context) {
    const bodyOnceId = 'language-dropdown-body';

    let bodyElem;
    let languageDropdown;
    let languageDropdownButton;

    // Once doesn't work in Storybook currently.
    try {
      /* eslint-disable */
      bodyElem = once(bodyOnceId, document.body, context).shift();

      languageDropdown = once(
        'language-dropdown',
        '#language-dropdown',
        context
      ).shift();

      // For some reason, the body element will be empty for logged-in users
      // even though the once ID is applied correctly.
      if (!bodyElem) {
        bodyElem = once.find(bodyOnceId).shift();
      }

      /* eslint-enable */
    } catch (e) {
      bodyElem = document.body;
      languageDropdown = document.getElementById('language-dropdown');
    }

    if (languageDropdown) {
      try {
        /* eslint-disable */
        languageDropdownButton = once(
          'language-dropdown-button',
          '#language-dropdown-toggle',
          languageDropdown
        ).shift();
        /* eslint-enable */
      } catch (e) {
        languageDropdownButton = languageDropdown.querySelector(
          '#language-dropdown-toggle'
        );
      }

      if (languageDropdownButton) {
        languageDropdownButton.addEventListener(
          'click',
          handleLanguageDropdownButtonInteraction
        );

        languageDropdownButton.addEventListener(
          'tap',
          handleLanguageDropdownButtonInteraction
        );

        bodyElem.button = languageDropdownButton;
        bodyElem.dropdown = languageDropdown;

        bodyElem.addEventListener(
          'click',
          handleLanguageDropdownBodyInteraction
        );

        bodyElem.addEventListener('tap', handleLanguageDropdownBodyInteraction);
      }
    }
  },
};
