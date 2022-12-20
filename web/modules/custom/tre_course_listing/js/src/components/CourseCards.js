import React from "react";
import styled from "styled-components";

import CourseCard from "./CourseCard";

const StyledCourseCards = styled.ul`
  display: grid;
  gap: 20px;
  grid-auto-rows: 1fr;
  grid-template-columns: 1fr;
  margin-top: 32px;
  margin-bottom: 32px;
  padding: 0;

  @media screen and (min-width: 30rem) {
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
  }
`;

export default function CourseCards({ items }) {
  return (
    <StyledCourseCards aria-live="polite">
      {
        items.map(item => (
          <CourseCard key={item.id} {...item} />
        ))
      }
    </StyledCourseCards>
  );
};
