import React from 'react';

import iconFieldGroupComponent from './icon-field-group.twig';

import iconFieldGroupData from './icon-field-group.yml';
import iconFieldGroupArrowData from './icon-field-group-arrow.yml';
import iconFieldGroupPhoneData from './icon-field-group-phone.yml';
import iconFieldGroupContactData from './icon-field-group-contact.yml';

export default { title: 'Atoms/Icon Field Group' };

export const iconFieldGroup = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: iconFieldGroupComponent(iconFieldGroupData),
    }}
  />
);

export const iconFieldGroupArrow = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: iconFieldGroupComponent(iconFieldGroupArrowData),
    }}
  />
);

export const iconFieldGroupPhone = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: iconFieldGroupComponent(iconFieldGroupPhoneData),
    }}
  />
);

export const iconFieldGroupContact = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: iconFieldGroupComponent(iconFieldGroupContactData),
    }}
  />
);
