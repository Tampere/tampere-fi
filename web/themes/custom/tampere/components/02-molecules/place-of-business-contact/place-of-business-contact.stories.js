import React from 'react';

import placeOfBusinessContactComponent from './place-of-business-contact.twig';
import placeOfBusinessContactLinkedData from './place-of-businesss-contact-linked.yml';
import placeOfBusinessContactData from './place-of-business-contact.yml';

export default {
  title: 'Molecules/Contact Information/Place Of Business Contact',
};

export const placeOfBusinessContact = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: placeOfBusinessContactComponent(placeOfBusinessContactData),
    }}
  />
);

export const placeOfBusinessContactLinked = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: placeOfBusinessContactComponent({
        ...placeOfBusinessContactData,
        ...placeOfBusinessContactLinkedData,
      }),
    }}
  />
);
