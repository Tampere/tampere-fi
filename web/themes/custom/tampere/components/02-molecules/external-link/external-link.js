/**
 * @file
 * Append external link icon to external links inside WYSIWYG fields.
 *
 */

/**
 * Strip "www." from the beginning of the string if it exists.
 */
function getHostNameWithoutWww(hostname) {
  return hostname.slice(-4) === 'www.' ? hostname.slice(5) : hostname;
}

/**
 * Exract hostname from an href text.
 *
 * @param {string} hrefText
 *  href property text from which hostname will attempted to be extracted.
 * @param {string} defaultHostname
 *  A hostname to be used if no hostname can be extracted from url.
 * @returns hostname
 */
function getLinkHostname(hrefText, defaultHostname) {
  // Note: using regular expression might be faster but error prone.
  // See: https://stackoverflow.com/a/54947757
  // Adapting regular expressions from extlink contrib module could be one option.
  // See: https://git.drupalcode.org/project/extlink/-/blob/8.x-1.x/extlink.js#L256

  let linkHostname;
  try {
    linkHostname = new URL(hrefText).hostname;
  } catch (error) {
    // Hostname is not provided, thus link url is relational.
    linkHostname = defaultHostname;
  }
  if (linkHostname === '') {
    // There is no hostname to extract. This can happen if the
    // the url starts with for example "tel:" or "mailto:".
    // Thus the url should be considered to be internal.
    return defaultHostname;
  }
  linkHostname = getHostNameWithoutWww(linkHostname);
  return linkHostname;
}

/**
 * Append icon to dom elememnt.
 * @param {*} anchorElement
 *  An element to which the icon should be appended to.
 */
function appendIconToElement(anchorElement) {
  if ('content' in document.createElement('template')) {
    const externalLinkIndicatorTemplate = document.querySelector(
      '#external-link-indicator-template'
    );

    if (!externalLinkIndicatorTemplate) {
      return;
    }

    const externalLinkIndicatorClone = externalLinkIndicatorTemplate
      .content.firstElementChild.cloneNode(true);

    externalLinkIndicatorClone.innerHTML = externalLinkIndicatorClone.innerHTML.trim();

    anchorElement.appendChild(externalLinkIndicatorClone);
  }
}

Drupal.behaviors.externalLinkContainer = {
  attach(context) {
    // eslint-disable-next-line no-undef

    // Get all a elements within the given context that have not yet been processed.
    // eslint-disable-next-line no-undef
    const anchors = once(
      'external-link-processed',
      document.querySelectorAll('.text-long a, .cta__content a, .field a', context)
    );

    // eslint-disable-next-line no-restricted-globals
    const siteHostname = getHostNameWithoutWww(location.hostname);

    for (let i = 0; i < anchors.length; i += 1) {
      const linkHostname = getLinkHostname(anchors[i].href, siteHostname);
      if (linkHostname !== siteHostname) {
        // This is external link, add external icon.
        appendIconToElement(anchors[i]);
      }
    }
  },
};
