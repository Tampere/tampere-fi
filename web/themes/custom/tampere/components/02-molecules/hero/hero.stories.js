import React from 'react';

import heroData from './hero.yml';
import heroWithIconData from './hero-with-icon.yml';
import heroNoImageData from './hero-no-image.yml';
import heroTitleCenteredData from './hero-title-centered.yml';
import heroWithCTAData from './hero-with-cta.yml';

import heroComponent from './hero.twig';

export default { title: 'Atoms/Images/Hero' };

export const hero = () => (
  <div dangerouslySetInnerHTML={{ __html: heroComponent(heroData) }} />
);

export const heroWithIcon = () => (
  <div dangerouslySetInnerHTML={{ __html: heroComponent(heroWithIconData) }} />
);

export const heroWithoutImage = () => (
  <div dangerouslySetInnerHTML={{ __html: heroComponent(heroNoImageData) }} />
);

export const heroWithCenteredTitle = () => (
  <div
    dangerouslySetInnerHTML={{ __html: heroComponent(heroTitleCenteredData) }}
  />
);

export const heroWithCTA = () => (
  <div dangerouslySetInnerHTML={{ __html: heroComponent(heroWithCTAData) }} />
);
