import React from 'react';

import generalContactCardComponent from './general-contact-card.twig';
import generalContactCardLinkedData from './general-contact-card-linked.yml';
import generalContactCardData from './general-contact-card.yml';

export default { title: 'Molecules/Contact Information/General Contact Card' };

export const generalContactCard = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: generalContactCardComponent(generalContactCardData),
    }}
  />
);

export const generalContactCardLinked = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: generalContactCardComponent({
        ...generalContactCardData,
        ...generalContactCardLinkedData,
      }),
    }}
  />
);
