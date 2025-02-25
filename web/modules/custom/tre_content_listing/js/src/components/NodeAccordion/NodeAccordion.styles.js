import styled, { css } from "styled-components";

export const Title = styled.span`
  margin-top: 8px;
  margin-bottom: 3px;
  margin-left: 12px
  word-break: break-word;
`;

export const PageLink = styled.a`
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 var(--space-xs-plus);
  text-decoration: none;
  color: var(--text);
  border-bottom: 2px solid #ccc;

  &:focus,
  &:hover {
    ${LinkText} {
      text-decoration: underline;
      text-underline-offset: 3px;
    }
  }
`;

export const LinkText = styled.span`
  flex: 1;
  font-family: var(--font-heading);
  font-size: var(--font-size-18);
  word-wrap: break-word;
  margin: 16px;

  @media screen and (min-width: 61.56rem) {
    font-size: var(--font-size-20);
  }
`;

export const LinkIcon = styled.svg.attrs({
  "aria-hidden": "true",
  role: "presentation",
  className: "attachment-list__icon rs_skip",
})`
  flex-shrink: 0;
  margin: 0 16px;
  height: 24px;
  width: 24px;
`;

export const NodeAccordionContainer = styled.div`
  border: ${({ open }) =>
    open ? css`1.5px solid var(--color-primary)` : css`none`};
`;

export const NodeHeader = styled.div`
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  hover: underline;
  padding: 0.8rem;
`;

export const ToggleIcon = styled.svg.attrs({
  "aria-hidden": "true",
  role: "presentation",
  className: "sidebar-menu__icon rs_skip",
})`
  height: 20px;
  width: 20px;
  transform: ${({ isActive }) =>
    isActive ? "rotate(0deg)" : "rotate(180deg)"};
`;

export const NodeDetails = styled.div`
  padding: 16px;
  margin-top: 8px;
  background-color: #fff;
  border-top: 1px solid #ddd;
`;

export const NodeTextBody = styled.div`
  padding-top: 1.5rem;
  padding-bottom: 3rem;
`;

export const NodeImage = styled.div`
  margin-bottom: 8px;
  padding-top: 1.5rem;
  padding-bottom: 1.5rem;
`;
