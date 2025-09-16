import { ClearAllFacets } from "@cludo/cludo-search-components";
import { useFacet, useFacetGroup } from "@cludo/cludo-search-components";
import { FacetState, FacetDispatchers } from "@cludo/cludo-search-components";
import { useTranslation } from "react-i18next";
import React, { useState, useEffect, useRef } from "react";
import { getGlobalSearchMode } from "../lib/globalSearchMode";

export function CludoControlsTemplate() {
  const { t } = useTranslation();
  const [facetGroupState] = useFacetGroup();
  const [expandedFacetGroup, setExpandedFacetGroup] = useState<string | null>(
    null
  );
  const facetButtonRefs = useRef<(HTMLButtonElement | null)[]>([]);
  const contentPanelRef = useRef<HTMLInputElement>(null);
  const removeFacetsRef = useRef<HTMLDivElement>(null);

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

  // Filter the facets to only ones that should be visible in the current search mode
  const visibleFacets = searchFacets.filter(
    (facet) =>
      (facet.name === "Sivusto" && getGlobalSearchMode() === "external") ||
      (facet.name !== "Sivusto" && getGlobalSearchMode() === "main")
  );

  function findNextVisibleButton(currentFacetName: string) {
    const currentIndex = visibleFacets.findIndex(
      (f) => f.name === currentFacetName
    );

    // Return the next facet group button ref.
    if (currentIndex > -1 && currentIndex < visibleFacets.length - 1) {
      return facetButtonRefs.current[currentIndex + 1];
    }
    return null;
  }

  function handleFacetClick(facetKey: string) {
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
        {getGlobalSearchMode() === "main"
          ? t("translation:controls:internal_search_page_title")
          : t("translation:controls:external_search_page_title")}
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
            {visibleFacets.map((facet, index) => {
              const [facetState] = facet.facetHook;
              const selectedCount =
                facetState.facet?.values.filter((o) => o.isSelected).length ||
                0;
              const isActive = expandedFacetGroup === facet.name;
              const isLastButton = index === visibleFacets.length - 1;
              const activeFacetHasContent =
                activeFacet &&
                activeFacet.values &&
                activeFacet.values.length > 0;

              return (
                <button
                  key={facet.name}
                  ref={(el) => (facetButtonRefs.current[index] = el)}
                  type="button"
                  onClick={() => handleFacetClick(facet.name)}
                  className={`facet-accordion-item__heading accordion-heading ${
                    isActive ? "is-active" : ""
                  }`}
                  onKeyDown={(e) => {
                    if (e.key === "Tab" && !e.shiftKey) {
                      // When facet group is open, navigate to the opened facet options panel.
                      if (isActive && activeFacetHasContent) {
                        e.preventDefault();
                        contentPanelRef.current?.focus();
                      }
                      // If this is the last button and some other group is open,
                      // exit the component & break the loop.
                      else if (
                        isLastButton &&
                        activeFacetHasContent &&
                        !isActive
                      ) {
                        removeFacetsRef.current?.focus();
                      }
                    }
                  }}
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
              );
            })}
          </div>

          {activeFacet && activeFacet.values && (
            <div className="facet-accordion-item__content accordion-content-section active">
              <ul className="facet-accordion-item__list">
                {activeFacet.values.map((option, index) => (
                  <li
                    key={option.value}
                    className={`facet-item facet-accordion-item__list-item cludo-filter ${
                      option.isSelected ? "is-active" : ""
                    }`}
                  >
                    <label>
                      <input
                        type="checkbox"
                        ref={index === 0 ? contentPanelRef : null}
                        className="facets-checkbox"
                        checked={option.isSelected || false}
                        onChange={(e) =>
                          handleOptionChange(activeFacet.key, option.value, e)
                        }
                        onKeyDown={(e) => {
                          if (e.key === "Enter") {
                            e.preventDefault();
                            handleOptionChange(
                              activeFacet.key,
                              option.value,
                              e
                            );
                          }

                          // When on the last option in the panel,
                          // Tab moves focus to the next facet group button.
                          const isLastOption =
                            index === activeFacet.values.length - 1;
                          if (isLastOption && e.key === "Tab" && !e.shiftKey) {
                            const nextButton = findNextVisibleButton(
                              activeFacet.key
                            );
                            if (nextButton) {
                              e.preventDefault();
                              nextButton.focus();
                            }
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

      <div className="remove-facets" tabIndex={-1} ref={removeFacetsRef}>
        <ClearAllFacets
          disableTheme={true}
          label={t("translation:controls:filters_reset_label")}
          className="remove-facets__link"
        />
      </div>
    </>
  );
}
