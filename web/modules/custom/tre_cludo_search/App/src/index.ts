import {
  CludoInstanceController,
  initCludo,
} from "@cludo/cludo-search-components";
import { CludoResultsTemplate } from "./components/cludo-results-template";
import { CludoControlsTemplate } from "./components/cludo-controls-template";
import { CludoAutocompleteTemplate } from "./components/cludo-autocomplete-template";
import { initReactI18next, Trans } from "react-i18next";
import { treDrupalSettings } from "./config.js";
import i18next from "i18next";
import en_translation from "./i18n/en/translation.json";
import fi_translation from "./i18n/fi/translation.json";
import { getGlobalSearchMode } from "./lib/globalSearchMode";

let controller: CludoInstanceController;

i18next.use(initReactI18next).init({
  debug: false,
  fallbackLng: "fi",
  lng: treDrupalSettings.currentLanguage,
  interpolation: {
    escapeValue: false,
  },
  resources: {
    en: {
      translation: en_translation,
    },
    fi: {
      translation: fi_translation,
    },
  },
  returnNull: false,
  supportedLngs: ["fi", "en"],
});

let searchPageUrl =
  treDrupalSettings.currentLanguage === "en" ? "/en/search" : "/search";

async function initCludoApp() {
  controller = await initCludo({
    /** Required properties ~ */
    customerId: treDrupalSettings.customerId,
    engineId: treDrupalSettings.engineId,
    searchUrl: searchPageUrl,
    language: treDrupalSettings.currentLanguage,
    /** A selector of a form element (or any wrapper element)
     *  that contains an existing search input that you
     *  would like to use with Cludo search. Multiple selectors
     *  can be added to make multiple search inputs work.
     */
    searchInputSelectors: ["#cludo-search-input"],
    /** Any css selector for wrapper elements inside which
     *  results and controls will be rendered */
    searchResultsWrapperSelector: ".cludo-search-results",
    controlsWrapperSelector: ".cludo-search-controls",

    /** Recommended properties ~
     *  Set template components that the search
     *  controller will use to render search results,
     *  controls, and autocomplete. The Cludo template
     *  components set by default provide a good starting
     *  point for using Cludo components to create a
     *  search template.
     */
    components: {
      results: CludoResultsTemplate,
      controls: CludoControlsTemplate,
      autocomplete: CludoAutocompleteTemplate,
    },
    /** Optional properties ~ */
    cssTheme: "cludo-theme-basic",
    autocomplete: {
      disable: false,
      useSearchAsYouType: true,
      renderTemplateWhenNoResults: true,
      showInstantSuggestions: true,
    },
    behavior: {
      allowSearchWithoutSearchword: true,
      jumpToTopOnFacetClick: false,
    },
    callbacks: {
      // Callback function that sets Domain category based filters.
      // Ran after a search query is submitted, but not before its sent to server.
      beforeSearch: (searchParams) => {
        if (getGlobalSearchMode() === "external") {
          searchParams.notFilters = {
            Category: ["Tampere.fi"],
          };
          if (searchParams.filters && searchParams.filters.Category) {
            delete searchParams.filters.Category;
          }
        } else {
          searchParams.filters = {
            Category: ["Tampere.fi"],
          };
          if (searchParams.notFilters && searchParams.notFilters.Category) {
            delete searchParams.notFilters.Category;
          }
        }
      },
    },
    facets: {
      keys: [
        "Content_type",
        "Sivusto",
        "Topic",
        "Life_situation",
        "Geo_areas",
        "Breadcrumb",
        "Date",
        "Description",
      ],
    },
    theme: {
      resultsPerPage: 10,
    },
  });
}

document.addEventListener("DOMContentLoaded", initCludoApp);
