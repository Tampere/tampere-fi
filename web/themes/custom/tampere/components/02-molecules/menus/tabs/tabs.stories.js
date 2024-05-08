import React from 'react';

import tabs from './tabs.twig';

import tabData from './tabs.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Menus/Tabs' };

export const LocalTasksTabs = () => <div dangerouslySetInnerHTML={{ __html: tabs(tabData) }} />;
