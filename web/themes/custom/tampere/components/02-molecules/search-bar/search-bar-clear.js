/**
 * @file
 * Search bar clear ("X") button: shown while the input has a value; clicking
 * it clears the field only (no search is triggered).
 */

/**
 * @param {Document|Element} context
 *   The context to scan for new elements.
 */
function attachSearchBarClear(context) {
  let clearLabel;
  try {
    clearLabel = Drupal.t('Clear search');
  } catch (e) {
    clearLabel = 'Clear search';
  }

  let forms;
  try {
    forms = once('search-bar-clear', 'form[data-search-bar-clear-host]', context);
  } catch (e) {
    // once is unavailable in Storybook.
    forms = context.querySelectorAll('form[data-search-bar-clear-host]');
  }

  forms.forEach((form) => {
    const clearButton = form.querySelector('[data-search-bar-clear]');

    if (!clearButton) {
      return;
    }

    // Cludo (React) re-manages the input, so delegate on the form and
    // re-query the live input on every event instead of binding to it.
    const getLiveInput = () => form.querySelector('.search-bar__input');

    let lastValue = '';

    const sync = () => {
      const input = getLiveInput();
      if (!input) {
        return;
      }
      const hasValue = input.value.trim().length > 0;
      if (input.value === lastValue && clearButton.hidden === !hasValue) {
        return;
      }
      lastValue = input.value;
      clearButton.hidden = !hasValue;
      clearButton.setAttribute('aria-label', clearLabel);
      // Reserves room for the X inside the input.
      form.closest('.search-bar')?.classList.toggle('search-bar--has-value', hasValue);
    };

    // `input`/`change` cover typing; Cludo suggestion picks fire neither.
    form.addEventListener('input', sync);
    form.addEventListener('change', sync);

    // Cludo sets the value programmatically when a suggestion is picked, with
    // no event and an unpredictable delay, so poll while the form has focus.
    let poll;
    form.addEventListener('focusin', () => {
      if (!poll) {
        poll = setInterval(sync, 250);
      }
    });
    form.addEventListener('focusout', (event) => {
      if (!form.contains(event.relatedTarget)) {
        clearInterval(poll);
        poll = null;
        setTimeout(sync, 250);
      }
    });
    ['mousedown', 'click'].forEach((type) => {
      form.addEventListener(type, () => setTimeout(sync, 100));
    });

    clearButton.addEventListener('click', () => {
      const input = getLiveInput();
      if (!input) {
        return;
      }
      // Use the native setter + input event so React picks up the change.
      const valueSetter = Object.getOwnPropertyDescriptor(
        window.HTMLInputElement.prototype,
        'value',
      ).set;
      valueSetter.call(input, '');
      input.dispatchEvent(new Event('input', { bubbles: true }));
      sync();
      input.focus();
    });

    sync();
  });
}

Drupal.behaviors.searchBarClear = {
  attach(context) {
    attachSearchBarClear(context);
  },
};
