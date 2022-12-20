import React from 'react';

import searchPanelComponent from './search-panel.twig';

import searchPanelData from './search-panel.yml';
import searchPanelRestrictedHeightData from './search-panel-restricted-height.yml';

import './search-panel';

/**
 * Storybook Definition.
 */
export default { title: 'Organisms/Search panel' };

export const searchPanel = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: searchPanelComponent(searchPanelData),
    }}
  />
);

export const searchPanelRestrictedHeight = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: searchPanelComponent(searchPanelRestrictedHeightData),
    }}
  />
);
