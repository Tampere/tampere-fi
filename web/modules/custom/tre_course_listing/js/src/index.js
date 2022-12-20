import React from "react";
import { createRoot } from "react-dom/client";

import App from "./App";

const courseListings = window.drupalSettings.tampere.courseListings || {};

Object.keys(courseListings).forEach(key => {
  const courseListing = courseListings[key];
  const container = document.getElementById(courseListing.element_id);
  const root = createRoot(container);

  root.render(
    <App
      title={courseListing.title}
      description={courseListing.description}
      originalItems={courseListing.courseData}
      sortType={courseListing.sort}
      listingType={courseListing.bundle}
    />
  );
});
