import React from 'react';

import searchBarComponent from './search-bar.twig';

import searchBarNoBorderData from './search-bar-no-border.yml';

export default { title: 'Molecules/Search bar' };

export const searchBar = () => (
  <div dangerouslySetInnerHTML={{ __html: searchBarComponent() }} />
);

export const searchBarNoBorder = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: searchBarComponent(searchBarNoBorderData),
    }}
  />
);
