import React from 'react';

import languageSwitcher from './language-switcher.twig';

import languageSwitcherData from './language-switcher.yml';
import languageSwitcherDynamicData from './language-switcher-dynamic.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Language switcher' };

export const languageSwitcherExample = () => {
  return (
    <div
      dangerouslySetInnerHTML={{
        __html: languageSwitcher(languageSwitcherData),
      }}
    />
  );
};

export const languageSwitcherInversedExample = () => {
  return (
    <div
      dangerouslySetInnerHTML={{
        __html: languageSwitcher({
          ...languageSwitcherData,
          ...languageSwitcherDynamicData,
        }),
      }}
    />
  );
};
