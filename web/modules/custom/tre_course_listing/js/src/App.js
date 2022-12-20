import React, { useEffect, useRef, useState } from "react";
import styled from "styled-components";

import {
  FILTER_TYPES_BY_LISTING_TYPE,
  FILTER_TYPES_WO_COMPLEX_RELATIONSHIPS,
  FILTER_TYPE_PARENTS
} from './constants';

import CourseCards from "./components/CourseCards";
import Filters from "./components/Filters";
import Pager from "./components/Pager";
import SearchBar from "./components/SearchBar";

const StyledApp = styled.div`
  --color-primary: #22437b;
  --color-secondary: #29549a;
  --color-full: #ae1e20;
  --color-available: #397368;
  --color-count: #ae1e20;

  --font-family-body: 'Open Sans', 'Arial', sans-serif;
  --font-family-heading: 'Montserrat', 'Helvetica', 'Arial', sans-serif;

  --font-size-15: 0.9375rem;
  --font-size-16: 1rem;
  --font-size-18: 1.125rem;
  --font-size-20: 1.25rem;
  --font-size-22: 1.375rem;
  --font-size-24: 1.5rem;
  --font-size-28: 1.75rem;

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

const ListingTitle = styled.h2`
  font-family: var(--font-family-heading);
  font-size: var(--font-size-22);
  font-weight: 800;
  line-height: 1.11;

  @media screen and (min-width: 61.56rem) {
    font-size: var(--font-size-28);
  }
`;

const ListingDescription = styled.p`
  line-height: 1.4;

  @media screen and (min-width: 61.56rem) {
    line-height: 1.6;
  }
`;

const ResultAmount = styled.p`
  margin: 32px 0;
`;

export default function App({ title, description, originalItems, sortType, listingType }) {
  const [items, setItems] = useState([]);
  const [currentPage, setCurrentPage] = useState(1);
  const [activeFilters, setActiveFilters] = useState([]);
  const [availableFilters, setAvailableFilters] = useState([]);

  const filterTypes = FILTER_TYPES_BY_LISTING_TYPE[listingType];

  const searchQueryRef = useRef(null);

  const itemsPerPage = 12;
  const pagerLinkSiblingCount = 1;

  const filterItemParentNames = new Map();

  // A period will always have a term as parent, and there cannot be duplicate
  // periods with different parents. Other categories can have different
  // departments as parent, and the response includes following information:
  // subject, category + subject, and department + category + subject. In order
  // to not get duplicate filters, the categories with parents will be ignored
  // for categories with complex relationships.
  //
  // See section 'Tree structure in catalog' in
  // https://api.opistopalvelut.fi/#tag/Catalog/operation/GetCatalog
  const catalogItems = originalItems.map(item => item.catalogitems.filter(catalogItem => {
    if (FILTER_TYPES_WO_COMPLEX_RELATIONSHIPS.includes(catalogItem.type)) {
      return catalogItem;
    }
    else if (FILTER_TYPE_PARENTS.includes(catalogItem.type) && !catalogItem.parent) {
      // Collecting parent catalog item names so they can be appended to
      // the names of their children in filters. Currently only affects semesters.
      // E.g. Autumn semester 2022-2023.
      filterItemParentNames.set(`${catalogItem["type"]}:${catalogItem["id"]}`, catalogItem.name);
    }
    else if (filterTypes.includes(catalogItem.type) && !catalogItem.parent) {
      return catalogItem;
    }
  })).flat();

  // Make sure all filters appear only once by mapping them by their type and ID.
  const getUniqueFilters = array => {
    return [...new Map(array.map(filter => [`${filter["type"]}:${filter["id"]}`, filter])).values()];
  }

  const originalFilters = getUniqueFilters(catalogItems);

  // Applies active filters of type to given array.
  const filterByType = (array, type) => {
    const activeFiltersOfType = activeFilters.filter(filter => filter.type === type);

    return array.filter(item => item.catalogitems.some(
      catalogItem => activeFiltersOfType.some(
        activeFilter => activeFilter.id === catalogItem.id && activeFilter.type === catalogItem.type
      )
    ));
  };

  // Applies active filters and search query to all items.
  const filterItems = () => {
    const allMatchedItemsByFilterType = filterTypes.map(filterType => {
      const itemsMatchingFilterType = filterByType(originalItems, filterType);
      const matchedItemIds = itemsMatchingFilterType.map(item => item.id);

      return matchedItemIds;
    });

    // Filter out unused filter groups (empty array).
    const arraysWithItems =  allMatchedItemsByFilterType.filter(array => array.length);

    const availableItems = getItemsMatchingSearchQuery(originalItems);

    const arrayAmount = arraysWithItems.length;
    let intersection;
    // Filter displayed items based on how many filter groups are active.
    switch (arrayAmount) {
      case 1:
        // Only one filter group is active.
        setItems(availableItems.filter(item => arraysWithItems[0].includes(item.id)));
        break;
      case 2:
        // Two filter groups are active.
        const firstGroup = new Set(arraysWithItems[0]);
        const secondGroup = new Set(arraysWithItems[1]);
        intersection = [...firstGroup].filter(x => secondGroup.has(x));
        setItems(availableItems.filter(item => intersection.includes(item.id)));
        break;
      case 3:
        // Three filter groups are active.
        intersection = arraysWithItems.reduce((a, b) => a.filter(c => b.includes(c)));
        setItems(availableItems.filter(item => intersection.includes(item.id)));
        break;
      default:
        setItems(availableItems);
    }
  };

  // Sets given value to active filters if not already active.
  const selectFilter = value => {
    setActiveFilters(prevFilters => {
      const isFilterActive = prevFilters.some(filter => filter.id === value.id && filter.type === value.type);

      return isFilterActive ? prevFilters.filter(filter => filter.id !== value.id) : [...prevFilters, value];
    });
  };

  const paginate = pageNumber => setCurrentPage(pageNumber);

  const toLowerCaseString = item => item.toString().toLowerCase();

  // Filters given array based on search query value if one exists.
  const getItemsMatchingSearchQuery = array => {
    if (!searchQueryRef.current.value) {
      return array;
    }

    return array.filter(item => toLowerCaseString(item.name).includes(toLowerCaseString(searchQueryRef.current.value)));
  }

  // Resets active filters.
  const resetFilters = () => {
    setItems(originalItems);
    setActiveFilters([]);
  };

  const sortByStartingDateAsc = (a, b) => {
    if (a.begins < b.begins) {
      return 1;
    }

    if (a.begins > b.begins) {
      return -1;
    }

    return 0;
  };

  const sortByStartingDateDesc = (a, b) => {
    if (a.begins > b.begins) {
      return 1;
    }

    if (a.begins < b.begins) {
      return -1;
    }

    return 0;
  };

  // Updates available filters so that they don't include options that would
  // offer no matching items.
  const updateAvailableFilters = () => {
    if (!activeFilters?.length && !searchQueryRef.current.value) {
      // When there are no active filters or a search query, use the original
      // filters as available filters.
      setAvailableFilters(originalFilters);
      return;
    }

    const availableItems = getItemsMatchingSearchQuery(originalItems);

    const allMatchedItemIdsByFilterType = filterTypes.map(filterType => {
      let itemsMatchingFilterType = filterByType(availableItems, filterType);

      // If the current filter type has no active filters,
      // there will be no matching items for that type. In that case,
      // all the available items should be used.
      if (!itemsMatchingFilterType?.length) {
        itemsMatchingFilterType = availableItems;
      }

      return itemsMatchingFilterType.map(item => item.id);
    });

    const filterTypesMap = new Map();
    filterTypes.forEach((filterType, index) => filterTypesMap.set(filterType, index));

    // The filters of a given type are the result of an intersection of
    // the matched items for the other two types (e.g. available department
    // filters will be searched from the intersection of the matched subject
    // and period items).
    const availableFiltersByFilterType = filterTypes.map(filterType => {
      const otherFilterTypes = filterTypes.filter(type => type !== filterType);

      const firstGroupFilterIndex = filterTypesMap.get(otherFilterTypes[0]);
      const secondGroupFilterIndex = filterTypesMap.get(otherFilterTypes[1]);

      const firstGroup = new Set(allMatchedItemIdsByFilterType[firstGroupFilterIndex]);
      const secondGroup = new Set(allMatchedItemIdsByFilterType[secondGroupFilterIndex]);
      const intersection = [...firstGroup].filter(x => secondGroup.has(x));

      const intersectedItems = availableItems.filter(item => intersection.includes(item.id));

      const filtersForType = intersectedItems.map(item => item.catalogitems.filter(catalogItem => {
        if (FILTER_TYPES_WO_COMPLEX_RELATIONSHIPS.includes(filterType) && catalogItem.type === filterType) {
          return catalogItem;
        }
        else if (catalogItem.type === filterType && !catalogItem.parent) {
          return catalogItem;
        }
      })).flat();

      return filtersForType;
    });

    const allFilters = [ ...activeFilters, ...availableFiltersByFilterType.flat() ];

    const newFilters = getUniqueFilters(allFilters);

    setAvailableFilters(newFilters);
  };

  useEffect(() => {
    setItems(originalItems);
    setAvailableFilters(originalFilters);
  }, []);

  useEffect(() => {
    setCurrentPage(1);
    setItems(prevItems => (
      sortType === 'date_asc' ?
      prevItems.sort(sortByStartingDateAsc) :
      prevItems.sort(sortByStartingDateDesc)
    ));
    updateAvailableFilters();
  }, [items]);

  useEffect(() => {
    filterItems();
  }, [activeFilters]);

  const indexOfLastItem = currentPage * itemsPerPage;
  const indexOfFirstItem = indexOfLastItem - itemsPerPage;
  const itemsForPage = items.slice(indexOfFirstItem, indexOfLastItem);

  return (
    <StyledApp>
      { title && <ListingTitle>{title}</ListingTitle> }
      { description && <ListingDescription>{description}</ListingDescription> }
      <SearchBar ref={searchQueryRef} filterItems={filterItems} />
      <Filters
        availableFilters={availableFilters}
        selectFilter={selectFilter}
        resetFilters={resetFilters}
        activeFilters={activeFilters}
        filterItemParentNames={filterItemParentNames}
        filterTypes={filterTypes}
      />
      <ResultAmount>
        { Drupal.t("Total of @amount results", { "@amount": items.length }) }
      </ResultAmount>
      <CourseCards items={itemsForPage} />
      { items.length > itemsPerPage &&
        <Pager
          currentPage={currentPage}
          itemsPerPage={itemsPerPage}
          totalItems={items.length}
          pagerLinkSiblingCount={pagerLinkSiblingCount}
          paginate={paginate}
        />
      }
    </StyledApp>
  );
};
