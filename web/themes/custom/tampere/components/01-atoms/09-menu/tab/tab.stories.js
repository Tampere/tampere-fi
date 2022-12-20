import React from 'react';

import tab from './tab.twig';

import tabData from './tab.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Tab' };

export const tabs = () => (
  <div dangerouslySetInnerHTML={{ __html: tab(tabData) }} />
);
