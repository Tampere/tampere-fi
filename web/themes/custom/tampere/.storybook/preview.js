import { addDecorator } from '@storybook/react';
import { useEffect } from '@storybook/client-api';
import Twig from 'twig';
import { setupTwig } from './setupTwig';

// GLOBAL CSS
import '../components/style.scss';

// If in a Drupal project, it's recommended to import a symlinked version of drupal.js.
import './_drupal.js';

export const parameters = {
  themes: {
    default: 'main-site',
    list: [
      { name: 'Main site', class: 'main-site', color: '#c83e36' },
      { name: 'Palette 1', class: 'palette-1', color: '#22437b' },
      { name: 'Palette 2', class: 'palette-2', color: '#0074a4' },
      { name: 'Palette 3', class: 'palette-3', color: '#397368' },
      { name: 'Palette 4', class: 'palette-4', color: '#c83e36' },
      { name: 'Palette 5', class: 'palette-5', color: '#e8b455' },
      { name: 'Palette 6', class: 'palette-6', color: '#e8b455' },
      { name: 'Palette 7', class: 'palette-7', color: '#8cc1b3' },
      { name: 'Palette 8', class: 'palette-8', color: '#91c9ea' },
      { name: 'Palette 9', class: 'palette-9', color: '#abc872' },
      { name: 'Palette 10', class: 'palette-10', color: '#ad3963' },
      { name: 'Palette 11', class: 'palette-11', color: '#cb4a6c' },
    ],
  },
};

// addDecorator deprecated, but not sure how to use this otherwise.
addDecorator((storyFn) => {
  useEffect(() => Drupal.attachBehaviors(), []);
  return storyFn();
});

setupTwig(Twig);