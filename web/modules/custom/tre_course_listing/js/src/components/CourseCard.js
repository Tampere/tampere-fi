import React from "react";
import styled from "styled-components";

const StyledCourseCard = styled.li`
  border: 2px solid var(--color-primary);
  display: flex;
  flex-direction: column;
  list-style: none;
  padding: 20px;
`;

const Title = styled.h3`
  font-family: var(--font-family-heading);
  font-size: var(--font-size-18);
  hyphens: auto;
  line-height: 1.1;
  margin-top: 8px;
  margin-bottom: 8px;
  word-break: break-word;

  @media screen and (min-width: 61.56rem) {
    font-size: var(--font-size-24);
  }
`;

const DateContainer = styled.span`
  background-image: url('/modules/custom/tre_course_listing/js/src/icons/calendar.svg');
  background-position: left center;
  background-repeat: no-repeat;
  display: block;
  font-size: var(--font-size-16);
  margin-top: 12px;
  margin-bottom: 12px;
  padding-left: 40px;

  @media screen and (min-width: 61.56rem) {
    font-size: var(--font-size-18);
  }
`;

const Department = styled.span`
  display: block;
  color: var(--color-primary);
  font-weight: bold;
  font-size: var(--font-size-18);
`;

const Availability = styled.span`
  align-items: center;
  display: flex;
  font-size: var(--font-size-16);
  margin-bottom: 24px;

  @media screen and (min-width: 61.56rem) {
    font-size: var(--font-size-18);
  }

  &::before {
    background-color: ${props => props.isCourseFull ? "var(--color-full)" : "var(--color-available)"};
    border-radius: 100%;
    content: "";
    display: inline-block;
    flex-shrink: 0;
    height: 20px;
    margin-right: 20px;
    width: 20px;
  }
`;

const PageLink = styled.a`
  align-items: center;
  color: var(--color-primary);
  display: flex;
  font-size: var(--font-size-15);
  line-height: 1.12;
  margin-top: auto;
  max-width: 80%;
  text-decoration: none;
  text-transform: uppercase;

  &:focus,
  &:hover {
    text-decoration: underline;
    text-decoration-thickness: 2px;
  }

  @media screen and (min-width: 61.56rem) {
    font-size: var(--font-size-18);
  }
`;

const LinkText = styled.span`
  font-family: var(--font-family-heading);
  margin-right: 16px;
`;

const LinkIcon = styled.span`
  background-image: url('/modules/custom/tre_course_listing/js/src/icons/external-link.svg');
  background-position: right center;
  background-repeat: no-repeat;
  flex-shrink: 0;
  height: 25px;
  width: 25px;
`;

export default function CourseCard({
  full: isCourseFull,
  catalogitems: catalogItems,
  begins: startDate,
  ends: endDate,
  name: courseName,
  courseUrl
}) {
  const departments = catalogItems.filter(item => item.type === "department");
  const departmentNames = departments.map(item => item.name).join(", ");

  const getLocalizedDate = (date) => (locale) => {
    const dateTime = new Date(date);
    return dateTime.toLocaleDateString(locale);
  };

  const locale = "fi-FI";
  const localizedStartDate = getLocalizedDate(startDate)(locale);
  const localizedEndDate = getLocalizedDate(endDate)(locale);

  return (
    <StyledCourseCard>
      <Department>
        { departmentNames }
      </Department>
      { courseName && <Title>{ courseName }</Title> }
      <DateContainer>
        { localizedStartDate } - { localizedEndDate }
      </DateContainer>
      <Availability isCourseFull={isCourseFull}>
        { isCourseFull ?
          Drupal.t("The course is fully booked") :
          Drupal.t("There are available places on the course")
        }
      </Availability>
      <PageLink href={courseUrl} target="_blank">
        <LinkText>{ Drupal.t("Additional information and registration") }</LinkText>
        <span className="visually-hidden rs_skip">{ Drupal.t("Opens in new window") }</span>
        <LinkIcon aria-hidden="true" role="presentation" />
      </PageLink>
    </StyledCourseCard>
  );
};
