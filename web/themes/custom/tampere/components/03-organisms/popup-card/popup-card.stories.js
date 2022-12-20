import React from 'react';

import popupCardData from './popup-card.yml';
import popupCardWithBackgroundData from './popup-card-background.yml';

import popupCardComponent from './popup-card.twig';

export default { title: 'Organisms/Popup card' };

export const popupCard = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: popupCardComponent(popupCardData),
    }}
  />
);

export const popupCardWithBackground = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: popupCardComponent({
        ...popupCardData,
        ...popupCardWithBackgroundData,
      }),
    }}
  />
);
