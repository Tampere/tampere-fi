import React, { useState, useEffect } from "react";
import {
  StyledFilters,
  FilterGroupLabelsContainer,
  ResetButton,
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

const FilterGroupMenu = ({ filterValues, onFilterChange }) => {
  const [activeGroupIndex, setActiveGroupIndex] = useState(null);
  const [selectedFilters, setSelectedFilters] = useState({});

  const toggleFacetAccordion = (index) => {
    setActiveGroupIndex(activeGroupIndex === index ? null : index);
  };

  const clearAllFilters = () => {
    setSelectedFilters({});
    setActiveGroupIndex(null);
  };

  // Whenever the user checks/unchecks a filter, update filter selection accordingly.
  const handleCheckboxChange = (label, option) => {
    const groupKey = label.toLowerCase();
    setSelectedFilters((prev) => {
      const groupFilters = prev[groupKey] || [];
      let updatedFilters;

      if (groupFilters.includes(option)) {
        // If filter is already selected, remove it
        updatedFilters = {
          ...prev,
          [groupKey]: groupFilters.filter((item) => item !== option),
        };
      } else {
        // Otherwise, add it
        updatedFilters = {
          ...prev,
          [groupKey]: [...groupFilters, option],
        };
      }

      return updatedFilters;
    });
  };

  // Whenever selectedFilters is updated, we call onFilterChange
  // to inform the parent component.
  useEffect(() => {
    onFilterChange(selectedFilters);
  }, [selectedFilters]);

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
                              option
                            )
                          }
                        />
                        {option}

                        {isSelected && <CloseIcon />}
                      </StyledLabel>
                    </StyledFilter>
                  );
                }
              )}
            </StyledFilterList>
          )}
        </StyledFilterGroup>
      )}

      {Object.keys(selectedFilters).length > 0 && (
        <ResetButton onClick={clearAllFilters}>
          {Drupal.t("Remove filters")}
        </ResetButton>
      )}
    </StyledFilters>
  );
};

export default FilterGroupMenu;
