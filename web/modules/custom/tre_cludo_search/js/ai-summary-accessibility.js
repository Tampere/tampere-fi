(function (Drupal) {
  Drupal.behaviors.cludoAiSummaryAccessibility = {
    attach: function (context) {
      if (context !== document) {
        return;
      }

      var COLLAPSED_FLAG = "data-tre-collapsed";
      var TOGGLE_SELECTOR = ".cludo_expandable-container > button[aria-expanded]";
      var openedWithKeyboard = false;

      // A click without pointer coordinates comes from Enter or Space.
      document.addEventListener(
        "click",
        function (event) {
          var toggle = event.target.closest && event.target.closest(TOGGLE_SELECTOR);
          openedWithKeyboard = !!toggle && event.detail === 0;
        },
        true
      );

      // Content clipped by the collapsed summary must not be reachable with
      // the keyboard, otherwise focus lands on invisible links. Returns the
      // elements made reachable again.
      function setContentFocusable(content, focusable) {
        var restored = [];
        content
          .querySelectorAll("a[href], button, [tabindex]")
          .forEach(function (element) {
            if (!focusable && !element.hasAttribute("tabindex")) {
              element.setAttribute("tabindex", "-1");
              element.setAttribute(COLLAPSED_FLAG, "");
            } else if (focusable && element.hasAttribute(COLLAPSED_FLAG)) {
              element.removeAttribute("tabindex");
              element.removeAttribute(COLLAPSED_FLAG);
              restored.push(element);
            }
          });
        return restored;
      }

      function update() {
        document.querySelectorAll(".cludo-ai-summary").forEach(function (summary) {
          var wrapper = summary.closest("[data-read-aloud-label]");

          var aiLabel = wrapper && wrapper.dataset.aiIconLabel;
          summary
            .querySelectorAll(".cludo-generative-ai__icon-container .cludo-icon")
            .forEach(function (icon) {
              if (aiLabel && icon.getAttribute("aria-label") !== aiLabel) {
                icon.setAttribute("role", "img");
                icon.setAttribute("aria-label", aiLabel);
              }
            });

          // Cludo only ships English labels and swaps them to Pause/Resume
          // while playing, so keep a constant label and let aria-pressed
          // carry the state.
          var label = wrapper && wrapper.dataset.readAloudLabel;
          var readAloud = summary.querySelector(
            ".cludo-ai-summary-read-aloud__button"
          );
          if (label && readAloud && readAloud.getAttribute("aria-label") !== label) {
            readAloud.setAttribute("aria-label", label);
            readAloud.setAttribute("title", label);
          }

          var toggle = summary.querySelector(TOGGLE_SELECTOR);
          if (wrapper) {
            // Cludo only ships English names for the icon-only feedback buttons.
            [
              [".cludo-ai-summary-feedback__positive-button", wrapper.dataset.feedbackYesLabel],
              [".cludo-ai-summary-feedback__negative-button", wrapper.dataset.feedbackNoLabel],
            ].forEach(function (pair) {
              var button = summary.querySelector(pair[0]);
              if (button && pair[1] && button.getAttribute("aria-label") !== pair[1]) {
                button.setAttribute("aria-label", pair[1]);
              }
            });
          }
          if (wrapper && toggle) {
            // The visible label is Avaa/Sulje, so the accessible name must
            // contain it instead of Cludo's English default.
            var expanded = toggle.getAttribute("aria-expanded") === "true";
            var toggleLabel = expanded
              ? wrapper.dataset.closeLabel
              : wrapper.dataset.openLabel;
            if (toggleLabel && toggle.getAttribute("aria-label") !== toggleLabel) {
              toggle.setAttribute("aria-label", toggleLabel);
            }
          }
          var content = summary.querySelector(".cludo_expandable-content");
          if (content) {
            var restored = setContentFocusable(
              content,
              !toggle || toggle.getAttribute("aria-expanded") === "true"
            );
            // The toggle comes after the content, so move focus into the
            // opened content to keep Avaa, content, Sulje in tab order.
            if (restored.length && openedWithKeyboard) {
              openedWithKeyboard = false;
              restored[0].focus();
            }
          }
        });
      }

      new MutationObserver(update).observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ["aria-label", "aria-expanded"],
      });
      update();
    },
  };
})(Drupal);
