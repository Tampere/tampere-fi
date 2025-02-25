const contentListing = window.drupalSettings?.tampere?.contentListing;
export const API_URL = Object.values(contentListing)[0]?.apiUrl;

export const ALPHABETICAL_FILTERS = [
  "A",
  "B",
  "C",
  "D",
  "E",
  "F",
  "G",
  "H",
  "I",
  "J",
  "K",
  "L",
  "M",
  "N",
  "O",
  "P",
  "Q",
  "R",
  "S",
  "T",
  "U",
  "V",
  "W",
  "X",
  "Y",
  "Z",
  "Å",
  "Ä",
  "Ö",
];

export const PAGER_DELIMITER = "...";

export const FILTER_HELP_TEXT = Drupal.t(
  "When you select a filter, the search results are automatically narrowed down to your selection.",
  {},
  { context: "Content listing filters help text" },
);

export const FILTER_TITLE = Drupal.t(
  "Filter results",
  {},
  { context: "Content listing filters title" },
);
