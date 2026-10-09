(function (Drupal, once) {
  'use strict';

  /**
   * Handles the inline accept/discard flow for the AI page summary preview.
   *
   * "Use this summary" copies the AI text into the field_page_summary textarea.
   * "Discard" hides the preview without changing the textarea.
   *
   * Event delegation is attached to document.body once so it covers both the
   * initial page load and elements revealed after the AJAX generate call.
   */
  Drupal.behaviors.treAiPageSummary = {
    attach: function (context) {
      once('ai-page-summary', 'body').forEach(function (body) {
        body.addEventListener('click', function (e) {
          var target = e.target;

          if (target.classList.contains('ai-page-summary-accept')) {
            e.preventDefault();
            var preview = document.getElementById('ai-page-summary-preview');
            var textEl = preview ? preview.querySelector('.ai-page-summary-text') : null;
            if (textEl) {
              var textarea = document.querySelector('[name="field_page_summary[0][value]"]');
              if (textarea) {
                textarea.value = textEl.textContent.trim();
              }
            }
            if (preview) {
              preview.style.display = 'none';
            }
          }
          else if (target.classList.contains('ai-page-summary-discard')) {
            e.preventDefault();
            var preview = document.getElementById('ai-page-summary-preview');
            if (preview) {
              preview.style.display = 'none';
            }
          }
        });
      });
    }
  };

}(Drupal, once));
