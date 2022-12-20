import React from 'react';

import footerTwig from './site-footer/site-footer.twig';
import headerTwig from './site-header/site-header.twig';
import minisiteHeaderTwig from './minisite-header/minisite-header.twig';
import topicalContentTwig from './topical-content/topical-content.twig';
import precontentTwig from './site-precontent/site-precontent.twig';
import involvementContentTwig from './involvement-content/involvement-content.twig';

import breadcrumbsData from '../../02-molecules/menus/breadcrumbs/breadcrumbs.yml';
import mainMenuData from '../../02-molecules/menus/main-menu/main-menu.yml';
import footerData from './site-footer/site-footer.yml';
import footerMinisiteData from './site-footer/site-footer-minisite.yml';
import headerData from './site-header/site-header.yml';
import minisiteHeaderData from './minisite-header/minisite-header.yml';
import minisiteHeaderNoTaglineData from './minisite-header/minisite-header-no-tagline.yml';
import topicalContentData from './topical-content/topical-content.yml';
import involvementContentData from './involvement-content/involvement-content.yml';

import './site-header/site-header';

/**
 * Storybook Definition.
 */
export default { title: 'Organisms/Sites' };

export const footer = () => (
  <div dangerouslySetInnerHTML={{ __html: footerTwig(footerData) }} />
);

export const footerMinisite = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: footerTwig({
        ...footerData,
        ...footerMinisiteData,
      }),
    }}
  />
);

export const header = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: headerTwig({ ...mainMenuData, ...headerData }),
    }}
  />
);

export const minisiteHeader = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: minisiteHeaderTwig({ ...mainMenuData, ...minisiteHeaderData }),
    }}
  />
);

export const minisiteHeaderNoTagline = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: minisiteHeaderTwig({
        ...mainMenuData,
        ...minisiteHeaderData,
        ...minisiteHeaderNoTaglineData,
      }),
    }}
  />
);

export const topicalContent = () => (
  <div
    dangerouslySetInnerHTML={{ __html: topicalContentTwig(topicalContentData) }}
  />
);

export const involvementContent = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: involvementContentTwig(involvementContentData),
    }}
  />
);

export const precontent = () => (
  <div dangerouslySetInnerHTML={{ __html: precontentTwig(breadcrumbsData) }} />
);
