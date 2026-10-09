(function (Drupal, once) {
  /* eslint-disable no-param-reassign */
  Drupal.behaviors.menuNewMenu = {
    attach(context) {
      once('custom-menu', '.custom-menu', context).forEach((menu) => {
        const header = menu.querySelector('.menu-drilldown-header');
        const backButton = menu.querySelector('.menu-current-title');
        const backButtonText = backButton.querySelector('.menu-current-title__text');
        const returnToMainMenuText = header.dataset.returnMainText;

        // Store navigation history
        const history = [];
        // Save the current menu state
        const storageKey = 'customMenuDrilldownPath';
        let isRestoring = false;

        function setBackButtonText(text) {
          if (backButtonText) {
            backButtonText.textContent = text;
          }
        }

        function saveMenuState() {
          if (isRestoring) return;

          const path = history.map((entry) => entry.title);
          sessionStorage.setItem(storageKey, JSON.stringify(path));
        }

        // Save menu state for menu links that are pressed outside menu, for example page links
        function restoreMenuFromCurrentUrl() {
          const currentPath = window.location.pathname;
          const activeLink = Array.from(menu.querySelectorAll('.menu-link')).find((link) => (
            link.pathname.replace(/\/$/, '') === currentPath.replace(/\/$/, '')
          ));

          if (!activeLink) return;

          const parentItems = [];

          let item = activeLink.closest('.menu-item');

          while (item) {
            const parentList = item.parentElement;
            const parentItem = parentList?.closest('.menu-item');

            if (parentItem) {
              parentItems.unshift(parentItem);
            }

            item = parentItem;
          }

          isRestoring = true;

          parentItems.forEach((parentItem) => {
            const button = parentItem.querySelector(':scope > .menu-item__row > .submenu-toggle');

            if (button && !button.hidden) {
              button.click();
            }
          });

          activeLink.closest('.menu-item')?.classList.add('menu-item--active-trail');

          isRestoring = false;
        }

        // Submenu toggle handler
        menu.querySelectorAll('.submenu-toggle').forEach((button) => {
          button.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            const item = button.closest('.menu-item');
            const row = item.querySelector(':scope > .menu-item__row');
            const submenu = item.querySelector(':scope > .submenu');
            const link = row.querySelector('.menu-link');
            const currentList = item.parentElement;
            const parentItem = currentList.closest('.menu-item');

            if (!submenu || !row || !link) return;

            history.push({
              title: link.textContent.trim(),
              list: currentList,
              item,
              row,
              submenu,
              button,
              parentItem,
            });

            /**
             * Hide all sibling items in the CURRENT level.
             * This creates the drilldown screen effect.
             */
            Array.from(currentList.children).forEach((sibling) => {
              sibling.hidden = sibling !== item;
            });

            /**
             * If we are inside a deeper level,
             * hide only the previous parent ROW,
             * not the whole parent LI.
             */
            if (parentItem) {
              const parentRow = parentItem.querySelector(':scope > .menu-item__row');

              if (parentRow) {
                parentRow.hidden = true;
              }
            }

            // Show current parent row.
            item.hidden = false;

            menu.querySelectorAll('.menu-item--current-parent').forEach((oldItem) => {
              oldItem.classList.remove('menu-item--current-parent');
            });

            item.classList.add('menu-item--current-parent');

            row.hidden = false;

            // Hide current arrow button.
            button.hidden = true;
            button.setAttribute('aria-expanded', 'true');

            // Show current submenu.
            submenu.hidden = false;

            // Show direct children only, but keep grandchildren closed.
            Array.from(submenu.children).forEach((child) => {
              child.hidden = false;

              const childSubmenu = child.querySelector(':scope > .submenu');
              const childButton = child.querySelector(':scope > .menu-item__row > .submenu-toggle');

              if (childSubmenu) {
                childSubmenu.hidden = true;
              }

              if (childButton) {
                childButton.hidden = false;
                childButton.setAttribute('aria-expanded', 'false');
              }
            });

            // Header.
            header.hidden = false;

            if (history.length === 1) {
              setBackButtonText(returnToMainMenuText);
            } else {
              const previous = history[history.length - 2];
              setBackButtonText(previous.title);
            }

            saveMenuState();
          });
        });

        //  Back button logic
        backButton.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();

          const current = history.pop();
          if (!current) return;
          current.item.classList.remove('menu-item--current-parent');

          current.submenu.hidden = true;
          current.row.hidden = false;
          current.button.hidden = false;
          current.button.setAttribute('aria-expanded', 'false');

          Array.from(current.list.children).forEach((item) => {
            item.hidden = false;
          });

          // hide only current submenu
          current.submenu.hidden = true;

          // show parent row again
          current.row.hidden = false;

          // Show back again, on back
          current.button.hidden = false;
          current.button.setAttribute('aria-expanded', 'false');

          // show items from previous level
          current.list.querySelectorAll(':scope > .menu-item').forEach((item) => {
            item.hidden = false;
          });

          // Show parent row again.
          if (current.parentItem) {
            const parentRow = current.parentItem.querySelector(':scope > .menu-item__row');

            if (parentRow) {
              parentRow.hidden = false;
            }
          }

          saveMenuState();

          // update title to previous parent
          if (history.length === 0) {
            header.hidden = true;
            setBackButtonText('');
            sessionStorage.removeItem(storageKey);
          } else if (history.length === 1) {
            setBackButtonText(returnToMainMenuText);
          } else {
            const previous = history[history.length - 2];
            setBackButtonText(previous.title);
          }

          if (history.length > 0) {
            const previous = history[history.length - 1];
            previous.item.classList.add('menu-item--current-parent');
          }
        });

        // Restore logic
        function restoreMenuState() {
          const saved = sessionStorage.getItem(storageKey);

          if (!saved) return;

          let path;

          try {
            path = JSON.parse(saved);
          } catch (e) {
            return;
          }

          if (!Array.isArray(path) || path.length === 0) return;

          isRestoring = true;

          path.forEach((title) => {
            const buttons = Array.from(menu.querySelectorAll('.submenu-toggle'));

            const matchingButton = buttons.find((button) => {
              const item = button.closest('.menu-item');
              const row = item?.querySelector(':scope > .menu-item__row');
              const link = row?.querySelector('.menu-link');

              return link?.textContent.trim() === title;
            });

            if (matchingButton && !matchingButton.hidden) {
              matchingButton.click();
            }
          });

          isRestoring = false;
        }

        // Mobile menu accordion open on active link
        function openAccordionWithActiveLink() {
          document.querySelectorAll('.accordion__heading').forEach((button) => {
            const content = document.getElementById(button.getAttribute('aria-controls'));

            if (!content) {
              return;
            }

            const hasActiveLink = content.querySelector(
              '.menu-link.is-active, .menu-item--active-trail',
            );

            if (!hasActiveLink) {
              return;
            }

            if (button.getAttribute('aria-expanded') !== 'true') {
              button.click();
            }

            content.setAttribute('aria-hidden', 'false');
          });
        }

        // Open accordion after 100 ms
        setTimeout(openAccordionWithActiveLink, 100);

        // Restore from url, otherwise restore from menu navigation
        if (!restoreMenuFromCurrentUrl()) {
          restoreMenuState();
        }
      });
    },
  };
}(Drupal, once));
