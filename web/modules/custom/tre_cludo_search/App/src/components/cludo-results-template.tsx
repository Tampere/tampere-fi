import React, { useEffect } from "react";
import {
  Banner,
  CustomResult,
  Pagination,
  ResultBadge,
  ResultTitle,
  ResultsList,
  useBanners,
  useSearchResults,
} from "@cludo/cludo-search-components";
import CludoHeaderResults from "./cludo-header-results";
import CludoSearchServices from './cludo-search-services';
import { getGlobalSearchMode } from "../lib/globalSearchMode";
import { replayMissedSearchForAiSummary } from "../lib/aiSummaryReplay";
import { useTranslation } from "react-i18next";
import { treDrupalSettings } from "../config";

export function CludoResultsTemplate() {
  const [bannerState] = useBanners();
  const { banners } = bannerState;
  const [{resultCount, isLoading}] = useSearchResults();
  const { t } = useTranslation();

  useEffect(
    () =>
      treDrupalSettings.aiSummaryXid
        ? replayMissedSearchForAiSummary()
        : undefined,
    [],
  );

  useEffect(() => {
    if (!document.getElementById("cludo-experience-manager")) {
      const script = document.createElement("script");
      script.setAttribute("data-cid", String(treDrupalSettings.customerId));
      script.setAttribute("data-eid", String(treDrupalSettings.engineId));
      script.src =
        "https://customer.cludo.com/scripts/bundles/experiences/manager.js";
      script.id = "cludo-experience-manager";
      script.defer = true;

      document.body.appendChild(script);
    }
  }, []);

  useEffect(() => {
    // Create an observer to look for nodes added to the DOM.
    const observer = new MutationObserver((mutations) => {
      // Go through each mutation, look for the cludo assistant element.
      for (const mutation of mutations) {
        mutation.addedNodes.forEach((node) => {
          const cludoAssistant = node as HTMLElement;
          // Once the script has added the cludo
          // assistant to the DOM we can process it.
          if (
            cludoAssistant.id &&
            cludoAssistant.id.startsWith("cludo-experience-container")
          ) {
            // Check if the element is already processed to possible infite loop.
            if (cludoAssistant.getAttribute("data-processed")) {
              return;
            }

            // Make chat element focusable and trigger click on Enter.
            cludoAssistant.setAttribute("data-processed", "true");
            cludoAssistant.setAttribute("tabindex", "0");
            cludoAssistant.addEventListener("keydown", (e) => {
              if (e.key === "Enter") {
                e.preventDefault();
                cludoAssistant.click();
              }
            });

            const searchControls = document.querySelector(
              ".cludo-search-controls",
            );
            // Move cludo assistant manually from the bottom of the page
            // so that is focusable after the search controls.
            if (searchControls) {
              searchControls.appendChild(cludoAssistant);
            }

            // Disconnect the observer once the element is processed.
            observer.disconnect();
          }
        });
      }
    });

    // Run the observer.
    observer.observe(document.body, { childList: true, subtree: true });
    // Reserve cleanup, if the observer is still running on unmount.
    return () => observer.disconnect();
  }, []);

  return (
    <>
      {banners &&
        banners.map((banner, i) => {
          return <Banner key={i} banner={banner} />;
        })}
      {treDrupalSettings.aiSummaryXid && (
        <div
          data-read-aloud-label={t("controls.listen_summary")}
          data-open-label={t("controls.open_summary")}
          data-close-label={t("controls.close_summary")}
          data-ai-icon-label={t("controls.ai_icon")}
          data-feedback-yes-label={t("controls.feedback_yes")}
          data-feedback-no-label={t("controls.feedback_no")}
          style={
            {
              "--ai-summary-open": JSON.stringify(t("controls.open")),
              "--ai-summary-close": JSON.stringify(t("controls.close")),
              "--ai-summary-listen": JSON.stringify(t("controls.listen")),
            } as React.CSSProperties
          }
        >
          <cludo-element
            data-type="AISummaryModule"
            data-xid={treDrupalSettings.aiSummaryXid}
          ></cludo-element>
        </div>
      )}
      <CludoHeaderResults showResults={true}/>
      <ResultsList
        disableTheme={true}
        className="search-page__search-results"
        template={(currResult) => (
          <CustomResult
            result={currResult}
            wrapWithLink={true}
            disableTheme={true}
            className="listing-item listing-item--default-search listing-item--link-wrapper"
          >
            <div className="listing-item__type">
              {getGlobalSearchMode() === "main" ? (
                <ResultBadge
                  disableTheme={true}
                  field="Content_type"
                  className="listing-item__type-wrapper"
                />
              ) : (
                <ResultBadge
                  disableTheme={true}
                  field="Sivusto"
                  className="listing-item__type-wrapper"
                />
              )}
            </div>
            <div className="listing-item__content-wrapper">
              <div className="listing-item__main-content hyphenate">
                <ResultTitle
                  disableTheme={true}
                  className="listing-item__heading h3 hyphenate"
                />
                { currResult.Fields.Description &&
                  currResult.Fields.Description.Value.length <= 300 ? (
                    <ResultBadge
                      disableTheme={true}
                      className="field-metadata-description"
                      field="Description"
                    />
                  ) : []
                }
                { currResult.Fields.Date && 
                  currResult.Fields.Content_type
                  ? ( currResult.Fields.Content_type.Value ===
                        "Uutiset ja artikkelit" ||
                      currResult.Fields.Content_type.Value ===
                        "News and articles") && (
                        <ResultBadge
                          disableTheme={true}
                          text={new Date(
                            currResult.Fields.Date.Value,
                          ).toLocaleDateString("fi-FI")}
                          className="listing-item__published"
                        />
                      )
                  : [ currResult.Fields.Date &&
                      currResult.Fields.Sivusto
                      ? currResult.Fields.Sivusto.Value != "Tampere.fi" && (
                        <ResultBadge
                          disableTheme={true}
                          text={new Date(
                            currResult.Fields.Date.Value,
                          ).toLocaleDateString("fi-FI")}
                          className="listing-item__published"
                        />
                      ) : []
                    ]
                  }
                <div className="listing-item__breadcrumbs">
                  {currResult.Fields.Breadcrumb
                    ? currResult.Fields.Breadcrumb.Values.length > 0 && (
                        <span className="cludo-search-results-item__breadcrumbs">
                          {currResult.Fields.Breadcrumb.Values.map(
                            (breadcrumb: any, i: number) => (
                              <React.Fragment key={i}>
                                {breadcrumb}
                                {i <
                                  currResult.Fields.Breadcrumb.Values.length -
                                    1 && <span aria-hidden="true">{' > '}</span>}
                              </React.Fragment>
                            ),
                          )}
                        </span>
                      )
                    : []}
                </div>
              </div>
            </div>
          </CustomResult>
        )}
      />
      <Pagination disableTheme={true} className="pager pager__items" />
      {resultCount > 0 && !isLoading && (
        <CludoHeaderResults showResults={false}/>
      )}
      <CludoSearchServices />
    </>
  );
}
