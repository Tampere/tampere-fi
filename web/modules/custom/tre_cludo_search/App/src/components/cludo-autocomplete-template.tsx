import React from "react";
import {
  SaytSuggestion,
  useAutocomplete,
} from "@cludo/cludo-search-components";
import { useTranslation } from "react-i18next";

export function CludoAutocompleteTemplate() {
  const [autocompleteState] = useAutocomplete();
  const { suggestions, selectedSuggestion, instantSuggestions } =
    autocompleteState;
  const { t } = useTranslation();

  return (
    <>
      {instantSuggestions.length > 0 ? (
        <div className="cludo-search-autocomplete-suggestions">
          <ul>
          {instantSuggestions.map((suggestion, i) => {
            const isSelected = i === selectedSuggestion;
            return (
              <SaytSuggestion
                key={i}
                suggestion={suggestion}
                isSelected={isSelected}
                suggestionIndex={i}
                className="autocomplete-suggestion autocomplete-suggestion-label"
              />
            );
          })}
          </ul>
          <div className="autocomplete-link-container">
            <a href={t("translation:autocomplete:search_page_link")} className="link field-link">{t("translation:autocomplete:search_page_link_text")}</a>
            <p>{t("translation:autocomplete:search_page_link_description")}</p>
          </div>
        </div>
      ) : suggestions.length > 0 && 
        <div className="cludo-search-autocomplete-suggestions">
          <ul>
            {suggestions.map((suggestion, i) => {
              const isSelected = i === selectedSuggestion;
              return (
                <SaytSuggestion
                  key={i}
                  suggestion={suggestion}
                  isSelected={isSelected}
                  suggestionIndex={i}
                  className="autocomplete-suggestion autocomplete-suggestion-label"
                />
              );
            })}
          </ul>
          <div className="autocomplete-link-container">
            <a href={t("translation:autocomplete:search_page_link")} className="link field-link">{t("translation:autocomplete:search_page_link_text")}</a>
            <p>{t("translation:autocomplete:search_page_link_description")}</p>
          </div>
        </div>
      }
    </>
  );
}
