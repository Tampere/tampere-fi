import React from 'react';

import linkListComponent from './link-list.twig';

import linkListData from './link-list.yml';
import linkListLargeIconsData from './link-list-large-icons.yml';
import linkListLargeIconsPrimaryData from './link-list-large-icons-primary.yml';
import linkListCenteredData from './link-list-centered.yml';
import linkListMoreSpaceData from './link-list-more-space.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Link list' };

export const LinkList = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: linkListComponent(linkListData),
    }}
  />
);

export const LinkListLargeIcons = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: linkListComponent(linkListLargeIconsData),
    }}
  />
);

export const LinkListLargeIconsPrimary = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: linkListComponent({
        ...linkListLargeIconsData,
        ...linkListLargeIconsPrimaryData,
      }),
    }}
  />
);

export const LinkListCentered = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: linkListComponent(linkListCenteredData),
    }}
  />
);

export const LinkListMoreSpace = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: linkListComponent(linkListMoreSpaceData),
    }}
  />
);
