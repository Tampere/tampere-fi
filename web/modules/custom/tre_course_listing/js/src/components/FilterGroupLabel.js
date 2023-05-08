import React, { forwardRef } from "react";
import styled from "styled-components";

const Label = styled.span`
  font-weight: 500;
  text-decoration: ${props => props.isActive ? "underline" : "none"};
  word-break: break-word;
`;

// Visibility of description (Show/Hide filters) toggled so that screenreaders
// use the proper filter group label when announcing changes to active filters
// (e.g. "Semester, active filters 3" instead of "Hide filters Semester,
// active filters 3").
const StyledAlternativeDescription = styled.span`
  display: none;
`;

const StyledFilterGroupLabel = styled.button`
  align-items: center;
  background-color: ${props => props.isActive ? "var(--color-primary)" : "transparent"};
  border: 2px solid var(--color-primary);
  color: ${props => props.isActive ? "#fff" : "var(--color-primary)"};
  cursor: pointer;
  display: block;
  margin-left: -2px;
  padding: 12px 20px;
  text-transform: uppercase;

  @media screen and (min-width: 61.56rem) {
    padding: 20px 28px;
  }

  &:focus {
    ${StyledAlternativeDescription} {
      display: block;
    }
  }

  &:focus,
  &:hover {
    ${Label} {
      text-decoration: underline;
    }
  }
`;

const Count = styled.span`
  background-color: var(--color-count);
  border-radius: 32px;
  color: #fff;
  display: inline-block;
  font-size: var(--font-size-18);
  margin-left: 32px;
  min-width: 30px;
  padding: 4px 10px;
`;

const FilterGroupLabel = forwardRef((props, ref) => {

  const {
    id,
    label,
    type,
    activeFilters,
    activeFilterGroup,
    toggleActiveFilterGroup,
    handleKeyDown,
    currentActiveIndex,
    index
  } = props;

  const isActive = activeFilterGroup === type;
  const activeFiltersForType = activeFilters.filter(isOfType);
  const activeFilterAmount = activeFiltersForType.length;
  const hasActiveFilters = activeFilterAmount > 0;

  function isOfType(filter) {
    return filter.type === type;
  };

  return (
    <StyledFilterGroupLabel
      id={`label${id}`}
      aria-controls={`filters${id}`}
      aria-selected={isActive}
      isActive={isActive}
      onClick={() => toggleActiveFilterGroup(type)}
      onKeyDown={handleKeyDown}
      role="tab"
      tabIndex={index === currentActiveIndex ? null : -1}
      ref={ref}
    >
      <StyledAlternativeDescription className="visually-hidden">
        { isActive ?
          Drupal.t("Hide filters", {}, { context: "Course listing assistive text for filter buttons" }) :
          Drupal.t("Show filters", {}, { context: "Course listing assistive text for filter buttons" })
        }
      </StyledAlternativeDescription>

      <Label isActive={isActive}>
        { label }
      </Label>

      { hasActiveFilters &&
        <Count>
          <span className="visually-hidden">{ Drupal.t("Active filters", {}, { context: "Course listing assistive text for active filters count" }) }: </span>
          { activeFilterAmount }
        </Count>
      }
    </StyledFilterGroupLabel>
  );
});

export default FilterGroupLabel;
