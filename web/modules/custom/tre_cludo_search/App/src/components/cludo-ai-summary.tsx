import React, { useEffect, useState, useRef } from "react";
import { useTranslation } from "react-i18next";
import { treDrupalSettings } from "../config";
import {
  TypedDocument,
  useSearchInput,
  useSearchResults,
} from "@cludo/cludo-search-components";

export function CludoAiSummary() {
  const [searchInputState] = useSearchInput();
  const [searchResultsState] = useSearchResults();
  const [summary, setSummary] = useState("");
  const [expandSummary, setExpandSummary] = useState(false);
  const [summaryAvailable, setSummaryAvailable] = useState(false);
  const abortControllerRef = useRef<AbortController | null>(null);
  const { t } = useTranslation();

  const cludoURL = `https://api.cludo.com/api/v4/${treDrupalSettings.customerId}/${treDrupalSettings.engineId}/search/`;

  // Creates base64 encoded auth headers required by cludo.
  function getEncodedCredentials() {
    const { customerId, engineId } = treDrupalSettings;
    return btoa(`${customerId}:${engineId}:SearchKey`);
  }

  // Calls Cludo endpoint with the current search query
  // to obtain data needed for the AI summary request.
  async function queryCludo() {
    const response = await fetch(cludoURL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `SiteKey ${getEncodedCredentials()}`,
      },
      body: JSON.stringify({
        query: searchInputState.query,
        operator: "or",
      }),
    });

    return response.json();
  }

  // Calls Cludo AI summary endpoint with search query data,
  // and updates the summary state with the incoming stream.
  async function fetchSummary() {
    // Fetch search data for the current query.
    const searchData = await queryCludo();

    // Generate summary if its available for the search query.
    if (searchData.GenerativeAnswerAvailable) {
      // Set summary container visible.
      setSummaryAvailable(true);

      // Get top 3 hits from search results and pick used fields.
      const sourceData = searchData.TypedDocuments.slice(0, 3);
      const topHits = sourceData.map((document: TypedDocument) => ({
        id: document.Fields.Url.Value,
        fields: ["Title", "Description"],
      }));

      try {
        // Fetch AI summary using the streaming endpoint.
        const response = await fetch(`${cludoURL}/summarize/stream`, {
          method: "POST",
          signal: abortControllerRef.current?.signal,
          headers: {
            "Content-Type": "application/json",
            Authorization: `SiteKey ${getEncodedCredentials()}`,
          },
          body: JSON.stringify({
            query: searchInputState.query,
            sources: topHits,
            queryId: searchData.QueryId,
            language: treDrupalSettings.currentLanguage,
          }),
        });

        if (!response.body || response.status !== 200) {
          setSummaryAvailable(false);
          return null;
        }

        // Create a reader and decoder to read the response.
        const reader = response.body.getReader();
        const decoder = new TextDecoder("utf-8");

        // Process the stream as chuncks come in.
        while (true) {
          const { done, value } = await reader.read();

          // Exit once the stream is done.
          if (done) break;

          // Decode incoming value and append it to the summary.
          const chunk = decoder.decode(value, { stream: true });
          setSummary((prev) => prev + chunk);
        }
      } catch (error) {
        // Return null, usually in case stream gets the abort signal.
        setSummaryAvailable(false);
        return null;
      }
    } else {
      setSummaryAvailable(false);
    }

    return null;
  }

  // Fetches summary each time a search is done.
  useEffect(() => {
    if (!searchInputState.query || searchResultsState.isLoading) {
      return;
    }

    async function fetchData() {
      // If there is an existing stream ongoing,
      // abort it using the ref signal.
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }

      // Create new AbortController and add it to ref
      // that is then passed in summary request.
      abortControllerRef.current = new AbortController();

      // Reset summary before streaming begins.
      setSummary("");
      setExpandSummary(false);
      await fetchSummary();
    }

    fetchData();
  }, [searchResultsState.isLoading]);

  return (
    <>
      {summaryAvailable && (
        <>
          <div className="text-with-bgcolor__summary">
            <h3 className="text-with-bgcolor__summary__title">
              {t("translation:titles:summary_title")}
            </h3>
            <div
              className={`text-with-bgcolor__summary__text ${
                expandSummary
                  ? "text-with-bgcolor__summary__text--expanded"
                  : "text-with-bgcolor__summary__text--default"
              }`}
            >
              {summary.length > 0 && (
                <>
                  <p>{summary}</p>
                  <p>{t("translation:instructions:summary_note")}</p>
                </>
              )}
            </div>
            <button
              className="expand-button"
              onClick={() => setExpandSummary(!expandSummary)}
            >
              <svg
                role="presentation"
                className={`accordion__icon ${
                  expandSummary
                    ? "accordion__icon--expanded"
                    : "accordion__icon--collapsed"
                }`}
              >
                <use xlinkHref="/themes/custom/tampere/dist/main-site-icons.svg?20250221#arrow-plain-new" />
              </svg>

              {expandSummary
                ? t("translation:controls:close")
                : t("translation:controls:open")}
            </button>
          </div>
        </>
      )}
    </>
  );
}
