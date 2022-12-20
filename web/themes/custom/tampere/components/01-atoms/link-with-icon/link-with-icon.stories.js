import React from 'react';

import linkWithIconComponent from './link-with-icon.twig';

import linkWithIconDarkData from './link-with-icon-dark.yml';
import linkWithIconGhostData from './link-with-icon-ghost.yml';
import linkWithIconTransparentData from './link-with-icon-transparent.yml';
import linkWithIconServiceData from './link-with-icon-service.yml';
import linkWithIconSecondaryData from './link-with-icon-secondary.yml';
import linkWithIconBackToFrontpageData from './link-with-icon-back-to-frontpage.yml';
import linkWithIconFullWidthData from './link-with-icon-full-width.yml';
import linkWithIconPrimaryGhostData from './link-with-icon-primary-ghost.yml';
import linkWithIconReversedData from './link-with-icon-reversed.yml';
import linkWithIconData from './link-with-icon.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Link with icon' };

export const LinkWithIcon = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: linkWithIconComponent(linkWithIconData),
    }}
  />
);

export const LinkWithIconDark = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: linkWithIconComponent({
        ...linkWithIconData,
        ...linkWithIconDarkData,
      }),
    }}
  />
);

export const LinkWithIconGhost = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: linkWithIconComponent({
        ...linkWithIconData,
        ...linkWithIconGhostData,
      }),
    }}
  />
);

export const LinkWithIconPrimaryGhost = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: linkWithIconComponent({
        ...linkWithIconData,
        ...linkWithIconPrimaryGhostData,
      }),
    }}
  />
);

export const LinkWithIconTransparent = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: linkWithIconComponent({
        ...linkWithIconData,
        ...linkWithIconTransparentData,
      }),
    }}
  />
);

export const LinkWithIconService = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: linkWithIconComponent({
        ...linkWithIconData,
        ...linkWithIconServiceData,
      }),
    }}
  />
);

export const LinkWithIconSecondary = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: linkWithIconComponent({
        ...linkWithIconData,
        ...linkWithIconSecondaryData,
      }),
    }}
  />
);

export const LinkWithIconBackToFrontpage = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: linkWithIconComponent({
        ...linkWithIconData,
        ...linkWithIconBackToFrontpageData,
      }),
    }}
  />
);

export const LinkWithIconFullWidth = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: linkWithIconComponent({
        ...linkWithIconData,
        ...linkWithIconFullWidthData,
      }),
    }}
  />
);

export const LinkWithIconReversed = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: linkWithIconComponent({
        ...linkWithIconData,
        ...linkWithIconReversedData,
      }),
    }}
  />
);
