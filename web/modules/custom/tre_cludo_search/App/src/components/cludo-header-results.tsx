import React from "react";
import { ResultCount, useSearchInput } from "@cludo/cludo-search-components";
import { useTranslation } from "react-i18next";
import { setGlobalSearchMode, getGlobalSearchMode } from "../lib/globalSearchMode";

interface CludoHeaderResultsProps {
  showResults?: boolean;
}

const CludoHeaderResults = ({showResults}: CludoHeaderResultsProps) => {
  const [searchInputState, searchInputDispatchers] = useSearchInput();
  
  // Function gets the current search domain mode and toggles it.
  const toggleResults = (e: React.MouseEvent<HTMLAnchorElement>) => {
    e.preventDefault();
    const mode = getGlobalSearchMode();
    const newMode = mode === "main" ? "external" : "main";

    // Change search mode and do a search with current the query.
    // This then triggers Cludo callback function that applies domain filters.
    setGlobalSearchMode(newMode);
    searchInputDispatchers.search(searchInputState.query);
  };

  const { t } = useTranslation();
  
  return (
    <div className="search-page__header__results">
      {showResults && <ResultCount disableTheme={true} />}
      <a
        href="#"
        onClick={(e) => toggleResults(e)}
        className="search-page__header__results__link"
      >
        {getGlobalSearchMode() === "external"
          ? t("translation:controls:internal_search_label")
          : t("translation:controls:external_search_label")}
      </a>
    </div>
  )
}

export default CludoHeaderResults;