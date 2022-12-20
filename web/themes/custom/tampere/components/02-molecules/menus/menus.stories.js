import React from 'react';

import breadcrumb from './breadcrumbs/breadcrumbs.twig';
import footerMenu from './footer/footer-menu.twig';
import inlineMenu from './inline-menu/inline-menu.twig';
import mainMenu from './main-menu/main-menu.twig';
import sidebarMenu from './sidebar-menu/sidebar-menu.twig';
import inpageMenu from './in-page-menu/in-page-menu.twig';

import breadcrumbsData from './breadcrumbs/breadcrumbs.yml';
import footerMenuData from './footer/footer-menu.yml';
import inlineMenuData from './inline-menu/inline-menu.yml';
import footerInlineMenuData from './inline-menu/footer-inline-menu.yml';
import mainInlineMenuData from './inline-menu/main-inline-menu.yml';
import mainSecondaryInlineMenuData from './inline-menu/main-secondary-inline-menu.yml';
import mainInvertedInlineMenuData from './inline-menu/main-inverted-inline-menu.yml';
import mainMenuSecondaryData from './main-menu/main-menu-secondary.yml';
import mainMenuMinisiteData from './main-menu/main-menu-minisite.yml';
import mainMenuData from './main-menu/main-menu.yml';
import sidebarMenuMinisiteData from './sidebar-menu/sidebar-menu-minisite.yml';
import sidebarMenuData from './sidebar-menu/sidebar-menu.yml';
import inpageMenuData from './in-page-menu/in-page-menu.yml';
import inpageMenuWithHeadingData from './in-page-menu/in-page-menu-with-heading.yml';

import './sidebar-menu/sidebar-menu';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Menus' };

export const breadcrumbs = () => (
  <div dangerouslySetInnerHTML={{ __html: breadcrumb(breadcrumbsData) }} />
);

export const footer = () => (
  <div dangerouslySetInnerHTML={{ __html: footerMenu(footerMenuData) }} />
);

export const inline = () => (
  <div dangerouslySetInnerHTML={{ __html: inlineMenu(inlineMenuData) }} />
);

export const footerInline = () => (
  <div dangerouslySetInnerHTML={{ __html: inlineMenu(footerInlineMenuData) }} />
);

export const mainInline = () => (
  <div dangerouslySetInnerHTML={{ __html: inlineMenu(mainInlineMenuData) }} />
);

export const mainSecondaryInline = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: inlineMenu(mainSecondaryInlineMenuData),
    }}
  />
);

export const mainInvertedInline = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: inlineMenu({ ...mainInlineMenuData, ...mainInvertedInlineMenuData }),
    }}
  />
);

export const main = () => (
  <div dangerouslySetInnerHTML={{ __html: mainMenu(mainMenuData) }} />
);

export const mainSecondary = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: mainMenu({ ...mainMenuData, ...mainMenuSecondaryData }),
    }}
  />
);

export const mainMinisite = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: mainMenu(mainMenuMinisiteData),
    }}
  />
);

export const sidebar = () => (
  <div dangerouslySetInnerHTML={{ __html: sidebarMenu(sidebarMenuData) }} />
);

export const sidebarMinisite = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: sidebarMenu({
        ...sidebarMenuData,
        ...sidebarMenuMinisiteData,
      }),
    }}
  />
);

export const inpage = () => (
  <div dangerouslySetInnerHTML={{ __html: inpageMenu(inpageMenuData) }} />
);

export const inpageWithHeading = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: inpageMenu({
        ...inpageMenuData,
        ...inpageMenuWithHeadingData,
      }),
    }}
  />
);
