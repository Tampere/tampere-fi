import React from 'react';

import sidebarMenuContainerComponent from './sidebar-menu-container.twig';

import sidebarMenuData from '../../02-molecules/menus/sidebar-menu/sidebar-menu.yml';
import sidebarMenuContainerMinisiteData from './sidebar-menu-container-minisite.yml';
import sidebarMenuContainerData from './sidebar-menu-container.yml';

import './sidebar-menu-container';

/**
 * Storybook Definition.
 */
export default { title: 'Organisms/Sidebar Menu Container' };

export const sidebarMenuContainer = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: sidebarMenuContainerComponent({
        ...sidebarMenuData,
        ...sidebarMenuContainerData,
      }),
    }}
  />
);

export const sidebarMenuContainerMinisite = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: sidebarMenuContainerComponent({
        ...sidebarMenuData,
        ...sidebarMenuContainerMinisiteData,
      }),
    }}
  />
);
