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
  LetterCell,
} from "./AccordionList.styles";

const AccordionList = ({ subTerms, queryParams }) => {
  const [openTermIds, setOpenTermIds] = useState([]);
  const [accordionContents, setAccordionContents] = useState({});

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

      // Fetch content for the opened accordion.
      const nodes = await fetchTermNodes(termId);
      setAccordionContents((prev) => ({
        ...prev,
        [termId]: nodes.items.sort((a, b) => a.title.localeCompare(b.title)),
      }));
    }
  }

  // When query parameters change, refresh data for open accordions.
  useEffect(() => {
    async function refreshOpenAccordions() {
      // For each open term, re-fetch the nodes using the new filters.
      openTermIds.forEach(async (termId) => {
        const nodes = await fetchTermNodes(termId);
        setAccordionContents((prev) => ({
          ...prev,
          [termId]: nodes.items.sort((a, b) => a.title.localeCompare(b.title)),
        }));
      });
    }
    if (openTermIds.length > 0) {
      refreshOpenAccordions();
    }
  }, [queryParams]);

  async function fetchTermNodes(termId) {
    const url = `${API_URL}/${termId}/sub-term-items?${queryParams}`;
    const response = await fetch(url);
    const data = await response.json();
    return data;
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
    a.localeCompare(b)
  );

  return (
    <AccordionContainer>
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
                <Row key={term.term_id}>
                  <LetterCell>{showLetter ? letter : ""}</LetterCell>

                  <AccordionItem>
                    <AccordionHeading
                      isActive={isActive}
                      onClick={() => toggleAccordion(term)}
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
                            ? Drupal.t(
                                "Close",
                                {},
                                { context: "accordion toggle" }
                              )
                            : Drupal.t(
                                "Open",
                                {},
                                { context: "accordion toggle" }
                              )}
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
                          accordionContents[term.term_id] &&
                          accordionContents[term.term_id].map((item, i) => (
                            <ContentListItem key={i}>
                              <NodeAccordion node={item} />
                            </ContentListItem>
                          ))}
                      </ContentList>
                    </AccordionContent>
                  </AccordionItem>
                </Row>
              );
            })}
          </LetterGroupWrapper>
        );
      })}
    </AccordionContainer>
  );
};

export default AccordionList;
