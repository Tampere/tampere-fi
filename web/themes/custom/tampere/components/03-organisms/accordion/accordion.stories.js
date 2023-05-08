import React from 'react';

import accordionComponent from './accordion.twig';
import accordionItemComponent from './accordion-item.twig';
import horizontalAccordionComponent from './horizontal-accordion/horizontal-accordion.twig';
import processAccordionComponent from './process-accordion/process-accordion.twig';
import facetAccordionComponent from './facet-accordion/facet-accordion.twig';

import accordionData from './accordion.yml';
import accordionPlainData from './accordion-plain.yml';
import accordionItemMiniData from './accordion-item-mini.yml';
import accordionNavigationData from './accordion-navigation.yml';
import horizontalAccordionData from './horizontal-accordion/horizontal-accordion.yml';
import processAccordionData from './process-accordion/process-accordion.yml';
import facetAccordionData from './facet-accordion/facet-accordion.yml';

import './accordion';
import './horizontal-accordion/horizontal-accordion';
import './facet-accordion/facet-accordion';

/**
 * Storybook Definition.
 */
export default { title: 'Organisms/Accordions' };

export const accordion = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: accordionComponent(accordionData),
    }}
  />
);

export const accordionPlain = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: accordionComponent({ ...accordionData, ...accordionPlainData }),
    }}
  />
);

export const accordionNavigation = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: accordionComponent(accordionNavigationData),
    }}
  />
);

export const accordionItemMini = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: accordionItemComponent(accordionItemMiniData),
    }}
  />
);

export const accordionHorizontal = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: horizontalAccordionComponent(horizontalAccordionData),
    }}
  />
);

export const processAccordion = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: processAccordionComponent(processAccordionData),
    }}
  />
);

export const facetAccordion = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: facetAccordionComponent(facetAccordionData),
    }}
  />
);
