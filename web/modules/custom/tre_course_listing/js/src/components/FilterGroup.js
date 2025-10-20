import React, { forwardRef } from "react";
import styled from "styled-components";

import Filter from "./Filter";

const StyledFilterGroup = styled.div`
  margin-top: 24px;
  width: 100%;
`;

const StyledFilterList = styled.ul`
  display: ${props => props.isActive ? "flex" : "none"};
  flex-wrap: wrap;
  padding: 0;
`;

const FilterGroup = forwardRef(({
  id,
  type,
  availableFilters,
  selectFilter,
  activeFilters,
  activeFilterGroup,
  onLastItemTab,
  onPanelKeyDown
}, ref) => {
  const isActive = activeFilterGroup === type;
  const availableFiltersForType = availableFilters.filter(isOfType).sort(sortByName);
  const activeFiltersForType = activeFilters.filter(isOfType);

  function isOfType(filter) {
    return filter.type === type;
  };

  function sortByName(itemA, itemB) {
    const nameA = itemA.name.toLowerCase();
    const nameB = itemB.name.toLowerCase();

    if (nameA < nameB) {
      return -1;
    }

    if (nameA > nameB) {
      return 1;
    }

    return 0;
  };

  return (
    <StyledFilterGroup
      id={`filters${id}`}
      aria-labelledby={`label${id}`}
      aria-hidden={!isActive}
      role="tabpanel"
      ref={ref}
      onKeyDown={onPanelKeyDown}
      tabIndex={-1}
    >
      <StyledFilterList isActive={isActive}>
        {
          availableFiltersForType.map((filterItem, index) => {
            const isLastItem = index === availableFiltersForType.length - 1;
            return (
              <Filter
                filterItem={filterItem}
                selectFilter={selectFilter}
                activeFilters={activeFiltersForType}
                key={`${filterItem.type}:${filterItem.id}`}
                isLastItem={isLastItem}
                onLastItemTab={onLastItemTab}
              />
            );
          })
        }
      </StyledFilterList>
    </StyledFilterGroup>
  );
});

export default FilterGroup;