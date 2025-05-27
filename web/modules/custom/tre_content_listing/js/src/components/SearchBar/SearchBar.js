import React, { useState } from "react";
import {
  SearchBarWrapper,
  Label,
  InputWrapper,
  Input,
  SearchButton,
} from "./SearchBar.styles";

const SearchBar = ({ filterTerms, searchQuery, setSearchQuery }) => {
  const handleKeyDown = (event) => {
    if (event.key === "Enter") {
      filterTerms(searchQuery);
    }
  };

  const handleInputChange = (event) => {
    event.preventDefault();
    setSearchQuery(event.target.value);
    filterTerms(event.target.value);
  };

  return (
    <SearchBarWrapper>
      <Label>{Drupal.t("Search terms")}</Label>
      <InputWrapper>
        <Input
          value={searchQuery}
          onKeyDown={handleKeyDown}
          onChange={handleInputChange}
        />
        <SearchButton
          aria-label={Drupal.t("Search")}
          onClick={() => filterTerms(searchQuery)}
        />
      </InputWrapper>
    </SearchBarWrapper>
  );
};

export default SearchBar;
