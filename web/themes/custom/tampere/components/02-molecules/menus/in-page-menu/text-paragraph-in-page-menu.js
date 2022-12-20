/**
 * @file
 * A JavaScript file containing functionality for the text
 * paragraph specific automatic anchor list.
 *
 */

/**
 * Returns a list item for the given subheading.
 *
 * @param {HTMLElement} subheading
 * @returns {undefined|HTMLElement}
 *  Clone of the template fragment containing subheading
 *  information if succesful. Otherwise undefined.
 */
function getSubheadingListItem(subheading) {
  if (!('content' in document.createElement('template'))) {
    return undefined;
  }

  const listItemTemplate = document.querySelector(
    '#automatic-anchor-list-template'
  );
  const clone = listItemTemplate.content.firstElementChild.cloneNode(true);
  const link = clone.querySelector('a');

  link.href = `#${subheading.id}`;
  link.append(subheading.textContent);

  return clone;
}

Drupal.behaviors.textParagraphAutomaticAnchorList = {
  attach(context) {
    const inPageMenus = once(
      'text-paragraph-automatic-anchor-lists',
      '.in-page-menu--text-paragraph-specific',
      context
    );

    if (!inPageMenus || inPageMenus.length === 0) {
      return;
    }

    inPageMenus.forEach((inPageMenu) => {
      const inPageMenuList = inPageMenu.querySelector('ul');
      const paragraph = inPageMenu.closest('.paragraph');
      const subheadings = once.find('wysiwyg-heading-anchors', paragraph);

      if (subheadings.length === 0) {
        return;
      }

      subheadings.forEach((subheading) => {
        const listItem = getSubheadingListItem(subheading);

        if (listItem) {
          inPageMenuList.appendChild(listItem);
        }
      });
    });
  },
};
