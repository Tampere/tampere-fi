import React from 'react';

import searchToggleComponent from './search-toggle.twig';

import './search-toggle';

export default { title: 'Organisms/Search toggle' };

export const searchToggle = () => (
  <div dangerouslySetInnerHTML={{ __html: searchToggleComponent() }} />
);
