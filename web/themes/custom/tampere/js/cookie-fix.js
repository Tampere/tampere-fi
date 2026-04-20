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
      const overlaySelector = '#coiOverlay';
      const bannerWrapperSelector = '#coi-banner-wrapper';
      const renewButtonSelector = '#Coi-Renew, .consent-placeholder__button';

      const targetAttribute = 'tabindex';
      const desiredValue = '0';

      const showAfterMs = 5000;

      // consent cookie name(s) to check for before showing the banner
      const consentCookieName = 'CookieInformationConsent';
      const delayClass = 'coi-delay-active';
      
      // Session key: keeps the first-visit timestamp for this browser tab/session.
      const sessionStartKey = 'coi-first-visit-timestamp';

      let delayTimerId = null;

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

      function hasConsentCookie() {
        return (`: ${document.cookie}`).includes(`: ${consentCookieName}=`);
      }

      function getOverlay() {
        return document.querySelector(overlaySelector);
      }

      function getBannerWrapper() {
        return document.querySelector(bannerWrapperSelector);
      }

      function isOverlayVisible() {
        const overlay = getOverlay();
        if (!overlay) {
          return false;
        }

        return (
          getComputedStyle(overlay).display !== 'none' &&
          overlay.getAttribute('aria-hidden') !== 'true'
        );
      }

      function openOverlay() {
        const overlay = getOverlay();
        const bannerWrapper = getBannerWrapper();

        if (!overlay || !bannerWrapper) {
          return;
        }

        overlay.style.removeProperty('display');
        bannerWrapper.style.removeProperty('display');

        if (getComputedStyle(overlay).display === 'none') {
          overlay.style.display = 'flex';
        }

        if (getComputedStyle(bannerWrapper).display === 'none') {
          bannerWrapper.style.display = 'block';
        }

        overlay.setAttribute('aria-hidden', 'false');
        bannerWrapper.setAttribute('aria-hidden', 'false');

        bannerWrapper.setAttribute('tabindex', '-1');
        bannerWrapper.focus();
      }


      function closeOverlay() {
        const overlay = getOverlay();
        const bannerWrapper = getBannerWrapper();

        if (!overlay || !bannerWrapper) {
          return;
        }

        overlay.style.display = 'none';
        overlay.setAttribute('aria-hidden', 'true');
        bannerWrapper.setAttribute('aria-hidden', 'true');
      }

      function toggleOverlay() {
        if (isOverlayVisible()) {
          closeOverlay();
        } else {
          openOverlay();
        }
      }

      function getSessionStartTime() {
        const stored = window.sessionStorage.getItem(sessionStartKey);

        if (stored) {
          const parsed = parseInt(stored, 10);
          if (!Number.isNaN(parsed)) {
            return parsed;
          }
        }

        const now = Date.now();
        window.sessionStorage.setItem(sessionStartKey, String(now));
        return now;
      }

      function startInitialDelay() {
        if (hasConsentCookie()) {
          document.documentElement.classList.remove(delayClass);
          return;
        }

        const sessionStartTime = getSessionStartTime();
        const elapsedMs = Date.now() - sessionStartTime;
        const remainingMs = Math.max(0, showAfterMs - elapsedMs);

        if (remainingMs <= 0) {
          document.documentElement.classList.remove(delayClass);
          return;
        }

        document.documentElement.classList.add(delayClass);

        delayTimerId = window.setTimeout(function () {
          document.documentElement.classList.remove(delayClass);
          delayTimerId = null;
        }, remainingMs);
      }

      function cancelInitialDelay() {
        if (delayTimerId) {
          window.clearTimeout(delayTimerId);
          delayTimerId = null;
        }

        document.documentElement.classList.remove(delayClass);
      }

      // Start delay immediately so the banner cannot flash open before we react.
      startInitialDelay();

      // If user clicks a consent action, stop any pending initial delay.
      document.addEventListener('click', function (event) {
        const clickedConsentAction = event.target.closest(
          '.coi-banner__accept, .coi-banner__decline, #updateButton'
        );

        if (clickedConsentAction) {
          cancelInitialDelay();
          return;
        }

        const renewButton = event.target.closest(renewButtonSelector);

        if (renewButton) {
          event.preventDefault();
          cancelInitialDelay();
          toggleOverlay();
        }
      });
    },
  };
})(Drupal);