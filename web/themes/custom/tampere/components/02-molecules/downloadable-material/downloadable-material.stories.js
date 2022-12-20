import React from 'react';

import materialComponent from './downloadable-material.twig';

import materialData from './downloadable-material.yml';
/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Downloadable material' };

export const downloadableMaterial = () => (
  <div dangerouslySetInnerHTML={{ __html: materialComponent(materialData) }} />
);
