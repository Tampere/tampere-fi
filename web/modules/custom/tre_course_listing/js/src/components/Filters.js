import React, { createRef, useId, useRef, useState, useEffect } from "react";
import { v4 as uuidv4 } from 'uuid';
import styled from "styled-components";

import { LISTING_TYPE_SPORTS } from '../constants';

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

  &:focus,
  &:hover {
    text-decoration: none;
  }
`;

export default function Filters({
  availableFilters,
  selectFilter,
  resetFilters,
  activeFilters,
  filterTypes,
  listingType
 }) {
  const [activeFilterGroup, setActiveFilterGroup] = useState("");
  const [currentActiveIndex, setCurrentActiveIndex] = useState(0);
  const [filtersHaveFocus, setFiltersHaveFocus] = useState(false);

  const filterGroupRefs = filterTypes.map(() => createRef());
  const filtersTitleId = `filters-title-${useId()}`;
  const filterTypeIds = useRef(null);
  const hasActiveFilters = Boolean(activeFilters && activeFilters.length);
  const headingRef = useRef(null);
  const lastFilterRefIndex = filterGroupRefs.length - 1;

  // The Drupal.t() calls are repetitive because replacing the repetitive parts
  // (e.g. context) with variables stopped the strings from showing up as
  // translatable strings in the UI.
  const getLabel = (filterType) => {
    switch (filterType) {
      case "department":
        if (listingType === LISTING_TYPE_SPORTS) {
          return Drupal.t("Location", {}, { context: "Course listing (sports) filter type" });
        }
        return Drupal.t("Location", {}, { context: "Course listing filter type" });
      case "subject":
      case "category":
        return Drupal.t("Course subject", {}, { context: "Course listing filter type" });
      case "period":
      default:
        if (listingType === LISTING_TYPE_SPORTS) {
          return Drupal.t("Season", {}, { context: "Course listing (sports) filter type" });
        }
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
    const filterTypeIdMap = new Map();
    filterTypes.forEach(filterType => filterTypeIdMap.set(filterType, `filter-type-${uuidv4()}`));
    filterTypeIds.current = filterTypeIdMap;
  }, []);

  useEffect(() => {
    moveFocusToCurrent();
  }, [currentActiveIndex]);

  useEffect(() => {
    if (activeFilterGroup) {
      setCurrentActiveIndex(filterTypes.indexOf(activeFilterGroup));
    }
  }, [activeFilterGroup]);

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
          filterTypeIds.current && filterTypes.map((filterType, index) => (
            <FilterGroupLabel
              id={filterTypeIds.current.get(filterType)}
              label={getLabel(filterType)}
              type={filterType}
              activeFilters={activeFilters}
              activeFilterGroup={activeFilterGroup}
              toggleActiveFilterGroup={toggleActiveFilterGroup}
              handleKeyDown={handleKeyDown}
              currentActiveIndex={currentActiveIndex}
              index={index}
              ref={filterGroupRefs[index]}
              key={`filterGroupLabel${filterTypeIds.current.get(filterType)}`}
            />
          ))
        }
      </FilterGroupLabelsContainer>
      {
        filterTypeIds.current && filterTypes.map(filterType => (
          <FilterGroup
            id={filterTypeIds.current.get(filterType)}
            type={filterType}
            availableFilters={availableFilters}
            selectFilter={selectFilter}
            activeFilters={activeFilters}
            activeFilterGroup={activeFilterGroup}
            key={`filterGroup${filterTypeIds.current.get(filterType)}`}
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
