/**
 * @file
 * A JavaScript file containing functionality for the automatic anchor list.
 *
 */

Drupal.behaviors.automaticAnchorList = {
  attach(context) {
    let anchorListItemTemplate;

    const headings = once.filter(
      'page-heading-anchors',
      document.querySelectorAll('h2')
    );

    const automaticInPageMenuLists = once(
      'in-page-menu-list',
      document.getElementsByClassName('in-page-menu__list--automatic')
    );

    if ('content' in document.createElement('template')) {
      anchorListItemTemplate = document.querySelector(
        '#automatic-anchor-list-template',
        context
      );
    }

    if (headings) {
      headings.forEach((heading) => {
        const headingPlainTextContent = heading.textContent.trim();

        if (anchorListItemTemplate) {
          const anchorListItemClone = anchorListItemTemplate.content.firstElementChild.cloneNode(
            true
          );

          const link = anchorListItemClone.querySelector('a');
          link.href = `#${heading.id}`;
          link.append(headingPlainTextContent);

          if (automaticInPageMenuLists.length > 0) {
            automaticInPageMenuLists.forEach((menuList) => {
              const langParent = heading.closest('[lang]');
              const langCode = langParent.getAttribute('lang');
              const listItemClone = anchorListItemClone.cloneNode(true);
              if (langParent.getAttribute('dir')) {
                listItemClone.setAttribute(
                  'dir',
                  langParent.getAttribute('dir')
                );
              }
              listItemClone.setAttribute('lang', langCode);
              menuList.appendChild(listItemClone);
            });
          }
        }
      });
    }
  },
};
