import React from 'react';

import someRowTwig from './some-row.twig';

import someRowInheritedColorsData from './some-row-inherited-colors.yml';
import someRowData from './some-row.yml';

export default { title: 'Molecules/Some Rows' };

export const someRow = () => (
  <div dangerouslySetInnerHTML={{ __html: someRowTwig(someRowData) }} />
);

export const someRowInheritedColors = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: someRowTwig({ ...someRowData, ...someRowInheritedColorsData }),
    }}
  />
);
