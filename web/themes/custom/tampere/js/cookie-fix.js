// Fixes issue where CookieInformation settings button is in the
// wrong place when navigating with keyboard.
(function (Drupal) {
  Drupal.behaviors.cookieFix = {
    attach: function (context, settings) {
      // Only run this once on the initial page load
      if (context !== document) {
        return;
      }

      const wrapperSelector = '#cookie-information-template-wrapper';
      const buttonSelector = '#Coi-Renew';

      const targetAttribute = 'tabindex';
      const desiredValue = '0';

      // This function will be called when the wrapper is found
      const fixCookieComponent = (wrapperElement) => {
        let moved = false;
        
        // Move the CookieInformation wrapper to the end of the body
        if (document.body.lastChild !== wrapperElement) {
          document.body.appendChild(wrapperElement);
          moved = true;
        }

        // Find the button inside the wrapper and fix its tabindex
        const buttonElement = wrapperElement.querySelector(buttonSelector);
        if (buttonElement && buttonElement.getAttribute(targetAttribute) !== desiredValue) {
          buttonElement.setAttribute(targetAttribute, desiredValue);
        }
        
        return moved;
      };

      // Create an observer to watch for the wrapper being added to the DOM
      const observer = new MutationObserver((mutations, obs) => {
        const wrapper = document.querySelector(wrapperSelector);
        if (wrapper) {
          // Once found, fix it and stop observing
          fixCookieComponent(wrapper);
          obs.disconnect(); 
        }
      });

      // Start observing the body for added nodes
      observer.observe(document.body, {
        childList: true,
        subtree: true,
      });

      // As a fallback, check if the wrapper already exists
      const existingWrapper = document.querySelector(wrapperSelector);
      if (existingWrapper) {
        fixCookieComponent(existingWrapper);
        observer.disconnect();
      }
    },
  };
})(Drupal);