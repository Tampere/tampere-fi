import React from 'react';

import Button from './react/Button.component';

import button from './01-button.twig';

import buttonData from './button.yml';
import buttonDarkData from './01-button-dark.yml';
import buttonTransparentData from './02-button-transparent.yml';
import buttonGhostData from './03-button-ghost.yml';
import buttonSecondaryData from './04-button-secondary.yml';
import buttonLinkData from './06-button-link.yml';
import buttonBackData from './07-button-back.yml';
import buttonMinimalData from './08-button-minimal.yml';

/**
 * Storybook Definition.
 */
export default {
  component: Button,
  title: 'Atoms/Button',
};

export const react = () => <Button>React Button</Button>;

export const twig = () => (
  <div dangerouslySetInnerHTML={{ __html: button(buttonData) }} />
);

export const dark = () => (
  <div dangerouslySetInnerHTML={{ __html: button(buttonDarkData) }} />
);

export const transparent = () => (
  <div dangerouslySetInnerHTML={{ __html: button(buttonTransparentData) }} />
);

export const ghost = () => (
  <div dangerouslySetInnerHTML={{ __html: button(buttonGhostData) }} />
);

export const secondary = () => (
  <div dangerouslySetInnerHTML={{ __html: button(buttonSecondaryData) }} />
);

export const link = () => (
  <div dangerouslySetInnerHTML={{ __html: button(buttonLinkData) }} />
);

export const back = () => (
  <div dangerouslySetInnerHTML={{ __html: button(buttonBackData) }} />
);

export const minimal = () => (
  <div dangerouslySetInnerHTML={{ __html: button(buttonMinimalData) }} />
);
