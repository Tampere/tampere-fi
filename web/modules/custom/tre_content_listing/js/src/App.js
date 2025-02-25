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

const StyledApp = styled.div`
  --color-primary: #22437b;
  --color-secondary: #29549a;
  --color-full: #ae1e20;
  --color-available: #397368;
  --color-count: #ae1e20;
  --color-accent-secondary: #f1eeeb;
  --color-border: #ccc;

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

const App = ({ title, description, termId, filterType, filterValues }) => {
  const [selectedFilters, setSelectedFilters] = useState({});
  const [filteredSubTerms, setFilteredSubTerms] = useState([]);
  const [totalResults, setTotalResults] = useState(0);
  const [pagination, setPagination] = useState({});
  const [currentPage, setCurrentPage] = useState(1);

  const [originalSubTerms, setOriginalSubTerms] = useState([]);
  const [originalPagination, setOriginalPagination] = useState({});

  const pagerLinkSiblingCount = 1;

  // Whenever selected filters or filter type changes, fetch new sub terms
  useEffect(() => {
    fetchSubTerms();
  }, [selectedFilters, currentPage]);

  async function fetchSubTerms() {
    const queryString = buildQueryString(
      selectedFilters,
      filterType,
      currentPage
    );
    const url = `${API_URL}/${termId}/sub-terms?${queryString}`;

    try {
      const response = await fetch(url);
      if (!response.ok) {
        throw new Error("Error fetching sub-terms");
      }
      const data = await response.json();

      if (data.sub_terms) {
        setFilteredSubTerms(data.sub_terms);
        setTotalResults(data.total_count);
        setPagination(data.pagination);
        setOriginalSubTerms(data);
        setOriginalPagination(data.pagination);
      } else {
        setFilteredSubTerms([]);
        setPagination({});
        setOriginalSubTerms([]);
        setOriginalPagination({});
      }
    } catch (error) {
      console.error("Error:", error);
      setOriginalSubTerms([]);
      setFilteredSubTerms([]);
      setPagination({});
      setOriginalPagination({});
    }
  }

  function buildQueryString(filters, filterType, page) {
    const params = new URLSearchParams();
    params.append("filter_type", filterType);
    params.append("page", page);

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

  function filterTerms(searchQuery) {
    if (searchQuery.length === 0) {
      setFilteredSubTerms(originalSubTerms.sub_terms);
      setCurrentPage(1);
      setPagination(originalPagination);
      setTotalResults(originalSubTerms.total_count);
      return;
    }

    const filteredResults = originalSubTerms.sub_terms.filter((term) => {
      const termName = term.term_name.toLowerCase();
      return termName.includes(searchQuery.toLowerCase());
    });
    setFilteredSubTerms(filteredResults);
    setCurrentPage(1);
    setPagination((prev) => ({
      ...prev,
      total_terms: filteredResults.length,
    }));

    const updatedResultCount = filteredResults.reduce(
      (acculumator, { matching_items }) => acculumator + matching_items,
      0
    );
    setTotalResults(updatedResultCount);
  }

  // Called when the user changes the page. updates the current page which triggers a new API call.
  function handlePaginate(pageNumber) {
    setCurrentPage(pageNumber);
  }

  return (
    <StyledApp>
      <h2>{title}</h2>
      <p>{description}</p>

      <SearchBar filterTerms={filterTerms} />

      <p>{FILTER_HELP_TEXT}</p>
      <Label>{FILTER_TITLE}</Label>

      {filterType === "alphabetical" && (
        <AlphabeticalFilter onFilterChange={handleFilterChange} />
      )}

      {filterType === "hierarchical" && (
        <FilterGroupMenu
          filterValues={filterValues}
          onFilterChange={handleFilterChange}
        />
      )}

      <TotalResults totalResults={totalResults} />

      <AccordionList
        subTerms={filteredSubTerms}
        queryParams={buildQueryString(selectedFilters, filterType, currentPage)}
      />

      <Pager
        currentPage={currentPage}
        itemsPerPage={pagination.per_page}
        totalItems={pagination.total_terms}
        pagerLinkSiblingCount={pagerLinkSiblingCount}
        paginate={handlePaginate}
      />
    </StyledApp>
  );
};

export default App;
