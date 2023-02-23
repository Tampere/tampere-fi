import React from 'react';

import accessibilityListComponent from './accessibility-list.twig';

import accessibilityListData from './accessibility-list.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Accessibility list' };

export const AccessibilityList = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: accessibilityListComponent(accessibilityListData),
    }}
  />
);
