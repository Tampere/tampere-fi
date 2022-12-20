/**
 * @file
 * A JavaScript file containing the sidebar menu functionality.
 *
 */

/**
 * Handles interaction with the expand toggle button.
 *
 * @param {Event} event
 *   The event on the toggle button.
 */
function handleExpandToggleInteraction(event) {
  const menuToggle = event.currentTarget;
  const isAriaExpanded = menuToggle.getAttribute('aria-expanded') === 'true';

  let ariaLabel;

  try {
    ariaLabel = !isAriaExpanded
      ? Drupal.t('Close submenu')
      : Drupal.t('Open submenu');
  } catch (e) {
    ariaLabel = !isAriaExpanded ? 'Close submenu' : 'Open submenu';
  }

  menuToggle.classList.toggle('is-closed');
  menuToggle.setAttribute('aria-expanded', !isAriaExpanded ? 'true' : 'false');
  menuToggle.setAttribute('aria-label', ariaLabel);
}

Drupal.behaviors.sidebarMenu = {
  attach(context) {
    let activeLinksWithSubs;
    let sidebarMenu;
    let submenuToggles;

    // Once doesn't work in Storybook currently.
    try {
      sidebarMenu = once('sidebar-menu', '.sidebar-menu', context).shift(); // eslint-disable-line
    } catch (e) {
      sidebarMenu = document.querySelector('.sidebar-menu');
    }

    if (sidebarMenu) {
      try {
        activeLinksWithSubs = once('sidebar-menu-active-links', '.sidebar-menu__link--active.sidebar-menu__link--with-sub', context); // eslint-disable-line
      } catch (e) {
        activeLinksWithSubs = document.querySelectorAll(
          '.sidebar-menu__link--active.sidebar-menu__link--with-sub'
        );
      }

      activeLinksWithSubs.forEach((activeLink) => {
        const activeLinkSubToggle = activeLink.nextElementSibling;

        activeLinkSubToggle.classList.remove('is-closed');
        activeLinkSubToggle.setAttribute('aria-expanded', 'true');
        activeLinkSubToggle.setAttribute('aria-label', 'Close submenu');
      });

      try {
        submenuToggles = once('sidebar-menu-submenu-toggles', '.sidebar-menu__toggle', sidebarMenu); // eslint-disable-line
      } catch (e) {
        submenuToggles = sidebarMenu.querySelectorAll('.sidebar-menu__toggle');
      }

      submenuToggles.forEach((toggle) => {
        toggle.addEventListener('click', handleExpandToggleInteraction);
        toggle.addEventListener('tap', handleExpandToggleInteraction);
      });
    }
  },
};
