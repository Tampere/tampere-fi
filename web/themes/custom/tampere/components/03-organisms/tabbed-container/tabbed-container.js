/**
 * @file
 * A JavaScript file containing the tabbed container component's tabbing functionality.
 *
 * @see https://inclusive-components.design/tabbed-interfaces/
 *
 */

/**
 * Functionality for switching a tab.
 *
 * @param {HTMLElement} oldTab
 *   The tab that is active before switching.
 * @param {HTMLElement} newTab
 *   The tab that should be active after switching.
 * @param {HTMLElement} allTabs
 *   All of the available tabs.
 * @param {HTMLElement} allPanels
 *   All of the available panels.
 */
function switchTab(oldTab, newTab, allTabs, allPanels) {
  newTab.focus();
  // Make the active tab focusable by the user (Tab key).
  newTab.removeAttribute('tabindex');
  // Set the selected state.
  newTab.setAttribute('aria-selected', 'true');
  newTab.classList.add('is-selected');
  oldTab.removeAttribute('aria-selected');
  oldTab.classList.remove('is-selected');
  oldTab.setAttribute('tabindex', '-1');

  // Get the indices of the new and old tabs to find the correct
  // tab panels to show and hide.
  const index = Array.prototype.indexOf.call(allTabs, newTab);
  const oldIndex = Array.prototype.indexOf.call(allTabs, oldTab);

  allPanels[oldIndex].hidden = true; // eslint-disable-line no-param-reassign
  allPanels[index].hidden = false; // eslint-disable-line no-param-reassign
}

Drupal.behaviors.tabbedContainer = {
  attach(context) {
    const tabbedContainers = document.querySelectorAll(
      '.tabbed-container',
      context,
    );

    if (tabbedContainers) {
      tabbedContainers.forEach((tabbedContainer) => {
        let tabs;

        const tablist = tabbedContainer.querySelector(
          '.tabbed-container__tab-list',
          context,
        );

        if (tablist) {
          tabs = tablist.querySelectorAll('.tabbed-container__tab', context);
        }

        const panels = tabbedContainer.querySelectorAll(
          '[id^="tabbed-container-tab-section-"]',
          context,
        );

        if (tabs) {
          Array.prototype.forEach.call(tabs, (tab) => {
            let currentTab;
            let dir;
            let index;

            // Handle tab clicking for mouse users.
            tab.addEventListener('click', (e) => {
              e.preventDefault();
              currentTab = tablist.querySelector('[aria-selected]', context);
              if (e.currentTarget !== currentTab) {
                switchTab(currentTab, e.currentTarget, tabs, panels);
              }
            });

            // Handle keydown events for keyboard users.
            tab.addEventListener('keydown', (e) => {
              // Get the index of the current tab in the tabs node list.
              index = Array.prototype.indexOf.call(tabs, e.currentTarget);
              // Work out which key the user is pressing and
              // calculate the new tab's index where appropriate.
              dir = e.which === 37 ? index - 1 : e.which === 39 ? index + 1 : e.which === 40 ? 'down' : null; // eslint-disable-line
              if (dir !== null) {
                e.preventDefault();
                // Switch to the adjacent tab.
                tabs[dir] ? switchTab(e.currentTarget, tabs[dir], tabs, panels) : void 0; // eslint-disable-line
              }
            });
          });
        }
      });
    }
  },
};
