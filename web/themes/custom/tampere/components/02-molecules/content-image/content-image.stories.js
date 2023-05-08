import React from 'react';

import contentImgComponent from './content-image.twig';

import contentImgData from './content-image.yml';

export default { title: 'Atoms/Images/Content Image' };

export const contentImage = () => (
  <div
    dangerouslySetInnerHTML={{ __html: contentImgComponent(contentImgData) }}
  />
);
