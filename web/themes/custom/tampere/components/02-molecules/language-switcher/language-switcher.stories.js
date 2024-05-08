import React from 'react';

import languageSwitcher from './language-switcher.twig';

import languageSwitcherData from './language-switcher.yml';
import languageSwitcherDynamicData from './language-switcher-dynamic.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Language switcher' };

export const languageSwitcherExample = () => (
    <div
      dangerouslySetInnerHTML={{
        __html: languageSwitcher(languageSwitcherData),
      }}
    />
);

export const languageSwitcherInversedExample = () => (
    <div
      dangerouslySetInnerHTML={{
        __html: languageSwitcher({
          ...languageSwitcherData,
          ...languageSwitcherDynamicData,
        }),
      }}
    />
);
