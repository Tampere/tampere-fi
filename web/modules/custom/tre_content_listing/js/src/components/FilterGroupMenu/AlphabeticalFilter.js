import React from "react";
import {
  StyledFilters,
  StyledFilterGroup,
  StyledFilterList,
  StyledAlphabeticalFilter,
  StyledAlphabeticalLabel,
  StyledCheckbox,
  CloseIconAlphabetical,
} from "./FilterGroupMenu.styles";

const AlphabeticalFilter = ({
  onFilterChange,
  selectedLetters,
  alphabeticalFilters,
}) => {
  const handleCheckboxChange = (letter) => {
    let updatedLetters;

    if (selectedLetters.includes(letter)) {
      updatedLetters = selectedLetters.filter(
        (selected) => selected !== letter,
      );
    } else {
      updatedLetters = [...selectedLetters, letter];
    }

    if (updatedLetters.length === 0) {
      onFilterChange({});
      return;
    }

    onFilterChange({ letter: updatedLetters });
  };

  return (
    <StyledFilters>
      <StyledFilterGroup>
        <StyledFilterList isActive={true}>
          {alphabeticalFilters?.size > 0 &&
            Array.from(alphabeticalFilters).map((letter, index) => {
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
    </StyledFilters>
  );
};

export default AlphabeticalFilter;
