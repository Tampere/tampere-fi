import React, { useState, useEffect } from "react";
import { API_URL } from "../../lib/constants";
import NodeAccordion from "../NodeAccordion/NodeAccordion";
import {
  AccordionContainer,
  AccordionItem,
  AccordionHeading,
  AccordionTitleWrapper,
  AccordionTitle,
  AccordionIconWrapper,
  AccordionContent,
  ContentLink,
  ContentList,
  ContentListItem,
  LetterGroupWrapper,
  AccordionArrow,
  Row,
  LetterRow,
  LetterCell,
} from "./AccordionList.styles";

const AccordionItemComponent = ({
  isActive,
  term,
  controlId,
  contentId,
  onToggle,
}) => {
  return (
    <AccordionItem>
      <AccordionHeading
        isActive={isActive}
        onClick={() => onToggle(term)}
        aria-expanded={isActive}
        aria-controls={contentId}
        id={controlId}
        title={term.term_name}
      >
        <AccordionTitleWrapper>
          <AccordionTitle>{term.term_name}</AccordionTitle>
        </AccordionTitleWrapper>
        <AccordionIconWrapper>
          <AccordionArrow isActive={isActive} />
          <span>
            {isActive
              ? Drupal.t("Close", {}, { context: "accordion toggle" })
              : Drupal.t("Open", {}, { context: "accordion toggle" })}
          </span>
        </AccordionIconWrapper>
      </AccordionHeading>
      <AccordionContent
        isActive={isActive}
        aria-labelledby={controlId}
        role="region"
      >
        <ContentList>
          {isActive &&
            term.items &&
            term.items.map((item, i) => (
              <ContentListItem key={i}>
                <NodeAccordion node={item} />
              </ContentListItem>
            ))}
        </ContentList>
      </AccordionContent>
    </AccordionItem>
  );
};

const AccordionList = ({ subTerms, disableLetterGrouping }) => {
  const [openTermIds, setOpenTermIds] = useState([]);

  async function toggleAccordion(term) {
    const termId = term.term_id;
    const isActive = openTermIds.includes(termId);

    if (isActive) {
      // User clicked an open accordion, so we filter the clicked
      // one out from open indexes.
      setOpenTermIds((prev) => prev.filter((i) => i !== termId));
    } else {
      // Otherwise we append the clicked accordion to open indexes.
      setOpenTermIds((prev) => [...prev, termId]);
    }
  }

  // Helper function to group terms by their first letter.
  function groupByFirstLetter(terms) {
    const grouped = {};
    terms.forEach((term) => {
      const firstLetter = term.term_name?.charAt(0).toUpperCase() || "";
      if (!grouped[firstLetter]) {
        grouped[firstLetter] = [];
      }
      grouped[firstLetter].push(term);
    });
    return grouped;
  }

  const groupedTerms = groupByFirstLetter(subTerms);
  const sortedLetters = Object.keys(groupedTerms).sort((a, b) =>
    a.localeCompare(b),
  );

  return (
    <AccordionContainer>
      {disableLetterGrouping ? (
        <>
          {subTerms.map((term) => {
            const isActive = openTermIds.includes(term.term_id);
            const controlId = `accordion-control-${term.term_id}`;
            const contentId = `accordion-content-${term.term_id}`;

            return (
              <Row key={term.term_id}>
                <AccordionItemComponent
                  term={term}
                  isActive={isActive}
                  controlId={controlId}
                  contentId={contentId}
                  onToggle={toggleAccordion}
                />
              </Row>
            );
          })}
        </>
      ) : (
        <>
          {sortedLetters.map((letter) => {
            const termsForLetter = groupedTerms[letter] || [];

            return (
              <LetterGroupWrapper key={letter}>
                {termsForLetter.map((term, idx) => {
                  const showLetter = idx === 0;
                  const isActive = openTermIds.includes(term.term_id);

                  const controlId = `accordion-control-${term.term_id}`;
                  const contentId = `accordion-content-${term.term_id}`;

                  return (
                    <LetterRow key={term.term_id}>
                      <LetterCell>{showLetter ? letter : ""}</LetterCell>
                      <AccordionItemComponent
                        term={term}
                        isActive={isActive}
                        controlId={controlId}
                        contentId={contentId}
                        onToggle={toggleAccordion}
                      />
                    </LetterRow>
                  );
                })}
              </LetterGroupWrapper>
            );
          })}
        </>
      )}
    </AccordionContainer>
  );
};

export default AccordionList;
