import React from 'react';

import listingCardData from './listing-card.yml';
import listingCardFullData from './listing-card-full.yml';

import listingCardComponent from './listing-card.twig';

export default { title: 'Molecules/Cards/Listing Card' };

export const listingCard = () => (
  <div
    dangerouslySetInnerHTML={{ __html: listingCardComponent(listingCardData) }}
  />
);

export const listingCardFull = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: listingCardComponent(listingCardFullData),
    }}
  />
);
