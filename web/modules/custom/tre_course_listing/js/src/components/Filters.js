import React, { createRef, useId, useRef, useState, useEffect, useMemo } from "react";
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
  
  // Refs for the filter group content, reset button and anchor element.
  const contentPanelRefs = useRef({});
  const resetButtonRef = useRef(null);
  const endAnchorRef = useRef(null);

  // Memoize refs for the filter group labels, 
  // so that they dont get created on every re-render.
  const filterGroupRefs = useMemo(() =>
      filterTypes.map(() => createRef()),
  [filterTypes]);
  const filtersTitleId = `filters-title-${useId()}`;
  const filterTypeIds = useRef(null);
  const filterHelpText = Drupal.t("When you select a filter, the search results are automatically narrowed down to your selection.",
    {}, { context: "Course listing filters help text" }
  );
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
      case "locationgroup":
        if (listingType === LISTING_TYPE_SPORTS) {
          return Drupal.t("Region", {}, { context: "Course listing (sports) filter type" });
        }
        return Drupal.t("Region", {}, { context: "Course listing filter type" });
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
    filterGroupRefs[0].current?.focus();
  };

  const moveIndexToLast = () => {
    const lastIndex = filterGroupRefs.length - 1;
    setCurrentActiveIndex(lastIndex);
    filterGroupRefs[lastIndex].current?.focus();
  };

  const moveIndexToPrevious = () => {
    setCurrentActiveIndex(prevIndex => {
      const newIndex = prevIndex === 0 ? lastFilterRefIndex : prevIndex - 1;
      filterGroupRefs[newIndex].current?.focus();
      return newIndex;
    });
  };

  const moveIndexToNext = () => {
    setCurrentActiveIndex(prevIndex => {
      const newIndex = prevIndex === lastFilterRefIndex ? 0 : prevIndex + 1;
      filterGroupRefs[newIndex].current?.focus();
      return newIndex;
    });
  };

  // Function handles focusing on the next filter group button / reset button.
  const focusNextElement = (currentFilterType) => {
    const currentIndex = filterTypes.indexOf(currentFilterType);
    const isLastGroup = currentIndex === filterTypes.length - 1;

    if (!isLastGroup) {
      const nextGroupRef = filterGroupRefs[currentIndex + 1];
      nextGroupRef.current?.focus();
      return true;
    }

    if (resetButtonRef.current) {
      resetButtonRef.current.focus();
      return true;
    }

    if (endAnchorRef.current) {
      endAnchorRef.current.focus();
      return true;
    }

    return false;
  };

   const handlePanelKeyDown = (event) => {
     const key = event.key;
     if (key === "ArrowLeft" || key === "ArrowRight" || key === "Home" || key === "End") {
       event.preventDefault();
       event.stopPropagation();
     }

     switch (key) {
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
         break;
     }
   };

  const handleLabelKeyDown = (event, filterType) => {
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
      case "Enter":
        toggleActiveFilterGroup(filterType);
        break;
      default:
        preventDefaultBehavior = false;
        break;
    }

    if (preventDefaultBehavior) {
      event.stopPropagation();
      event.preventDefault();
    }

    // Handle tab navigation.
    if (event.key === 'Tab') {
      const currentIndex = filterTypes.indexOf(filterType);

      if (event.shiftKey) {
        // On Shift + Tab, move focus to the previous item if it exists.
        if (currentIndex > 0) {
          event.preventDefault();
          filterGroupRefs[currentIndex - 1].current?.focus();
        }
      } else {
        const groupIsOpen = activeFilterGroup === filterType;

        if (groupIsOpen) {
          // If this group is open, tab into first option in the panel.
          const panelRef = contentPanelRefs.current[filterType];
          const firstFilter = panelRef?.current?.querySelector('input[type="checkbox"]');
          if (firstFilter) {
            event.preventDefault();
            firstFilter.focus();
          }
        } else {
          // If the group is closed, tab to the next filter label.
          const isLastGroup = currentIndex === filterTypes.length - 1;
          if (!isLastGroup) {
            event.preventDefault();
            filterGroupRefs[currentIndex + 1].current?.focus();
          } else {
            // On the last item, tab out to the reset button.
            if (focusNextElement(filterType)) {
              event.preventDefault();
            }
          }
        }
      }
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
    const panelRefs = {};
    filterTypes.forEach(filterType => {
      filterTypeIdMap.set(filterType, `filter-type-${uuidv4()}`);
      panelRefs[filterType] = createRef();
    });
    filterTypeIds.current = filterTypeIdMap;
    contentPanelRefs.current = panelRefs;
  }, [filterTypes]);

  useEffect(() => {
    if (activeFilterGroup) {
      setCurrentActiveIndex(filterTypes.indexOf(activeFilterGroup));
    }
  }, [activeFilterGroup]);

  return (
    <StyledFilters>
      <p>{ filterHelpText }</p>
      <FiltersTitle id={filtersTitleId} tabIndex="-1" ref={headingRef}>
        { Drupal.t("Filter courses") }
      </FiltersTitle>
      <FilterGroupLabelsContainer
        aria-labelledby={filtersTitleId}
        role="tablist"
        onBlur={handleBlur}
        onFocus={() => setCurrentActiveIndex(index)} 
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
              handleKeyDown={(event) => handleLabelKeyDown(event, filterType)}
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
            ref={contentPanelRefs.current[filterType]}
            onLastItemTab={() => focusNextElement(filterType)}
            onPanelKeyDown={handlePanelKeyDown}
          />
        ))
      }
      {hasActiveFilters &&
        <ResetButton
          onClick={handleReset}
          ref={resetButtonRef}
        >
          { Drupal.t("Remove filters") }
        </ResetButton>
      }
      <div ref={endAnchorRef} tabIndex="-1" />
    </StyledFilters>
  );
};