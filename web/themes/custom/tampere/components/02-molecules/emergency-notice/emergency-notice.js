/**
 * @file
 * A JavaScript file containing the emergency notice closing functionality.
 *
 */

Drupal.behaviors.emergencyNotice = {
  attach(context) {
    const myStorage = window.localStorage;
    const emergencyNotices = document.querySelectorAll(
      '.emergency-notice',
      context,
    );
    const closeButtons = document.querySelectorAll(
      '.emergency-notice__close-button',
      context,
    );

    /**
     * handleClick
     * @description Handles click event listeners on each of the close buttons
     * in the emergency notice. Returns nothing.
     *
     * @param {HTMLElement} button The button to listen for events on
     * @param {HTMLElement} notice The notice that is going to be hidden
     */
    function handleClick(button, notice) {
      button.addEventListener('click', () => {
        notice.style.display = 'none'; // eslint-disable-line no-param-reassign
        myStorage.setItem(`emergencyNotice-${notice.id}`, 'closed');
      });
    }

    for (let i = 0; i < closeButtons.length; i += 1) {
      const closeButton = closeButtons[i];
      const emergencyNotice = emergencyNotices[i];

      // do not display emergy notice if the close button has already been pushed
      if (myStorage.getItem(`emergencyNotice-${emergencyNotice.id}`) === null) {
        emergencyNotice.classList.remove('hidden');
        emergencyNotice.classList.add('shown');
      }

      handleClick(closeButton, emergencyNotice);
    }
  },
};
