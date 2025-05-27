import styled, { css } from "styled-components";

const large = "@media (min-width: 992px)";

export const AccordionContainer = styled.div``;

export const AccordionItem = styled.div`
  display: inline-block;
  margin-bottom: var(--space-m);
  width: 100%;

  ${large} {
    margin-bottom: var(--space-m);
  }
`;

export const AccordionHeading = styled.button`
  background: none;
  border: 2px solid var(--color-accent-secondary, #f1eeeb);
  color: var(--color-text, #000);
  font-family: var(
    --font-family-heading,
    "Montserrat",
    "Helvetica",
    "Arial",
    sans-serif
  );
  font-size: var(--font-size-16, 1rem);
  cursor: pointer;
  width: 100%;
  padding: var(--space-m-plus, 24px) 0;
  display: flex;
  justify-content: space-between;
  text-align: left;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.5rem;

  @media screen and (min-width: 24.375rem) {
    flex-direction: row;
    align-items: center;
    gap: unset;
  }

  ${large} {
    font-size: var(--font-size-20, 1.25rem);
  }

  ${(props) =>
    props.isActive &&
    css`
      border-color: var(--color-primary, #22437b);
      box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.2);
      font-weight: var(--font-weight-bold, 700);

      ${large} {
        font-size: var(--accordion-desktop-active-font-size, 1.25rem);
        box-shadow: 0 6px 4px 0 rgba(0, 0, 0, 0.1);
      }
    `}

  &:hover,
  &:focus {
    border-color: var(--color-primary, #22437b);
    outline: none;
  }
`;

export const AccordionTitleWrapper = styled.span`
  margin: 0;
`;

export const AccordionTitle = styled.span`
  display: inline-block;
  white-space: normal;
  margin: 0 var(--space-s, 8px);
  max-width: var(--text-content-max-width, 100%);

  ${large} {
    margin: 0 var(--space-l, 24px);
  }
`;

export const AccordionArrow = styled.span`
  display: inline-block;
  width: 20px;
  height: 10px;
  margin-right: 8px;

  background-color: var(--color-primary, #22437b);

  -webkit-mask-image: url("/modules/custom/tre_content_listing/js/src/assets/icons/arrow-plain-new.svg");
  -webkit-mask-repeat: no-repeat;
  -webkit-mask-size: contain;

  mask-image: url("/modules/custom/tre_content_listing/js/src/assets/icons/arrow-plain-new.svg");
  mask-repeat: no-repeat;
  mask-size: contain;

  transform: ${({ isActive }) =>
    isActive ? "rotate(0deg)" : "rotate(180deg)"};
`;

export const AccordionIconWrapper = styled.span`
  color: var(--color-primary, #22437b);

  display: inline-flex;
  align-items: center;
  white-space: nowrap;
  margin-right: 1rem;
  margin-left: 8px;

  @media screen and (min-width: 24.375rem) {
    margin-left: unset;
  }
`;

export const AccordionIconText = styled.span`
  color: var(--color-primary, #22437b);
  height: var(--accordion-icon-height, 10px);
  width: var(--accordion-icon-width, 20px);

  ${(props) =>
    !props.isActive &&
    css`
      &.accordion__icon-text--close {
        display: none;
      }

      &.accordion__icon-text--open {
        display: inline-block;
      }
    `}

  ${(props) =>
    props.isActive &&
    css`
      &.accordion__icon-text--close {
        display: inline-block;
      }

      &.accordion__icon-text--open {
        display: none;
      }
    `}
`;

export const AccordionContent = styled.div`
  display: ${(props) => (props.isActive ? "block" : "none")};
  margin: var(--accordion-content-vertical-margin, 8px)
    var(--accordion-content-horizontal-margin, 4px);

  ${large} {
    margin: var(--accordion-content-margin--large, 24px);
  }

  > :first-child {
    margin-top: 0;
  }
`;

export const ContentList = styled.ul`
  list-style: none;
  padding: 0;
  margin-top: 0;
`;

export const ContentListItem = styled.li`
  border-bottom: 2px solid var(--clr-border, #f1eeeb);
  margin-bottom: 1rem;
  margin-top: 1rem;
  padding-bottom: 0.2rem;

  &:last-child {
    margin-bottom: 0;
  }
`;

export const ContentLink = styled.a`
  text-decoration: none;
  color: var(--clr-text, #000);
  display: flex;
  flex-direction: column;
  padding: 0 var(--space-xs-plus, 12px);

  &:focus,
  &:hover {
    text-decoration: underline;
    text-underline-offset: 3px;
  }
`;

export const LetterGroupWrapper = styled.div`
  position: relative;
  margin-bottom: var(--space-m, 16px);

  &:before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 1rem;
    border-left: 2px solid var(--color-primary, #22437b);
  }

  @media (max-width: 768px) {
    position: static;
    &:before {
      content: none;
    }
  }
`;

export const LetterRow = styled.div`
  display: flex;
  margin-left: 1rem;
  margin-bottom: 0.5rem;

  @media (max-width: 768px) {
    margin-left: 0;
    flex-direction: column;
  }
`;

export const Row = styled.div`
  display: flex;
  margin-bottom: 0.5rem;

  @media (max-width: 768px) {
    margin-left: 0;
    flex-direction: column;
  }
`;

export const LetterCell = styled.div`
  width: 1.5rem;
  font-weight: bold;
  color: var(--color-primary, #22437b);
  margin-right: 5rem;

  @media (max-width: 768px) {
    width: 100%;
    margin-right: 0;
    text-align: left;
    margin-bottom: 0.5rem;
    padding-bottom: 0.5rem;
    display: block;
  }
`;
