import { ClearAllFacets } from "@cludo/cludo-search-components";
import { useFacet, useFacetGroup } from "@cludo/cludo-search-components";
import { FacetState, FacetDispatchers } from "@cludo/cludo-search-components";
import { useTranslation } from "react-i18next";
import React, { useState, useEffect } from "react";
import { getGlobalSearchMode } from "../lib/globalSearchMode";

export function CludoControlsTemplate() {
  const { t } = useTranslation();
  const [facetGroupState] = useFacetGroup();
  const [expandedFacetGroup, setExpandedFacetGroup] = useState<string | null>(
    null
  );

  // Define search facet config manually here to include
  // translated labels and local facet hooks.
  const searchFacets = [
    {
      name: "Content_type",
      label: t("translation:facets:content_type"),
      class_name: "content-type",
      facetHook: useFacet("Content_type"),
    },
    {
      name: "Topic",
      label: t("translation:facets:topic"),
      class_name: "topic",
      facetHook: useFacet("Topic"),
    },
    {
      name: "Life_situation",
      label: t("translation:facets:life_situation"),
      class_name: "life-situation",
      facetHook: useFacet("Life_situation"),
    },
    {
      name: "Geo_areas",
      label: t("translation:facets:location"),
      class_name: "geo-areas",
      facetHook: useFacet("Geo_areas"),
    },
    {
      name: "Sivusto",
      label: t("translation:facets:sivusto"),
      class_name: "domain",
      facetHook: useFacet("Sivusto"),
    },
  ];

  function handleFacetClick(facetKey: string, event: React.MouseEvent) {
    event.preventDefault();
    setExpandedFacetGroup((prev) => (prev === facetKey ? null : facetKey));
  }

  function handleOptionChange(
    facetKey: string,
    optionValue: string,
    event: React.MouseEvent | React.ChangeEvent | React.KeyboardEvent
  ) {
    event.preventDefault();

    const facetItem = searchFacets.find((facet) => facet.name === facetKey);
    if (!facetItem) return;

    // Get the dispatcher hook for this specific facet group.
    const [, facetDispatchers]: [FacetState, FacetDispatchers] =
      facetItem.facetHook;

    // Select the clicked option value, set multi-select as true.
    facetDispatchers.selectFacet(optionValue, true);
  }

  // This is used to get the active facet from the state.
  const activeFacet = facetGroupState.facets.find(
    (facet) => facet.key === expandedFacetGroup
  );

  return (
    <>
      <h1 className="search-page__title h1 hyphenate">
        {getGlobalSearchMode() ===  'main'
          ? t("translation:controls:internal_search_page_title")
          : t("translation:controls:external_search_page_title")
        }
      </h1>
      <div className="search-page__additional-information">
        {t("translation:controls:filters_description")}
      </div>
      <h2 className="search-page__filter-title h2 hyphenate">
        {t("translation:controls:filters_label")}
      </h2>
      <div className="search-page__facets">
        <div className="facet-accordion">
          <div className="facet-accordion__headings">
            {searchFacets.map((facet) => {
              // Get this facets state from the facet group hook.
              const [facetState] = facet.facetHook;
              // Count the selected options and get the active state.
              const selectedCount =
                facetState.facet?.values.filter((option) => option.isSelected)
                  .length || 0;
              const isActive = expandedFacetGroup === facet.name;

              return (
                facet.name === 'Sivusto' ? getGlobalSearchMode() === 'external' && (
                  <button
                    key={facet.name}
                    type="button"
                    onClick={(e) => handleFacetClick(facet.name, e)}
                    className={`facet-accordion-item__heading accordion-heading ${
                      isActive ? "is-active" : ""
                    }`}
                  >
                    <span className="facet-accordion-item__title">
                      {facet.label}
                      {selectedCount > 0 && (
                        <span className="facet-accordion-item__count">
                          {selectedCount}
                        </span>
                      )}
                    </span>
                  </button>
                ) : getGlobalSearchMode() === 'main' && (
                  <button
                    key={facet.name}
                    type="button"
                    onClick={(e) => handleFacetClick(facet.name, e)}
                    className={`facet-accordion-item__heading accordion-heading ${
                      isActive ? "is-active" : ""
                    }`}
                  >
                    <span className="facet-accordion-item__title">
                      {facet.label}
                      {selectedCount > 0 && (
                        <span className="facet-accordion-item__count">
                          {selectedCount}
                        </span>
                      )}
                    </span>
                  </button>
                )
              );
            })}
          </div>

          {activeFacet && activeFacet.values && (
            <div className="facet-accordion-item__content accordion-content-section active">
              <ul className="facet-accordion-item__list">
                {activeFacet.values.map((option) => (
                  <li
                    key={option.value}
                    className={`facet-item facet-accordion-item__list-item cludo-filter ${
                      option.isSelected ? "is-active" : ""
                    }`}
                  >
                    <label>
                      <input
                        type="checkbox"
                        className="facets-checkbox"
                        checked={option.isSelected || false}
                        onChange={(e) =>
                          handleOptionChange(activeFacet.key, option.value, e)
                        }
                        onKeyDown={(e) => {
                          if(e.key === "Enter") {
                            e.preventDefault()
                            handleOptionChange(activeFacet.key, option.value, e)
                          }
                        }}
                      />
                      <span className="facet-item__value">{option.value}</span>
                    </label>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      </div>

      <div className="remove-facets">
        <ClearAllFacets
          disableTheme={true}
          label={t("translation:controls:filters_reset_label")}
          className="remove-facets__link"
        />
      </div>
    </>
  );
}
