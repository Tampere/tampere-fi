(function (Drupal, once) {
  'use strict';

  /**
   * Handles the inline accept/discard flow for the AI alt text preview.
   *
   * "Use this alt text" copies the AI text into the alt input of the image
   * field the preview belongs to. "Discard" hides the preview without
   * changing the input.
   *
   * The preview element carries a data-alt-target attribute with the name of
   * the alt input to fill, so the same code works on the media edit form and
   * on every delta of the media library add dialog.
   *
   * Event delegation is attached to document.body once so it covers both the
   * initial page load and elements revealed after the AJAX generate call.
   */
  Drupal.behaviors.treAiAltText = {
    attach: function (context) {
      once('ai-alt-text', 'body').forEach(function (body) {
        body.addEventListener('click', function (e) {
          var target = e.target;

          if (target.classList.contains('ai-alt-text-accept')) {
            e.preventDefault();
            var preview = target.closest('.ai-alt-text-preview');
            var textEl = preview ? preview.querySelector('.ai-alt-text-text') : null;
            if (textEl && preview) {
              var altInput = document.querySelector('[name="' + preview.getAttribute('data-alt-target') + '"]');
              if (altInput) {
                altInput.value = textEl.textContent.trim();
              }
            }
            if (preview) {
              preview.style.display = 'none';
            }
          }
          else if (target.classList.contains('ai-alt-text-discard')) {
            e.preventDefault();
            var preview = target.closest('.ai-alt-text-preview');
            if (preview) {
              preview.style.display = 'none';
            }
          }
        });
      });
    }
  };

}(Drupal, once));
