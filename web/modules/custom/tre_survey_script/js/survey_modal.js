(function (Drupal) {
  "use strict";

  Drupal.behaviors.treSurveyModal = {
    attach(context) {
      const modal = document.querySelector("#survey-modal");

      if (!modal || modal.dataset.treSurveyModalInitialized) {
        return;
      }

      let keyupHandler;
      let lastFocusedElement;

      // Close triggers -> X button and background overlay.
      const closeTriggers = modal.querySelectorAll("[data-survey-close]");

      // Closes the popup modal window.
      const closeModal = () => {
        modal.classList.remove("is-visible");

        if (keyupHandler) {
          document.removeEventListener("keyup", keyupHandler);
          keyupHandler = null;
        }

        // Move focus back to the element that was
        // focused before modal opened.
        if (lastFocusedElement) {
          lastFocusedElement.focus();
        }

        sessionStorage.setItem("modalClosed", "true");
      };

      // Function for making the survey popup modal visible
      const openModal = () => {
        if (modal.classList.contains("is-visible")) {
          return;
        }

        if (sessionStorage.getItem("modalClosed") === "true") {
          return;
        }

        // Save the last focused element and make the popup visible.
        lastFocusedElement = document.activeElement;
        modal.classList.add("is-visible");

        // Get the focusable elements within the modal popup.
        const closeButton = modal.querySelector(".survey-modal-close");
        const surveyLink = modal.querySelector(".survey-modal-link a");

        // Set initial focus to the close button.
        if (closeButton) {
          closeButton.focus();
        }

        // Handle tab based navigation.
        modal.addEventListener("keydown", (event) => {
          if (event.key === "Tab") {
            if (event.shiftKey) {
              // Shift + tab focus.
              if (document.activeElement === closeButton) {
                event.preventDefault();
                surveyLink.focus();
              }
            } else {
              // Tab focus
              if (document.activeElement === surveyLink) {
                event.preventDefault();
                closeButton.focus();
              }
            }
          }
        });

        keyupHandler = (event) => {
          if (event.key === "Escape") {
            closeModal();
          }
        };

        document.addEventListener("keyup", keyupHandler);
      };

      // Wait 8 seconds before bringing up the modal popup.
      setTimeout(openModal, 8000);

      // Close the popup when user clicks the X or background overlay.
      closeTriggers.forEach((trigger) => {
        trigger.addEventListener("click", (event) => {
          event.preventDefault();
          closeModal();
        });
      });

      modal.dataset.treSurveyModalInitialized = "true";
    },
  };
})(Drupal);
