/**
 * @file
 * A JavaScript file containing the 'React & Share' initialization.
 *
 */
(function (Drupal, drupalSettings) {

  Drupal.behaviors.reactAndShareInit = {
    attach: function (context) {
      const apiKey = drupalSettings.tampere.react_and_share.key;

      // The API key is not revealed on the front-end side by accident.
      // See https://docs.reactandshare.com/#embed-code-install
      window.askem = {
        settings: {
          apiKey: apiKey
        }
      };

      var s = document.createElement('script');
      s.src = 'https://cdn.askem.com/plugin/askem.js';

      document.body.appendChild(s);
    }
  }

})(Drupal, drupalSettings);
