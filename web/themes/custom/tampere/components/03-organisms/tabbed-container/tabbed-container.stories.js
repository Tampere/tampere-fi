import React from 'react';

import tabbedContainerData from './tabbed-container.yml';

import tabbedContainerComponent from './tabbed-container.twig';

import './tabbed-container';

export default { title: 'Organisms/Tabbed container' };

export const tabbedContainer = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: tabbedContainerComponent(tabbedContainerData),
    }}
  />
);
