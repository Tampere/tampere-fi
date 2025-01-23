import React from 'react';

import cardWithBorderData from './card-with-border.yml';
import cardWithBorderComponent from './card-with-border.twig';

export default { title: 'Molecules/Card With Border' };

export const cardWithBorder = () => (
  <div dangerouslySetInnerHTML={{ __html: cardWithBorderComponent(cardWithBorderData) }} />
);
