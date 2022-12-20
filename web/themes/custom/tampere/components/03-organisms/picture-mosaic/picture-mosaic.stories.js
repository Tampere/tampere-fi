import React from 'react';

import pictureMosaicComponent from './picture-mosaic.twig';

import pictureMosaicData from './picture-mosaic.yml';
import pictureMosaicSingleRowData from './picture-mosaic-single-row.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Organisms/Picture mosaic' };

export const pictureMosaic = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: pictureMosaicComponent(pictureMosaicData),
    }}
  />
);

export const pictureMosaicWithSingleRow = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: pictureMosaicComponent({
        ...pictureMosaicData,
        ...pictureMosaicSingleRowData,
      }),
    }}
  />
);
