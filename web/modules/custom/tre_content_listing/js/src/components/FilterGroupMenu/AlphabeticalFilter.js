import React, { useEffect, useState } from "react";
import {
  StyledFilters,
  StyledFilterGroup,
  StyledFilterList,
  StyledAlphabeticalFilter,
  StyledAlphabeticalLabel,
  StyledCheckbox,
  ResetButton,
  CloseIconAlphabetical,
} from "./FilterGroupMenu.styles";
import { ALPHABETICAL_FILTERS } from "../../lib/constants";

const AlphabeticalFilter = ({ onFilterChange }) => {
  const [selectedLetters, setSelectedLetters] = useState([]);

  useEffect(() => {
    // Set letter as the group key and update parent component
    const letterFilter = { letter: selectedLetters };

    onFilterChange(letterFilter);
  }, [selectedLetters]);

  const clearAllFilters = () => {
    setSelectedLetters([]);
  };

  const handleCheckboxChange = (letter) => {
    if (selectedLetters.includes(letter)) {
      const updatedLetters = selectedLetters.filter(
        (selected) => selected !== letter
      );
      setSelectedLetters(updatedLetters);
    } else {
      setSelectedLetters([...selectedLetters, letter]);
    }
  };

  return (
    <StyledFilters>
      <StyledFilterGroup>
        <StyledFilterList isActive={true}>
          {ALPHABETICAL_FILTERS.map((letter, index) => {
            const isSelected = selectedLetters.includes(letter);

            return (
              <StyledAlphabeticalFilter key={index} isActive={isSelected}>
                <StyledAlphabeticalLabel isActive={isSelected}>
                  <StyledCheckbox
                    type="checkbox"
                    checked={isSelected || false}
                    onChange={() => handleCheckboxChange(letter)}
                  />
                  {letter}
                  {isSelected && <CloseIconAlphabetical />}
                </StyledAlphabeticalLabel>
              </StyledAlphabeticalFilter>
            );
          })}
        </StyledFilterList>
      </StyledFilterGroup>

      {selectedLetters.length > 0 && (
        <ResetButton onClick={clearAllFilters}>Remove filters</ResetButton>
      )}
    </StyledFilters>
  );
};

export default AlphabeticalFilter;
