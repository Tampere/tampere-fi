import React, { createRef, useId, useRef, useState, useEffect } from "react";
import styled from "styled-components";

import FilterGroup from "./FilterGroup";
import FilterGroupLabel from "./FilterGroupLabel";

const StyledFilters = styled.div``;

const FiltersTitle = styled.h3`
  font-size: var(--font-size-16);

  @media screen and (min-width: 61.56rem) {
    font-size: var(--font-size-20);
  }
`;

const FilterGroupLabelsContainer = styled.div`
  column-gap: 16px;
  display: flex;
  flex-wrap: wrap;
  font-family: var(--font-family-heading);
  row-gap: 16px;

  @media screen and (min-width: 61.56rem) {
    column-gap: 0;
  }
`;

const ResetButton = styled.button`
  background-color: transparent;
  border: none;
  padding: 0;
  color: #22437b;
  cursor: pointer;
  display: block;
  text-underline-offset: 2px;
  text-decoration-thickness: 1px;
  margin: 32px 0;
  text-decoration: underline;

  &:hover {
    color: #000;
  }
`;

export default function Filters({
  availableFilters,
  selectFilter,
  resetFilters,
  activeFilters,
  filterItemParentNames,
  filterTypes
 }) {
  const [activeFilterGroup, setActiveFilterGroup] = useState("");
  const [currentActiveIndex, setCurrentActiveIndex] = useState(0);
  const [filtersHaveFocus, setFiltersHaveFocus] = useState(false);
  const hasActiveFilters = Boolean(activeFilters && activeFilters.length);

  const filtersTitleId = `filtersTitle${useId()}`;

  const headingRef = useRef(null);

  const filterGroupRefs = filterTypes.map(() => createRef());
  const lastFilterRefIndex = filterGroupRefs.length - 1;

  const getLabel = (type) => {
    switch (type) {
      case "department":
        return Drupal.t("Location", {}, { context: "Course listing filter type" });
      case "subject":
      case "category":
        return Drupal.t("Course subject", {}, { context: "Course listing filter type" });
      case "period":
      default:
        return Drupal.t("Semester", {}, { context: "Course listing filter type" });
    }
  };

  function toggleActiveFilterGroup(type) {
    setActiveFilterGroup(prevType => prevType === type ? "" : type);
  };

  const moveFocusToCurrent = () => {
    if (filtersHaveFocus) {
      const currentGroup = filterGroupRefs[currentActiveIndex];
      currentGroup.current.focus();
    }
  };

  const moveIndexToFirst = () => {
    setCurrentActiveIndex(0);
  };

  const moveIndexToLast = () => {
    setCurrentActiveIndex(filterGroupRefs.length - 1);
  };

  const moveIndexToPrevious = () => {
    setCurrentActiveIndex(prevIndex => prevIndex === 0 ? lastFilterRefIndex : prevIndex - 1);
  };

  const moveIndexToNext = () => {
    setCurrentActiveIndex(prevIndex => prevIndex === lastFilterRefIndex ? 0 : prevIndex + 1);
  };

  const handleKeyDown = event => {
    let preventDefaultBehavior = true;

    switch (event.key) {
      case "ArrowLeft":
        moveIndexToPrevious();
        break;
      case "ArrowRight":
        moveIndexToNext();
        break;
      case "Home":
        moveIndexToFirst();
        break;
      case "End":
        moveIndexToLast();
        break;
      default:
        preventDefaultBehavior = false;
        break;
    }

    if (preventDefaultBehavior) {
      event.stopPropagation();
      event.preventDefault();
    }
  };

  const handleReset = () => {
    headingRef.current.focus();
    setActiveFilterGroup("");
    setCurrentActiveIndex(0);
    resetFilters();
  };

  const handleBlur = (event) => {
    if (!event.currentTarget.contains(event.relatedTarget)) {
      setFiltersHaveFocus(false);
    }
  };

  const handleFocus = (event) => {
    if (!event.currentTarget.contains(event.relatedTarget)) {
      setFiltersHaveFocus(true);
    }
  };

  useEffect(() => {
    moveFocusToCurrent();
  }, [currentActiveIndex]);

  useEffect(() => {
    if (activeFilterGroup) {
      setCurrentActiveIndex(filterTypes.indexOf(activeFilterGroup));
    }
  }, [activeFilterGroup]);

  const filterTypeIds = new Map();
  filterTypes.forEach(filterType => filterTypeIds.set(filterType, useId()));

  return (
    <StyledFilters>
      <FiltersTitle id={filtersTitleId} tabIndex="-1" ref={headingRef}>
        { Drupal.t("Filter courses") }
      </FiltersTitle>
      <FilterGroupLabelsContainer
        aria-labelledby={filtersTitleId}
        role="tablist"
        onBlur={handleBlur}
        onFocus={handleFocus}
      >
        {
          filterTypes.map((filterType, index) => (
            <FilterGroupLabel
              id={filterTypeIds.get(filterType)}
              label={getLabel(filterType)}
              type={filterType}
              activeFilters={activeFilters}
              activeFilterGroup={activeFilterGroup}
              toggleActiveFilterGroup={toggleActiveFilterGroup}
              handleKeyDown={handleKeyDown}
              currentActiveIndex={currentActiveIndex}
              index={index}
              ref={filterGroupRefs[index]}
              key={`filterGroupLabel${filterTypeIds.get(filterType)}`}
            />
          ))
        }
      </FilterGroupLabelsContainer>
      {
        filterTypes.map(filterType => (
          <FilterGroup
            id={filterTypeIds.get(filterType)}
            type={filterType}
            availableFilters={availableFilters}
            selectFilter={selectFilter}
            activeFilters={activeFilters}
            activeFilterGroup={activeFilterGroup}
            filterItemParentNames={filterItemParentNames}
            key={`filterGroup${filterTypeIds.get(filterType)}`}
          />
        ))
      }
      { hasActiveFilters &&
        <ResetButton onClick={handleReset}>
          { Drupal.t("Remove filters") }
        </ResetButton>
      }
    </StyledFilters>
  );
};
