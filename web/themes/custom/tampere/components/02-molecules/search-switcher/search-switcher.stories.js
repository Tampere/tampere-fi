import React from 'react';

import searchSwitcherComponent from './search-switcher.twig';

import searchSwitcherData from './search-switcher.yml';

export default { title: 'Molecules/Search switcher' };

export const searchSwitcher = () => (
  <div dangerouslySetInnerHTML={{ __html: searchSwitcherComponent(searchSwitcherData) }} />
);
