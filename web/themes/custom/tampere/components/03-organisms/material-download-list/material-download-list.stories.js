import React from 'react';

import materialComponent from './material-download-list.twig';

import materialData from './material-download-list.yml';
/**
 * Storybook Definition.
 */
export default { title: 'Organisms/Material download list' };

export const materialDownloadList = () => (
  <div dangerouslySetInnerHTML={{ __html: materialComponent(materialData) }} />
);
