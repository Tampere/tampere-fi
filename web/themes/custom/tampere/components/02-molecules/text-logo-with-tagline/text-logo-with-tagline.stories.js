import React from 'react';

import textLogoWithTagLine from './text-logo-with-tagline.twig';

import textLogoWithTagLineNoLinkData from './text-logo-with-tagline-no-link.yml';
import textLogoWithTagLineData from './text-logo-with-tagline.yml';

export default { title: 'Molecules/Text logo with tagline' };

export const textLogoWithTagline = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: textLogoWithTagLine(textLogoWithTagLineData),
    }}
  />
);

export const textLogoWithTaglineNoLink = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: textLogoWithTagLine(textLogoWithTagLineNoLinkData),
    }}
  />
);
