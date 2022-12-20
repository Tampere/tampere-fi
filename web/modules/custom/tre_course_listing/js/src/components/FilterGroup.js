import React, { useId } from "react";
import styled from "styled-components";

import Filter from "./Filter";

const StyledFilterGroup = styled.div`
  display: ${props => props.isActive ? "flex" : "none"};
  flex-wrap: wrap;
  gap: 16px;
  left: 0;
  margin-top: 24px;
  padding: 0;
  width: 100%;
`;

export default function FilterGroup({
  id,
  type,
  availableFilters,
  selectFilter,
  activeFilters,
  activeFilterGroup,
  filterItemParentNames
}) {
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
      isActive={isActive}
      role="tabpanel"
    >
      {
        availableFiltersForType.map(filterItem => {
          return (
            <Filter
              filterItem={filterItem}
              selectFilter={selectFilter}
              activeFilters={activeFiltersForType}
              filterItemParentNames={filterItemParentNames}
              key={`${filterItem.type}:${filterItem.id}`}
            />
          );
        })
      }
    </StyledFilterGroup>
  );
};
