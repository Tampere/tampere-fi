import React from 'react';

import placeContactComponent from './place-contact.twig';
import placeContactLinkedData from './place-contact-linked.yml';
import placeContactData from './place-contact.yml';

export default { title: 'Molecules/Contact Information/Place Contact' };

export const placeContact = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: placeContactComponent(placeContactData),
    }}
  />
);

export const placeContactLinked = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: placeContactComponent({
        ...placeContactData,
        ...placeContactLinkedData,
      }),
    }}
  />
);
