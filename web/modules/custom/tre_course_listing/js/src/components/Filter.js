import React, { useId } from "react";
import styled from "styled-components";

const StyledFilter = styled.li`
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
  position: relative;

  &:focus-within {
    outline: 1px solid var(--color-primary);
    outline-offset: 3px;
    text-decoration: underline;
  }

  &:hover {
    text-decoration: ${props => props.isActive ? "none" : "underline"};
  }

  @media screen and (min-width: 61.56rem) {
    font-size: var(--font-size-20);
  }
`;

const StyledCheckbox = styled.input`
  cursor: pointer;
  opacity: 0;
  position: absolute;
  top: 0;
  left: 0;
  height: 1rem;
  width: 1rem;
`;

const StyledLabel = styled.label`
  cursor: pointer;
  padding-top: 10px;
  padding-right: ${props => props.isActive ? "72px" : "35px"};
  padding-bottom: 10px;
  padding-left: 36px;
`;

const CloseIcon = styled.span`
  background-image: url('/modules/custom/tre_course_listing/js/src/icons/close.svg');
  background-position: center;
  background-repeat: no-repeat;
  height: 32px;
  position: absolute;
  pointer-events: none;
  right: 13px;
  width: 32px;
`;

export default function Filter({
  filterItem,
  selectFilter,
  activeFilters
}) {
  const isActive = activeFilters.some(
    filter => filter.keywords.includes(`${filterItem.type}:${filterItem.id}`)
  );

  const filterId = useId();

  return (
    <StyledFilter isActive={isActive}>
      <StyledCheckbox
        id={`filter-${filterId}`}
        type="checkbox"
        onChange={() => selectFilter(filterItem)}
        defaultChecked={isActive}
      />
      <StyledLabel
        htmlFor={`filter-${filterId}`}
        isActive={isActive}
      >
        { filterItem.name }
      </StyledLabel>
      { isActive && <CloseIcon /> }
    </StyledFilter>
  );
};
