import React from 'react';

import personContactComponent from './person-contact.twig';
import personContactData from './person-contact.yml';

export default { title: 'Molecules/Contact Information/Person Contact' };

export const personContact = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: personContactComponent(personContactData),
    }}
  />
);
