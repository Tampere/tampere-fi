import React from "react";
import {
  StyledPager,
  Pagination,
  InactiveDirection,
  InactiveDirectionRight,
  PagerButton,
  PagerPrevButton,
  PagerNextButton,
  DirectionText,
  PagerItem,
  DirectionPagerItem,
  PageNumberButton,
} from "./Pager.styles";
import { PAGER_DELIMITER } from "../../lib/constants";

export default function Pager({
  currentPage,
  itemsPerPage,
  totalItems,
  pagerLinkSiblingCount,
  paginate,
}) {
  const totalPageCount = Math.ceil(totalItems / itemsPerPage);
  const isFirstPage = currentPage === 1;
  const isLastPage = totalPageCount === currentPage;
  const pagerValues = getPagerRange(
    pagerLinkSiblingCount,
    totalPageCount,
    currentPage
  );

  const pagerItems = pagerValues.map((value, key) => {
    let pagerItemContent;
    let role;

    if (value === PAGER_DELIMITER) {
      pagerItemContent = value;
      role = "presentation";
    } else {
      pagerItemContent = (
        <PageNumberButton
          onClick={() => paginate(value)}
          isActive={value === currentPage}
        >
          <span className="visually-hidden rs_skip">{Drupal.t("Page")}</span>
          {value}
        </PageNumberButton>
      );
    }

    return (
      <PagerItem key={key} role={role}>
        {pagerItemContent}
      </PagerItem>
    );
  });

  function getPagerRange(pagerLinkSiblingCount, totalPageCount, currentPage) {
    // Current page and both of its siblings are always displayed.
    const displayedPagerLinksAmount = pagerLinkSiblingCount * 2 + 1;

    // https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Array/from#sequence_generator_range
    const range = (start, end) =>
      Array.from({ length: end - start + 1 }, (_, i) => start + i);

    const displayLeftEllipsis = currentPage > displayedPagerLinksAmount;
    const displayRightEllipsis =
      currentPage < totalPageCount - displayedPagerLinksAmount + 1;

    // Display all links when there are more pager links to be displayed than
    // available pages and when the current page would not get an ellipsis on either side.
    if (
      displayedPagerLinksAmount >= totalPageCount ||
      (!displayLeftEllipsis && !displayRightEllipsis)
    ) {
      return range(1, totalPageCount);
    }

    const leftSiblingIndex = Math.max(currentPage - pagerLinkSiblingCount, 1);
    const rightSiblingIndex = Math.min(
      currentPage + pagerLinkSiblingCount,
      totalPageCount
    );

    // One extra item should be displayed when the current page is the index
    // right before both ellipses are first displayed at the same time.
    const useLongerRange =
      currentPage == displayedPagerLinksAmount ||
      currentPage == totalPageCount - displayedPagerLinksAmount + 1;

    if (!displayLeftEllipsis && displayRightEllipsis) {
      let leftItemCount = useLongerRange
        ? displayedPagerLinksAmount + 1
        : displayedPagerLinksAmount;
      let leftRange = range(1, leftItemCount);

      return [...leftRange, PAGER_DELIMITER, totalPageCount];
    }

    if (displayLeftEllipsis && !displayRightEllipsis) {
      let rightItemCount = useLongerRange
        ? displayedPagerLinksAmount + 1
        : displayedPagerLinksAmount;
      let rightRange = range(
        totalPageCount - rightItemCount + 1,
        totalPageCount
      );

      return [1, PAGER_DELIMITER, ...rightRange];
    }

    let middleRange = range(leftSiblingIndex, rightSiblingIndex);
    return [
      1,
      PAGER_DELIMITER,
      ...middleRange,
      PAGER_DELIMITER,
      totalPageCount,
    ];
  }

  return (
    <StyledPager>
      {isFirstPage && <InactiveDirection aria-hidden="true" />}
      <Pagination>
        {!isFirstPage && (
          <DirectionPagerItem>
            <PagerPrevButton onClick={() => paginate(currentPage - 1)}>
              <DirectionText>{Drupal.t("Previous page")}</DirectionText>
            </PagerPrevButton>
          </DirectionPagerItem>
        )}

        {pagerItems}

        {!isLastPage && (
          <DirectionPagerItem>
            <PagerNextButton onClick={() => paginate(currentPage + 1)}>
              <DirectionText>{Drupal.t("Next page")}</DirectionText>
            </PagerNextButton>
          </DirectionPagerItem>
        )}
      </Pagination>
      {isLastPage && <InactiveDirectionRight aria-hidden="true" />}
    </StyledPager>
  );
}
