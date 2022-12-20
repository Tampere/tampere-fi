import React from 'react';

import involvementCardData from './involvement-card.yml';

import involvementCardComponent from './involvement-card.twig';

export default { title: 'Molecules/Involvement Cards/Involvement Card' };

export const involvementCard = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: involvementCardComponent(involvementCardData),
    }}
  />
);
