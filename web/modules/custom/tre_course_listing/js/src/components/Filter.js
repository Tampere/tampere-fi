import React from "react";
import styled from "styled-components";

const StyledFilter = styled.button`
  align-items: center;
  background-color: ${props => props.isActive ? "var(--color-primary)" : "transparent"};
  border: 2px solid var(--color-primary);
  border-radius: 35px;
  color: ${props => props.isActive ? "#fff" : "var(--color-primary)"};
  cursor: pointer;
  display: inline-flex;
  font-family: var(--font-family-heading);
  font-size: var(--font-size-18);
  text-decoration: ${props => props.isActive ? "underline" : "none"};
  padding-top: 10px;
  padding-right: ${props => props.isActive ? "72px" : "35px"};
  padding-bottom: 10px;
  padding-left: 36px;
  position: relative;

  &:focus,
  &:hover {
    text-decoration: underline;
  }

  @media screen and (min-width: 61.56rem) {
    font-size: var(--font-size-20);
  }
`;

const CloseIcon = styled.span`
  background-image: url('/modules/custom/tre_course_listing/js/src/icons/close.svg');
  background-position: center;
  background-repeat: no-repeat;
  height: 32px;
  position: absolute;
  right: 13px;
  width: 32px;
`;

export default function Filter({
  filterItem,
  selectFilter,
  activeFilters,
  filterItemParentNames
}) {
  const isActive = activeFilters.some(
    filter => filter.keywords.includes(`${filterItem.type}:${filterItem.id}`)
  );

  let filterDisplayName = filterItem.name;

  if (filterItemParentNames.has(filterItem.parent)) {
    const parentItemName = filterItemParentNames.get(filterItem.parent);
    filterDisplayName = `${filterDisplayName} ${parentItemName}`;
  }

  let ariaLabel;
  if (isActive) {
    ariaLabel = Drupal.t('Remove filter @name', { '@name': filterDisplayName });
  }

  return (
    <StyledFilter
      aria-label={ariaLabel}
      onClick={() => selectFilter(filterItem)}
      isActive={isActive}
    >
      { filterDisplayName }
      { isActive && <CloseIcon /> }
    </StyledFilter>
  );
};
