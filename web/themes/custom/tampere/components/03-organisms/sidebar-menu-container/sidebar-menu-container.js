/**
 * @file
 * A JavaScript file containing the sidebar menu container functionality.
 */

(function (Drupal) {
  'use strict';

  /**
   * Handles interaction with the container toggle button.
   *
   * @param {Event} event
   *   The event on the toggle button.
   */
  function handleContainerToggleInteraction(event) {
    const closedClass = 'is-closed-on-mobile';
    const containerToggle = event.currentTarget;
    const isAriaExpanded =
      containerToggle.getAttribute('aria-expanded') === 'true';
    const controlledContainerId = containerToggle.getAttribute('aria-controls');
    const container = document.getElementById(controlledContainerId);

    let ariaLabel;

    try {
      ariaLabel = !isAriaExpanded
        ? Drupal.t('Hide subsite navigation')
        : Drupal.t('Show subsite navigation');
    } catch (e) {
      ariaLabel = !isAriaExpanded
        ? 'Hide subsite navigation'
        : 'Show subsite navigation';
    }

    // Expand & collapse texts from data-js attribute
    const expandedText = containerToggle.dataset.jsExpandedText;
    const collapsedText = containerToggle.dataset.jsCollapsedText;
    const containerToggleText = containerToggle.querySelector('.button__text');

    // Set button text depending on state
    if (containerToggleText && expandedText && collapsedText) {
      const buttonText = !isAriaExpanded ? expandedText : collapsedText;
      containerToggleText.innerText = buttonText;
    }

    containerToggle.classList.toggle(closedClass);
    containerToggle.setAttribute(
      'aria-expanded',
      !isAriaExpanded ? 'true' : 'false'
    );
    containerToggle.setAttribute('aria-label', ariaLabel);

    if (!isAriaExpanded) {
      container.classList.remove(closedClass);
    } else {
      container.classList.add(closedClass);
    }
  }

  Drupal.behaviors.sidebarMenuContainerContainer = {
    attach(context) {
      let sidebarMenuContainer;
      let containerToggle;

      // Once doesn't work in Storybook currently.
      try {
        sidebarMenuContainer = once('sidebar-menu-container', '.sidebar-menu-container', context).shift(); // eslint-disable-line
      } catch (e) {
        sidebarMenuContainer = document.querySelector(
          '.sidebar-menu-container'
        );
      }

      if (sidebarMenuContainer) {
        try {
          containerToggle = once('sidebar-menu-container-toggle', '.sidebar-menu-container__toggle', sidebarMenuContainer).shift(); // eslint-disable-line
        } catch (e) {
          containerToggle = sidebarMenuContainer.querySelector(
            '.sidebar-menu-container__toggle'
          );
        }

        containerToggle.addEventListener(
          'click',
          handleContainerToggleInteraction
        );
        containerToggle.addEventListener(
          'tap',
          handleContainerToggleInteraction
        );
      }
    },
  };
})(Drupal);
