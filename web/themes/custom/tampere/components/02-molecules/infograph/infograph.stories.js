import React from 'react';

import infographData from './infograph.yml';
import infographAltData from './infograph-alt.yml';

import infographComponent from './infograph.twig';

export default { title: 'Molecules/Infographics/Infographic' };

export const infographic = () => (
  <div
    dangerouslySetInnerHTML={{ __html: infographComponent(infographData) }}
  />
);

export const infographicAlternative = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: infographComponent({ ...infographData, ...infographAltData }),
    }}
  />
);
