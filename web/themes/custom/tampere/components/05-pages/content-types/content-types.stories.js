import React from 'react';

import newsItemTwig from './news-item.twig';
import newsItemWithSidebarTwig from './news-item-with-sidebar.twig';
import noticeTwig from './notice.twig';
import blogPostTwig from './blog-post.twig';
import frontPageTwig from './front-page.twig';
import collectionPageTwig from './collection-page.twig';
import collectionPageWithSidebarTwig from './collection-page-with-sidebar.twig';
import basicPageTwig from './basic-page.twig';
import basicPageWithSidebarTwig from './basic-page-with-sidebar.twig';
import minisiteCollectionPageTwig from './minisite-collection-page.twig';
import involvementOpportunityPageTwig from './involvement-opportunity.twig';

import headerData from '../../03-organisms/site/site-header/site-header.yml';
import minisiteHeaderData from '../../03-organisms/site/minisite-header/minisite-header.yml';
import footerData from '../../03-organisms/site/site-footer/site-footer.yml';
import minisiteFooterData from '../../03-organisms/site/site-footer/site-footer-minisite.yml';
import mainMenuData from '../../02-molecules/menus/main-menu/main-menu.yml';
import breadcrumbData from '../../02-molecules/menus/breadcrumbs/breadcrumbs.yml';
import sidebarMenuData from '../../02-molecules/menus/sidebar-menu/sidebar-menu.yml';
import involvementHeaderData from '../../02-molecules/involvement-header/involvement-header.yml';

import topicalContentData from '../../03-organisms/site/topical-content/topical-content.yml';

import infographicsData from '../../03-organisms/infographics/infographics.yml';
import accordionData from '../../03-organisms/accordion/accordion.yml';
import processAccordionData from '../../03-organisms/accordion/process-accordion/process-accordion.yml';
import groupedContentLiftupData from '../../03-organisms/grouped-content-liftup/grouped-content-liftup.yml';
import topicsAndLifeSituationsData from '../../03-organisms/topics-and-life-situations/topics-and-life-situations-topics.yml';
import feedbackSectionData from '../../03-organisms/feedback-section/feedback-section.yml';
import topicalListingData from '../../03-organisms/topical-listing/topical-listing.twig';
import contactListingData from '../../03-organisms/contact-listing/contact-listing.yml';
import rssFeedData from '../../03-organisms/rss-feed/rss-feed.yml';
import tabbedContainerData from '../../03-organisms/tabbed-container/tabbed-container.yml';
import materialData from '../../03-organisms/material-download-list/material-download-list.yml';
import ctaData from '../../02-molecules/cta/cta.yml';
import attachmentListData from '../../02-molecules/attachment-list/attachment-list.yml';
import generalContactCard from '../../02-molecules/general-contact-card/general-contact-card.yml';
import placeContactData from '../../02-molecules/place-contact/place-contact.yml';
import placeOfBusinessContactData from '../../02-molecules/place-of-business-contact/place-of-business-contact.yml';
import personContactData from '../../02-molecules/person-contact/person-contact.yml';
import feedbackSectionCardData from '../../02-molecules/feedback-card/feedback-card.yml';
import involvementCardData from '../../02-molecules/involvement-card/involvement-card.yml';
import linkedListWithLargeIconsData from '../../01-atoms/link-list/link-list-large-icons.yml';
import feedbackSectionLinksVariationData from '../../01-atoms/link-list/link-list-large-icons-primary.yml';

/**
 * Storybook Definition.
 */
export default {
  title: 'Pages/Content Types',
  parameters: {
    layout: 'fullscreen',
  },
};

export const newsItem = () => (
    <div
      dangerouslySetInnerHTML={{
        __html: newsItemTwig({
          ...headerData,
          ...footerData,
          ...mainMenuData,
          ...breadcrumbData,
          ...topicalContentData,
          ...accordionData,
          ...processAccordionData,
          ...infographicsData,
          ...ctaData,
          ...attachmentListData,
          ...generalContactCard,
          ...placeContactData,
          ...placeOfBusinessContactData,
          ...personContactData,
          ...rssFeedData,
        }),
      }}
    />
);

export const newsItemWithSidebar = () => (
    <div
      dangerouslySetInnerHTML={{
        __html: newsItemWithSidebarTwig({
          ...headerData,
          ...footerData,
          ...mainMenuData,
          ...breadcrumbData,
          ...sidebarMenuData,
          ...topicalContentData,
          ...accordionData,
          ...processAccordionData,
          ...infographicsData,
          ...ctaData,
          ...attachmentListData,
          ...generalContactCard,
          ...placeContactData,
          ...placeOfBusinessContactData,
          ...personContactData,
          ...rssFeedData,
        }),
      }}
    />
);

export const notice = () => (
    <div
      dangerouslySetInnerHTML={{
        __html: noticeTwig({
          ...headerData,
          ...footerData,
          ...mainMenuData,
          ...breadcrumbData,
          ...topicalContentData,
          ...accordionData,
          ...processAccordionData,
          ...infographicsData,
          ...ctaData,
          ...attachmentListData,
          ...generalContactCard,
          ...placeContactData,
          ...placeOfBusinessContactData,
          ...personContactData,
        }),
      }}
    />
);

export const blogPost = () => (
    <div
      dangerouslySetInnerHTML={{
        __html: blogPostTwig({
          ...headerData,
          ...footerData,
          ...mainMenuData,
          ...breadcrumbData,
          ...topicalContentData,
          ...accordionData,
          ...processAccordionData,
          ...infographicsData,
          ...ctaData,
          ...attachmentListData,
          ...generalContactCard,
          ...placeContactData,
          ...placeOfBusinessContactData,
          ...personContactData,
        }),
      }}
    />
);

export const frontPage = () => (
    <div
      dangerouslySetInnerHTML={{
        __html: frontPageTwig({
          ...headerData,
          ...footerData,
          ...mainMenuData,
          ...breadcrumbData,
          ...accordionData,
          ...processAccordionData,
          ...infographicsData,
          ...ctaData,
          ...attachmentListData,
          ...generalContactCard,
          ...placeContactData,
          ...placeOfBusinessContactData,
          ...personContactData,
          ...groupedContentLiftupData,
          ...linkedListWithLargeIconsData,
          ...topicsAndLifeSituationsData,
          ...feedbackSectionData,
          ...feedbackSectionCardData,
          ...feedbackSectionLinksVariationData,
          ...topicalListingData,
        }),
      }}
    />
);

export const collectionPage = () => (
    <div
      dangerouslySetInnerHTML={{
        __html: collectionPageTwig({
          ...headerData,
          ...footerData,
          ...mainMenuData,
          ...breadcrumbData,
          ...accordionData,
          ...processAccordionData,
          ...infographicsData,
          ...ctaData,
          ...attachmentListData,
          ...generalContactCard,
          ...placeContactData,
          ...placeOfBusinessContactData,
          ...personContactData,
          ...groupedContentLiftupData,
          ...topicalListingData,
          ...materialData,
        }),
      }}
    />
);

export const collectionPageWithSidebar = () => (
    <div
      dangerouslySetInnerHTML={{
        __html: collectionPageWithSidebarTwig({
          ...headerData,
          ...footerData,
          ...mainMenuData,
          ...breadcrumbData,
          ...sidebarMenuData,
          ...accordionData,
          ...processAccordionData,
          ...infographicsData,
          ...ctaData,
          ...attachmentListData,
          ...generalContactCard,
          ...placeContactData,
          ...placeOfBusinessContactData,
          ...personContactData,
          ...groupedContentLiftupData,
          ...topicalListingData,
          ...materialData,
        }),
      }}
    />
);

export const basicPage = () => (
    <div
      dangerouslySetInnerHTML={{
        __html: basicPageTwig({
          ...headerData,
          ...footerData,
          ...mainMenuData,
          ...breadcrumbData,
          ...accordionData,
          ...processAccordionData,
          ...infographicsData,
          ...ctaData,
          ...attachmentListData,
          ...generalContactCard,
          ...placeContactData,
          ...placeOfBusinessContactData,
          ...personContactData,
          ...topicalListingData,
          ...contactListingData,
          ...tabbedContainerData,
          ...materialData,
        }),
      }}
    />
);

export const basicPageWithSidebar = () => (
    <div
      dangerouslySetInnerHTML={{
        __html: basicPageWithSidebarTwig({
          ...headerData,
          ...footerData,
          ...mainMenuData,
          ...breadcrumbData,
          ...sidebarMenuData,
          ...accordionData,
          ...processAccordionData,
          ...infographicsData,
          ...ctaData,
          ...attachmentListData,
          ...generalContactCard,
          ...placeContactData,
          ...placeOfBusinessContactData,
          ...personContactData,
          ...topicalListingData,
          ...contactListingData,
          ...tabbedContainerData,
          ...materialData,
        }),
      }}
    />
);

export const minisiteCollectionPage = () => (
    <div
      dangerouslySetInnerHTML={{
        __html: minisiteCollectionPageTwig({
          ...minisiteHeaderData,
          ...breadcrumbData,
          ...footerData,
          ...minisiteFooterData,
          ...accordionData,
          ...processAccordionData,
          ...infographicsData,
          ...ctaData,
          ...attachmentListData,
          ...generalContactCard,
          ...placeContactData,
          ...placeOfBusinessContactData,
          ...personContactData,
          ...groupedContentLiftupData,
          ...topicalListingData,
        }),
      }}
    />
);

export const InvolvementOpportunity = () => (
    <div
      dangerouslySetInnerHTML={{
        __html: involvementOpportunityPageTwig({
          ...headerData,
          ...footerData,
          ...mainMenuData,
          ...breadcrumbData,
          ...involvementHeaderData,
          ...involvementCardData,
          ...accordionData,
          ...processAccordionData,
          ...infographicsData,
          ...ctaData,
          ...attachmentListData,
          ...generalContactCard,
          ...placeContactData,
          ...placeOfBusinessContactData,
          ...personContactData,
          ...rssFeedData,
        }),
      }}
    />
);
