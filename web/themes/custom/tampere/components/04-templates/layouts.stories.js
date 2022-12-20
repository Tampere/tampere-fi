import React from 'react';

import fullWidthTwig from './full-width.twig';
import withSidebarTwig from './with-sidebar.twig';

import mainMenu from '../02-molecules/menus/main-menu/main-menu.yml';
import footerMenu from '../02-molecules/menus/inline-menu/inline-menu.yml';

/**
 * Storybook Definition.
 */
export default {
  title: 'Templates/Layouts',
  parameters: {
    layout: 'fullscreen',
  },
};

export const fullWidth = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: fullWidthTwig({ ...mainMenu, ...footerMenu }),
    }}
  />
);
export const withSidebar = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: withSidebarTwig({ ...mainMenu, ...footerMenu }),
    }}
  />
);
