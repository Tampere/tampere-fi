import React from "react";
import { createRoot } from "react-dom/client";
import App from "./App";

const drupalData = window.drupalSettings?.tampere?.contentListing;

if (drupalData) {
  const listing = Object.values(drupalData)[0];
  const rootElement = document.getElementById(listing.listingId);

  if (rootElement) {
    createRoot(rootElement).render(
      <App
        title={listing.title}
        description={listing.description}
        termId={listing.termId}
        filterType={listing.filterType}
        filterValues={listing.filterValues}
      />
    );
  }
}
