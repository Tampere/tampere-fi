import React, { useState, useEffect } from "react";
import {
  StyledFilters,
  FilterGroupLabelsContainer,
  StyledFilterGroup,
  StyledFilterList,
  Label,
  StyledFilterGroupLabel,
  StyledFilter,
  StyledCheckbox,
  StyledLabel,
  CloseIcon,
  Count,
} from "./FilterGroupMenu.styles";

const FilterGroupMenu = ({ filterValues, onFilterChange, selectedFilters }) => {
  const [activeGroupIndex, setActiveGroupIndex] = useState(null);

  const toggleFacetAccordion = (index) => {
    setActiveGroupIndex(activeGroupIndex === index ? null : index);
  };

  // Whenever the user checks/unchecks a filter, update filter selection accordingly.
  const handleCheckboxChange = (label, option) => {
    const groupKey = label.toLowerCase();

    const updatedFilters = { ...selectedFilters };

    // Intialize the filter group array if it doesnt exist.
    if (!updatedFilters[groupKey]) {
      updatedFilters[groupKey] = [];
    }

    if (updatedFilters[groupKey].includes(option)) {
      // Remove the filter option if its already selected.
      updatedFilters[groupKey] = updatedFilters[groupKey].filter(
        (item) => item !== option,
      );
    } else {
      // Add the option.
      updatedFilters[groupKey] = [...updatedFilters[groupKey], option];
    }

    // Call the parents update filter handler.
    onFilterChange(updatedFilters);
  };

  return (
    <StyledFilters>
      {filterValues.length > 0 && (
        <StyledFilterGroup>
          <FilterGroupLabelsContainer>
            {filterValues.map((filterGroup, index) => {
              const isActive = activeGroupIndex === index;

              const groupKey = filterGroup.label.toLowerCase();
              const selectedCount = selectedFilters[groupKey]?.length || 0;

              return (
                <StyledFilterGroupLabel
                  key={filterGroup.id}
                  isActive={isActive}
                  onClick={() => toggleFacetAccordion(index)}
                >
                  <Label isActive={isActive}>{filterGroup.label}</Label>

                  {selectedCount > 0 && <Count>{selectedCount}</Count>}
                </StyledFilterGroupLabel>
              );
            })}
          </FilterGroupLabelsContainer>

          {activeGroupIndex !== null && (
            <StyledFilterList isActive={activeGroupIndex !== null}>
              {filterValues[activeGroupIndex].content.map(
                (option, optIndex) => {
                  const groupLabel =
                    filterValues[activeGroupIndex].label.toLowerCase();
                  const isSelected =
                    selectedFilters[groupLabel]?.includes(option);

                  return (
                    <StyledFilter key={optIndex} isActive={isSelected}>
                      <StyledLabel isActive={isSelected}>
                        <StyledCheckbox
                          type="checkbox"
                          checked={isSelected || false}
                          onChange={() =>
                            handleCheckboxChange(
                              filterValues[activeGroupIndex].label,
                              option,
                            )
                          }
                        />
                        {option}

                        {isSelected && <CloseIcon />}
                      </StyledLabel>
                    </StyledFilter>
                  );
                },
              )}
            </StyledFilterList>
          )}
        </StyledFilterGroup>
      )}
    </StyledFilters>
  );
};

export default FilterGroupMenu;
