/**
 * @file
 * A JavaScript file containing the 'React & Share' initialization.
 *
 */
(function (Drupal, drupalSettings) {

  Drupal.behaviors.reactAndShareInit = {
    attach: function (context) {
      const apiKey = drupalSettings.tampere.react_and_share.key;
      const categories = drupalSettings.tampere.react_and_share.categories;

      const matchedCategory = categories.find(category => {
        // Remove trailing forward slash.
        category = category.replace(/\/$/, '');
        // Match category either at the end of the pathname or when it's
        // followed by a forward slash.
        // E.g. '/category' or '/category/other-word'.
        const regex = new RegExp(`^(${category}/+|${category}$)`);

        return regex.test(window.location.pathname);
      });

      // The API key is not revealed on the front-end side by accident.
      // See https://docs.reactandshare.com/#embed-code-install
      window.rnsData = {
        apiKey: apiKey
      };

      if (matchedCategory) {
        window.rnsData.categories = [ matchedCategory ];
      }

      var s = document.createElement('script');
      s.src = 'https://cdn.reactandshare.com/plugin/rns.js';

      document.body.appendChild(s);
    }
  }

})(Drupal, drupalSettings);
