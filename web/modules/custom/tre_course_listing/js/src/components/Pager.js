import React from "react";
import styled from "styled-components";

import { PAGER_DELIMITER } from "../constants";

const StyledPager = styled.nav`
  align-items: center;
  display: flex;
  font-family: var(--font-family-heading);
  margin-top: 32px;
  margin-bottom: 32px;
`;

const Pagination = styled.ul`
  align-items: center;
  display: flex;
  justify-content: center;
  list-style: none;
  margin: 0;
  padding: 0;
  width: 100%;
`;

const InactiveDirection = styled.span`
  @media screen and (max-width: 61.56rem) {
    position: relative;

    &,
    &::before {
      height: 32px;
      width: 32px;
    }

    &::before {
      background-image: url('/modules/custom/tre_course_listing/js/src/icons/arrow-inactive.svg');
      background-position: center;
      background-repeat: no-repeat;
      content: "";
      left: 0;
      position: absolute;
      top: 0;
      transform: scaleX(-1);
    }
  }
`;

const InactiveDirectionRight = styled(InactiveDirection)`
  &::before {
    transform: scaleX(1);
  }
`;

const PagerButton = styled.button`
  background-color: transparent;
  border: none;
  box-shadow: none;
  color: var(--color-primary);
  cursor: pointer;
  padding: 0;
  text-transform: uppercase;

  &:hover {
    text-decoration: underline;
  }
`;

const PagerPrevButton = styled(PagerButton)`
  position: relative;

  @media screen and (min-width: 61.56rem) {
    margin-right: 40px;
    padding-left: 40px;
  }

  &,
  &::before {
    min-height: 32px;
    min-width: 32px;
  }

  &::before {
    background-image: url('/modules/custom/tre_course_listing/js/src/icons/arrow.svg');
    background-position: center;
    background-repeat: no-repeat;
    content: "";
    left: 0;
    position: absolute;
    top: 0;
    transform: scaleX(-1);
  }
`;

const PagerNextButton = styled(PagerPrevButton)`
  margin-right: 0;
  padding-left: 0;

  @media screen and (min-width: 61.56rem) {
    margin-left: 40px;
    padding-right: 40px;
  }

  &::before {
    left: auto;
    right: 0;
    transform: scaleX(1);
  }
`;

const DirectionText = styled.span`
  @media screen and (max-width: 61.56rem) {
    position: absolute !important;
    clip: rect(1px, 1px, 1px, 1px);
    overflow: hidden;
    height: 1px;
    width: 1px;
    word-wrap: normal;
  }
`;

const PagerItem = styled.li`
  color: var(--color-primary);
  display: flex;
  font-size: var(--font-size-18);
  margin: 0;

  @media screen and (max-width: 61.56rem) {
    &:first-child {
      margin-left: auto;
      margin-right: 0;
    }

    &:last-child {
      margin-left: 0;
      margin-right: auto;
    }
  }

  @media screen and (min-width: 61.56rem) {
    margin: 0 8px;
  }
`;

const DirectionPagerItem = styled(PagerItem)`
  @media screen and (max-width: 61.56rem) {
    &:first-child {
      margin-left: 0;
      margin-right: auto;
    }

    &:last-child {
      margin-left: auto;
      margin-right: 0;
    }
  }
`;

const PageNumberButton = styled(PagerButton)`
  font-size: var(--font-size-20);
  font-weight: ${props => props.isActive ? "800" : "normal"};
  margin: 0 8px;
  padding: 8px;
  text-decoration: ${props => props.isActive ? "underline" : "none"};
`;

export default function Pager({
  currentPage,
  itemsPerPage,
  totalItems,
  pagerLinkSiblingCount,
  paginate
}) {
  const totalPageCount = Math.ceil(totalItems / itemsPerPage);
  const isFirstPage = currentPage === 1;
  const isLastPage = totalPageCount === currentPage;
  const pagerValues = getPagerRange(pagerLinkSiblingCount, totalPageCount, currentPage);

  const pagerItems = pagerValues.map((value, key) => {
    let pagerItemContent;
    let role;

    if (value === PAGER_DELIMITER) {
      pagerItemContent = value;
      role = "presentation";
    }
    else {
      pagerItemContent = (
        <PageNumberButton
          onClick={() => paginate(value)}
          isActive={ value === currentPage }
        >
          <span className="visually-hidden">{ Drupal.t("Page") }</span>
          {value}
        </PageNumberButton>
      );
    }

    return (
      <PagerItem key={key} role={role}>
        { pagerItemContent }
      </PagerItem>
    );
  });

  function getPagerRange(pagerLinkSiblingCount, totalPageCount, currentPage) {
    // Current page and both of its siblings are always displayed.
    const displayedPagerLinksAmount = pagerLinkSiblingCount * 2 + 1;

    // https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Array/from#sequence_generator_range
    const range = (start, end) => Array.from({ length: (end - start) + 1}, (_, i) => start + i);

    const displayLeftEllipsis = currentPage > displayedPagerLinksAmount;
    const displayRightEllipsis = currentPage < totalPageCount - displayedPagerLinksAmount + 1;

    // Display all links when there are more pager links to be displayed than
    // available pages and when the current page would not get an ellipsis on either side.
    if (displayedPagerLinksAmount >= totalPageCount || (!displayLeftEllipsis && !displayRightEllipsis)) {
      return range(1, totalPageCount);
    }

    const leftSiblingIndex = Math.max(currentPage - pagerLinkSiblingCount, 1);
    const rightSiblingIndex = Math.min(
      currentPage + pagerLinkSiblingCount,
      totalPageCount
    );

    // One extra item should be displayed when the current page is the index
    // right before both ellipses are first displayed at the same time.
    const useLongerRange = currentPage == displayedPagerLinksAmount || currentPage == totalPageCount - displayedPagerLinksAmount + 1;

    if (!displayLeftEllipsis && displayRightEllipsis) {
      let leftItemCount = useLongerRange ? displayedPagerLinksAmount + 1 : displayedPagerLinksAmount;
      let leftRange = range(1, leftItemCount);

      return [...leftRange, PAGER_DELIMITER, totalPageCount];
    }

    if (displayLeftEllipsis && !displayRightEllipsis) {
      let rightItemCount = useLongerRange ? displayedPagerLinksAmount + 1 : displayedPagerLinksAmount;
      let rightRange = range(
        totalPageCount - rightItemCount + 1,
        totalPageCount
      );

      return [1, PAGER_DELIMITER, ...rightRange];
    }

    let middleRange = range(leftSiblingIndex, rightSiblingIndex);
    return [1, PAGER_DELIMITER, ...middleRange, PAGER_DELIMITER, totalPageCount];
  };

  return (
    <StyledPager>
      { isFirstPage && <InactiveDirection aria-hidden="true" /> }
      <Pagination>
        { !isFirstPage &&
          <DirectionPagerItem>
            <PagerPrevButton onClick={() => paginate(currentPage - 1)}>
              <DirectionText>
                { Drupal.t("Previous page") }
              </DirectionText>
            </PagerPrevButton>
          </DirectionPagerItem>
        }

        { pagerItems }

        { !isLastPage &&
          <DirectionPagerItem>
            <PagerNextButton onClick={() => paginate(currentPage + 1)}>
              <DirectionText>
                { Drupal.t("Next page") }
              </DirectionText>
            </PagerNextButton>
          </DirectionPagerItem>
        }
      </Pagination>
      { isLastPage && <InactiveDirectionRight aria-hidden="true" /> }
    </StyledPager>
  );
};
