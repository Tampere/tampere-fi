/**
 * @file
 * A JavaScript file containing functionality for the anchor links.
 *
 */

/**
 * Replaces Scandinavian characters with characters in the range of a-z.
 *
 * @param {string} string
 *   The original string.
 */
function deScandify(string) {
  let modifiedString = string.toLowerCase();

  modifiedString = modifiedString
    .replace(/[äå]/g, 'a')
    .replace(/[öø]/g, 'o')
    .replace(/[æ]/g, 'ae');

  return modifiedString;
}

Drupal.behaviors.wysiwygHeadingAnchors = {
  attach(context) {
    let anchorIconTemplate;
    let headingIdCounter = 1;

    const headings = once(
      'wysiwyg-heading-anchors',
      document.querySelectorAll(
        '.text-long h2, .text-long h3, .text-long h4, .text-long h5',
        context
      )
    );

    if ('content' in document.createElement('template')) {
      anchorIconTemplate = document.querySelector(
        '#wysiwyg-field-anchor-link-template',
        context
      );
    }

    if (headings) {
      headings.forEach((heading) => {
        const headingPlainTextContent = heading.textContent.trim();
        let headingString = deScandify(headingPlainTextContent).replace(
          /\W+/g,
          '-'
        );

        // Create a new heading string if heading string
        // is just a dash or it won't be unique id.
        if (
          headingString === '-' ||
          document.getElementById(headingString) !== null
        ) {
          do {
            headingString = `heading-${headingIdCounter}`;
            headingIdCounter += 1;
          } while (document.getElementById(headingString) !== null);
        }

        heading.setAttribute('id', headingString);

        if (anchorIconTemplate) {
          const anchorIconClone = anchorIconTemplate.content.firstElementChild.cloneNode(
            true
          );

          anchorIconClone.href = `#${headingString}`;

          heading.insertBefore(anchorIconClone, heading.firstChild);
        }
      });
    }
  },
};

Drupal.behaviors.h2HeadingAnchors = {
  attach(context) {
    const headings = once(
      'page-heading-anchors',
      document.querySelectorAll(
        '.main-content h2:not(.in-page-menu__heading, .visually-hidden)',
        context
      )
    );

    if (headings) {
      headings.forEach((heading) => {
        if (typeof heading.id === 'undefined' || heading.id === '') {
          const headingPlainTextContent = heading.textContent.trim();
          const headingString = deScandify(headingPlainTextContent).replace(
            /\W+/g,
            '-'
          );

          heading.setAttribute('id', headingString);
        }
      });
    }
  },
};
