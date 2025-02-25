import styled from "styled-components";

export const StyledFilters = styled.div``;

export const FilterGroupLabelsContainer = styled.div`
  column-gap: 16px;
  display: flex;
  flex-wrap: wrap;
  font-family: var(--font-family-heading);
  row-gap: 16px;

  @media screen and (min-width: 61.56rem) {
    column-gap: 0;
  }
`;

export const ResetButton = styled.button`
  background-color: transparent;
  border: none;
  padding: 0;
  color: #22437b;
  cursor: pointer;
  display: block;
  text-underline-offset: 2px;
  text-decoration-thickness: 1px;
  margin: 32px 0;
  text-decoration: underline;

  &:focus,
  &:hover {
    text-decoration: none;
  }
`;

export const StyledFilterGroup = styled.div`
  margin-top: 24px;
  width: 100%;
`;

export const StyledFilterList = styled.ul`
  display: ${(props) => (props.isActive ? "flex" : "none")};
  flex-wrap: wrap;
  gap: 10px;
  padding: 0;
`;

export const Label = styled.span`
  font-weight: 200;
  text-decoration: ${(props) => (props.isActive ? "underline" : "none")};
  word-break: break-word;
`;

export const StyledFilterGroupLabel = styled.button`
  align-items: center;
  background-color: ${(props) =>
    props.isActive ? "var(--color-primary)" : "transparent"};
  border: 2px solid var(--color-primary);
  color: ${(props) => (props.isActive ? "#fff" : "var(--color-primary)")};
  cursor: pointer;
  display: block;
  margin-left: -2px;
  padding: 12px 20px;
  text-transform: uppercase;

  @media screen and (min-width: 61.56rem) {
    padding: 20px 28px;
  }

  &:focus,
  &:hover {
    ${Label} {
      text-decoration: underline;
    }
  }
`;

export const StyledFilter = styled.li`
  align-items: center;
  background-color: ${(props) =>
    props.isActive ? "var(--color-primary)" : "transparent"};
  border: 1.5px solid var(--color-primary);
  border-radius: 35px;
  color: ${(props) => (props.isActive ? "#fff" : "var(--color-primary)")};
  cursor: pointer;
  display: inline-flex;
  font-family: var(--font-family-heading);
  font-size: var(--font-size-15);
  text-decoration: ${(props) => (props.isActive ? "underline" : "none")};
  position: relative;

  &:focus-within {
    outline: 1px solid var(--color-primary);
    outline-offset: 3px;
    text-decoration: underline;
  }

  &:hover {
    text-decoration: ${(props) => (props.isActive ? "none" : "underline")};
  }

  @media screen and (min-width: 61.56rem) {
    font-size: var(--font-size-16);
  }
`;

export const StyledCheckbox = styled.input`
  cursor: pointer;
  opacity: 0;
  position: absolute;
  top: 0;
  left: 0;
  height: 1rem;
  width: 1rem;
`;

export const StyledLabel = styled.label`
  cursor: pointer;
  padding-top: 8px;
  padding-right: ${(props) => (props.isActive ? "50px" : "25px")};
  padding-bottom: 8px;
  padding-left: 24px;
`;

export const CloseIcon = styled.span`
  background-image: url("/modules/custom/tre_content_listing/js/src/assets/icons/close.svg");
  background-position: center;
  background-repeat: no-repeat;
  height: 22px;
  position: absolute;
  pointer-events: none;
  right: 13px;
  width: 22px;
  top: 11px;
`;

export const Count = styled.span`
  background-color: var(--color-count);
  border: 1px solid var(--color-accent-secondary);
  border-radius: 32px;
  color: #fff;
  display: inline-block;
  font-size: var(--font-size-18);
  margin-left: 32px;
  min-width: 30px;
  padding: 4px 10px;
`;

export const StyledAlphabeticalFilter = styled.li`
  align-items: center;
  background-color: ${(props) =>
    props.isActive ? "var(--color-primary)" : "transparent"};
  border: 1.5px solid var(--color-primary);
  border-radius: 35px;
  color: ${(props) => (props.isActive ? "#fff" : "var(--color-primary)")};
  cursor: pointer;
  display: inline-flex;
  font-family: var(--font-family-heading);
  font-size: var(--font-size-18);
  text-decoration: ${(props) => (props.isActive ? "underline" : "none")};
  position: relative;

  width: ${(props) => (props.isActive ? "auto" : "40px")};
  height: 40px;
  justify-content: center;

  &:focus-within {
    outline: 1px solid var(--color-primary);
    outline-offset: 3px;
  }

  &:hover {
    text-decoration: ${(props) => (props.isActive ? "none" : "underline")};
  }

  @media screen and (min-width: 61.56rem) {
    font-size: var(--font-size-20);
    width: ${(props) => (props.isActive ? "auto" : "45px")};
    height: 45px;
  }
`;

export const StyledAlphabeticalLabel = styled.label`
  cursor: pointer;
  padding-top: 10px;
  padding-right: ${(props) => (props.isActive ? "72px" : "10px")};
  padding-bottom: 10px;
  padding-left: ${(props) => (props.isActive ? "16px" : "10px")};
`;

export const CloseIconAlphabetical = styled.span`
  background-image: url("/modules/custom/tre_content_listing/js/src/assets/icons/close.svg");
  background-position: center;
  background-repeat: no-repeat;
  height: 24px;
  position: absolute;
  pointer-events: none;
  right: 13px;
  width: 24px;
  top: 10px;
`;
