import styled from "styled-components";

export const SearchBarWrapper = styled.div`
  margin: 32px 0;
`;

export const Label = styled.label`
  font-size: var(--font-size-16);
  font-weight: bold;

  @media screen and (min-width: 61.56rem) {
    font-size: var(--font-size-20);
  }
`;

export const InputWrapper = styled.span`
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

export const Input = styled.input`
  border: 0;
  font-family: var(--font-family-heading);
  padding: 16px;
  width: 100%;

  @media screen and (min-width: 61.56rem) {
    width: auto;
  }
`;

export const SearchButton = styled.button`
  background-color: var(--color-secondary);
  background-image: url("/modules/custom/tre_content_listing/js/src/assets/icons/search.svg");
  background-position: center;
  background-repeat: no-repeat;
  border: 0;
  color: #fff;
  cursor: pointer;
  height: 100%;
  padding: 8px;
  width: 55px;

  &:focus,
  &:hover {
    background-color: #fff;
    background-image: url("/modules/custom/tre_content_listing/js/src/assets/icons/search-inverted.svg");
    border-left: 2px solid var(--color-secondary);
    color: var(--color-secondary);
  }
`;
