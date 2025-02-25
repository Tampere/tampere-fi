import styled from "styled-components";

export const StyledPager = styled.nav`
  align-items: center;
  display: flex;
  font-family: var(--font-family-heading);
  margin-top: 32px;
  margin-bottom: 32px;
`;

export const Pagination = styled.ul`
  align-items: center;
  display: flex;
  justify-content: center;
  list-style: none;
  margin: 0;
  padding: 0;
  width: 100%;
`;

export const InactiveDirection = styled.span`
  @media screen and (max-width: 61.56rem) {
    position: relative;

    &,
    &::before {
      height: 32px;
      width: 32px;
    }

    &::before {
      background-image: url("/modules/custom/tre_course_listing/js/src/icons/arrow-inactive.svg");
      background-position: center;
      background-repeat: no-repeat;
      content: "";
      left: 0;
      position: absolute;
      top: 0;
      transform: scaleX(-1);
    }
  }
`;

export const InactiveDirectionRight = styled(InactiveDirection)`
  &::before {
    transform: scaleX(1);
  }
`;

export const PagerButton = styled.button`
  background-color: transparent;
  border: none;
  box-shadow: none;
  color: var(--color-primary);
  cursor: pointer;
  padding: 0;
  text-transform: uppercase;

  &:hover {
    text-decoration: underline;
  }
`;

export const PagerPrevButton = styled(PagerButton)`
  position: relative;

  @media screen and (min-width: 61.56rem) {
    margin-right: 40px;
    padding-left: 40px;
  }

  &,
  &::before {
    min-height: 32px;
    min-width: 32px;
  }

  &::before {
    background-image: url("/modules/custom/tre_course_listing/js/src/icons/arrow.svg");
    background-position: center;
    background-repeat: no-repeat;
    content: "";
    left: 0;
    position: absolute;
    top: 0;
    transform: scaleX(-1);
  }
`;

export const PagerNextButton = styled(PagerPrevButton)`
  margin-right: 0;
  padding-left: 0;

  @media screen and (min-width: 61.56rem) {
    margin-left: 40px;
    padding-right: 40px;
  }

  &::before {
    left: auto;
    right: 0;
    transform: scaleX(1);
  }
`;

export const DirectionText = styled.span`
  @media screen and (max-width: 61.56rem) {
    position: absolute !important;
    clip: rect(1px, 1px, 1px, 1px);
    overflow: hidden;
    height: 1px;
    width: 1px;
    word-wrap: normal;
  }
`;

export const PagerItem = styled.li`
  color: var(--color-primary);
  display: flex;
  font-size: var(--font-size-18);
  margin: 0;

  @media screen and (max-width: 61.56rem) {
    &:first-child {
      margin-left: auto;
      margin-right: 0;
    }

    &:last-child {
      margin-left: 0;
      margin-right: auto;
    }
  }

  @media screen and (min-width: 61.56rem) {
    margin: 0 8px;
  }
`;

export const DirectionPagerItem = styled(PagerItem)`
  @media screen and (max-width: 61.56rem) {
    &:first-child {
      margin-left: 0;
      margin-right: auto;
    }

    &:last-child {
      margin-left: auto;
      margin-right: 0;
    }
  }
`;

export const PageNumberButton = styled(PagerButton)`
  font-size: var(--font-size-20);
  font-weight: ${(props) => (props.isActive ? "800" : "normal")};
  margin: 0 8px;
  padding: 8px;
  text-decoration: ${(props) => (props.isActive ? "underline" : "none")};
`;
