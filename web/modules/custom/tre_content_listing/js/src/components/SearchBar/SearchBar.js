import React, { useState, useId } from "react";
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

  const id = useId();

  const handleInputChange = (event) => {
    event.preventDefault();
    setSearchQuery(event.target.value);
    filterTerms(event.target.value);
  };

  return (
    <SearchBarWrapper>
      <Label htmlFor={id}>{Drupal.t("Search terms")}</Label>
      <InputWrapper>
        <Input
          value={searchQuery}
          onKeyDown={handleKeyDown}
          onChange={handleInputChange}
          placeholder={Drupal.t("Type a search term")}
          id={id}
        />
        <button class="search-bar__button listing-search-bar__button" aria-label={ Drupal.t("Search") } onClick={() => filterTerms(searchQuery)}>
          {/* Search text */}
          <span class="button__text">{Drupal.t("Search")}</span>
          {/* Search button */}
          <svg class="search-bar__icon" aria-hidden="true" role="presentation" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <g clip-path="url(#clip0_8428_1438)">
            <path d="M12.7007 12.7042C15.45 9.95492 15.5323 5.57975 12.8845 2.93199C10.2368 0.284238 5.8616 0.36655 3.11231 3.11584C0.363018 5.86513 0.280706 10.2403 2.92846 12.8881C5.57622 15.5358 9.95139 15.4535 12.7007 12.7042Z" stroke="#FFF" stroke-width="2"/>
            <path d="M13.16 11.5L15.18 13.51L13.51 15.18L11.5 13.16" fill="#FFF"/>
            <path d="M13.16 11.5L15.18 13.51L13.51 15.18L11.5 13.16" stroke="#FFF" stroke-width="2"/>
            <path d="M15.6566 13.0395L13.0332 15.6628L19.878 22.5076L22.5014 19.8843L15.6566 13.0395Z" stroke="#FFF" stroke-width="2"/>
            <path d="M10.8298 4.99001C9.27984 3.44001 6.70984 3.48001 5.08984 5.10001" stroke="#FFF" stroke-width="2"/>
            </g>
            <defs>
            <clipPath id="clip0_8428_1438">
            <rect width="24" height="24" fill="white"/>
            </clipPath>
            </defs>
          </svg>
        </button>
      </InputWrapper>
    </SearchBarWrapper>
  );
};

export default SearchBar;
