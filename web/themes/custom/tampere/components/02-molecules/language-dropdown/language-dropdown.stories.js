import React from 'react';

import languageDropdown from './language-dropdown.twig';

import languageDropdownData from './language-dropdown.yml';
import languageDropdownDynamicData from './language-dropdown-dynamic.yml';

import './language-dropdown';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Language dropdown' };

export const languageDropdownExample = () => {
  return (
    <div
      dangerouslySetInnerHTML={{
        __html: languageDropdown(languageDropdownData),
      }}
    />
  );
};

export const languageDropdownInversedExample = () => {
  return (
    <div
      dangerouslySetInnerHTML={{
        __html: languageDropdown({
          ...languageDropdownData,
          ...languageDropdownDynamicData,
        }),
      }}
    />
  );
};
