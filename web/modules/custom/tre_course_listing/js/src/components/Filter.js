import React, { useId } from "react";
import styled from "styled-components";

const StyledFilter = styled.li`
  align-items: center;
  background-color: ${props => props.isActive ? "var(--color-primary)" : "transparent"};
  border: 1.5px solid var(--color-primary);
  border-radius: 35px;
  color: ${props => props.isActive ? "#fff" : "var(--color-primary)"};
  cursor: pointer;
  display: inline-flex;
  font-family: var(--font-family-heading);
  font-size: var(--font-size-16);
  text-decoration: ${props => props.isActive ? "underline" : "none"};
  position: relative;
  margin: 6px;

  &:focus-within {
    outline: 1px solid var(--color-primary);
    outline-offset: 3px;
    text-decoration: underline;
  }

  &:hover {
    text-decoration: ${props => props.isActive ? "none" : "underline"};
  }

  @media screen and (min-width: 61.56rem) {
    font-size: var(--font-size-16);
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
  padding: 8px 20px;
  padding-right: ${props => props.isActive ? "48px" : "20px"};
`;

const CloseIcon = styled.span`
  background-image: url('/modules/custom/tre_course_listing/js/src/icons/close.svg');
  background-size: 1rem 1rem;
  display: inline-block;
  margin-left: -16px;
  pointer-events: none;
  position: relative;
  right: 20px;
  top: 1px;
  height: 16px;
  width: 16px;
`;

export default function Filter({
  filterItem,
  selectFilter,
  activeFilters,
  isLastItem,
  onLastItemTab,
}) {
  const isActive = activeFilters.some(
    filter => filter.keywords.includes(`${filterItem.type}:${filterItem.id}`)
  );

  const filterId = useId();

  const handleKeyDown = (event) => {
    if (isLastItem && event.key === 'Tab' && !event.shiftKey) {
      // Prevent default behavior, if there was a group button to focus to.
      const focusWasHandled = onLastItemTab();
      if (focusWasHandled) {
        event.preventDefault();
      }
    }
    if(event.key === "Enter") {
      event.preventDefault();
      selectFilter(filterItem);
    }
  };

  return (
    <StyledFilter isActive={isActive}>
      <StyledCheckbox
        id={`filter-${filterId}`}
        type="checkbox"
        onChange={() => selectFilter(filterItem)}
        defaultChecked={isActive}
        onKeyDown={handleKeyDown}
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