import React, { useState, useEffect } from "react";
import { API_URL, FILTER_HELP_TEXT, FILTER_TITLE } from "./lib/constants";
import AccordionList from "./components/AccordionList/AccordionList";
import FilterGroupMenu from "./components/FilterGroupMenu/FilterGroupMenu";
import SearchBar from "./components/SearchBar/SearchBar";
import styled from "styled-components";
import AlphabeticalFilter from "./components/FilterGroupMenu/AlphabeticalFilter";
import Pager from "./components/Pagination/Pager";
import TotalResults from "./components/TotalResults/TotalResults";
import { Label } from "./components/SearchBar/SearchBar.styles";
import { ResetButton } from "./components/FilterGroupMenu/FilterGroupMenu.styles";

const StyledApp = styled.div`
  --color-primary: #22437b;
  --color-secondary: #29549a;
  --color-full: #ae1e20;
  --color-available: #397368;
  --color-count: #ae1e20;
  --color-accent-secondary: #f1eeeb;
  --color-border: #ccc;
  --color-blue-600: #1d3a6c;

  --font-family-body: "Open Sans", "Arial", sans-serif;
  --font-family-heading: "Montserrat", "Helvetica", "Arial", sans-serif;

  --font-size-15: 0.9375rem;
  --font-size-16: 1rem;
  --font-size-18: 1.125rem;
  --font-size-20: 1.25rem;
  --font-size-22: 1.375rem;
  --font-size-24: 1.5rem;
  --font-size-28: 1.75rem;

  --space-s: 8px;
  --space-m: 16px;

  font-family: var(--font-family-body);
  margin-right: auto;
  margin-left: auto;
  max-width: 90rem;

  @media screen and (min-width: 61.56rem) {
    padding: 0 60px;
  }

  .layout-container--with-sidebar & {
    max-width: none;
    padding: 0;
  }
`;

const App = ({
  title,
  description,
  termId,
  filterType,
  filterValues,
  disableLetterGrouping,
  langcode,
}) => {
  const [selectedFilters, setSelectedFilters] = useState({});
  const [searchQuery, setSearchQuery] = useState("");
  const [totalResults, setTotalResults] = useState(0);

  const [allSubTerms, setAllSubTerms] = useState([]);
  const [filteredSubTerms, setFilteredSubTerms] = useState([]);
  const [pageSubTerms, setPageSubTerms] = useState([]);
  const [alphabeticalFilters, setAlphabeticalFilters] = useState(new Set());

  const [currentPage, setCurrentPage] = useState(1);
  const pagerLinkSiblingCount = 1;
  const itemsPerPage = 25;

  useEffect(() => {
    fetchSubTerms();
  }, [selectedFilters]);

  useEffect(() => {
    // Sets alphabetical filter options based on results from the API.
    async function loadAlphabeticalFilters() {
      const terms = await fetchSubTerms();

      // Iterate over the results and include each
      // found letter to the set once.
      if (terms.length > 0) {
        const firstLetters = new Set([]);
        terms.map((term) => {
          firstLetters.add(term.term_name?.charAt(0).toUpperCase());
        });
        setAlphabeticalFilters(firstLetters);
      }
    }
    loadAlphabeticalFilters();
  }, []);

  useEffect(() => {
    // If there is a search query, filter each sub-terms nodes.
    let results;

    if (searchQuery.trim() !== "") {
      results = allSubTerms
        .map((term) => {
          // Get those that nodes that match the query.
          const filteredItems = term.items.filter((item) => {
            const titleMatch = item.title
              .toLowerCase()
              .includes(searchQuery.toLowerCase());

            // Not every node has text body, so filter conditionally.
            const bodyMatch = (item.text_body?.toLowerCase() ?? "").includes(
              searchQuery.toLowerCase(),
            );

            // Return true if either matches.
            return titleMatch || bodyMatch;
          });
          return { ...term, items: filteredItems };
        })
        .filter((term) => term.items.length > 0);
    } else {
      // No search query -> return all terms with nodes.
      results = allSubTerms;
    }
    // Go through each items array and count total nodes.
    const totalNodeCount = results.reduce(
      (total, term) => total + term.items.length,
      0,
    );

    setFilteredSubTerms(results);
    setTotalResults(totalNodeCount);
    setCurrentPage(1);
  }, [allSubTerms, searchQuery]);

  useEffect(() => {
    const start = (currentPage - 1) * itemsPerPage;
    const paginated = filteredSubTerms.slice(start, start + itemsPerPage);
    setPageSubTerms(paginated);
  }, [filteredSubTerms, currentPage]);

  async function fetchSubTerms() {
    const queryString = buildQueryString(selectedFilters, filterType);
    const url = `${API_URL}/${langcode}/${termId}/sub-terms?${queryString}`;

    try {
      const response = await fetch(url);
      if (!response.ok) {
        throw new Error("Error fetching sub-terms");
      }
      const data = await response.json();

      if (data.sub_terms) {
        // Get those terms that contain nodes.
        const subTermsWithNodes = data.sub_terms.filter(
          (term) => term.items.length > 0,
        );

        setAllSubTerms(subTermsWithNodes);
        setCurrentPage(1);
        return subTermsWithNodes;
      } else {
        setAllSubTerms([]);
        return [];
      }
    } catch (error) {
      console.error("Error:", error);
      setAllSubTerms([]);
    }
  }

  function buildQueryString(filters, filterType) {
    const params = new URLSearchParams();
    params.append("filter_type", filterType);

    // Go through each selected filter group and selected option, add them to query.
    Object.entries(filters).forEach(([groupKey, selectedOptions]) => {
      selectedOptions.forEach((option) => {
        // Convert keys and options to a lowercase underscore format.
        const parsedGroupKey = groupKey.toLowerCase().split(" ").join("_");
        const parsedOption = option.toLowerCase().split(" ").join("_");
        params.append(`${parsedGroupKey}[]`, parsedOption);
      });
    });

    return params.toString();
  }

  function handleFilterChange(newFilters) {
    setSelectedFilters(newFilters);
    setCurrentPage(1);
  }

  function clearAllFilters() {
    setSearchQuery("");
    setSelectedFilters({});
  }

  // Called when the user changes the page. updates the current page which triggers a new API call.
  function handlePaginate(pageNumber) {
    setCurrentPage(pageNumber);
  }

  const hasActiveFilters =
    searchQuery.length > 0 || Object.keys(selectedFilters).length > 0;

  return (
    <StyledApp>
      <h2>{title}</h2>
      <p>{description}</p>

      <SearchBar
        filterTerms={setSearchQuery}
        searchQuery={searchQuery}
        setSearchQuery={setSearchQuery}
      />

      <p>{FILTER_HELP_TEXT}</p>
      <Label>{FILTER_TITLE}</Label>

      {filterType === "alphabetical" && (
        <AlphabeticalFilter
          selectedLetters={selectedFilters.letter || []}
          onFilterChange={handleFilterChange}
          alphabeticalFilters={alphabeticalFilters}
        />
      )}

      {filterType === "hierarchical" && (
        <FilterGroupMenu
          selectedFilters={selectedFilters}
          filterValues={filterValues}
          onFilterChange={handleFilterChange}
        />
      )}

      {hasActiveFilters && (
        <ResetButton onClick={clearAllFilters}>
          {Drupal.t("Remove filters")}
        </ResetButton>
      )}

      <TotalResults totalResults={totalResults} />

      <AccordionList
        subTerms={pageSubTerms}
        disableLetterGrouping={disableLetterGrouping}
      />

      <Pager
        currentPage={currentPage}
        itemsPerPage={itemsPerPage}
        totalItems={filteredSubTerms.length}
        pagerLinkSiblingCount={pagerLinkSiblingCount}
        paginate={handlePaginate}
      />
    </StyledApp>
  );
};

export default App;
