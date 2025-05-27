import React, { forwardRef, useId } from "react";
import styled from "styled-components";

const StyledSearchBar = styled.div`
  margin: 32px 0;
`;

const Label = styled.label`
  font-size: var(--font-size-16);
  font-weight: bold;

  @media screen and (min-width: 61.56rem) {
    font-size: var(--font-size-20);
  }
`;

const InputWrapper = styled.span`
  border: 2px solid var(--color-secondary);
  display: flex;
  flex-wrap: nowrap;
  height: 55px;
  margin-top: 16px;
  width: 100%;

  @media screen and (min-width: 61.56rem) {
    margin-top: 24px;
    width: fit-content;
  }
`;

const Input = styled.input`
  border: 0;
  font-family: var(--font-family-heading);
  padding: 16px;
  width: 100%;

  @media screen and (min-width: 61.56rem) {
    width: auto;
  }
`;

const SearchButton = styled.button`
  background-color: var(--color-secondary);
  background-image: url('/modules/custom/tre_course_listing/js/src/icons/search.svg');
  background-position: center;
  background-repeat: no-repeat;
  border: 0;
  color: #fff;
  cursor: pointer;
  height: 100%;
  padding: 8px;
  width: 55px;

  &:focus {
    color: var(--color-secondary);
  }

  &:hover {
    background-color: var(--color-blue-600);
  }
`;

const SearchBar = forwardRef((props, ref) => {
  const {
    filterItems
  } = props;

  const id = useId();

  const handleKeyDown = event => {
    if (event.key === "Enter") {
      filterItems();
    }
  };

  return (
    <StyledSearchBar>
      <Label htmlFor={id}>
        { Drupal.t("Search courses by keyword") }
      </Label>
      <InputWrapper>
        <Input id={id} ref={ref} onKeyDown={handleKeyDown} />
        <SearchButton aria-label={ Drupal.t("Search") } onClick={filterItems} />
      </InputWrapper>
    </StyledSearchBar>
  );
});

export default SearchBar;
