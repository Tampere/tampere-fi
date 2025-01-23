export const LISTING_TYPE_DEFAULT = "course_listing";

export const LISTING_TYPE_SPORTS = "course_listing_sports";

export const FILTER_TYPES_BY_LISTING_TYPE = {
  [LISTING_TYPE_DEFAULT]: [ "department", "subject", "period" ],
  [LISTING_TYPE_SPORTS]: [ "department", "category", "period", "locationgroup" ]
};

export const FILTER_TYPES_WO_COMPLEX_RELATIONSHIPS = [ "period" ];

export const FILTER_TYPE_PARENTS = [ "term" ];

// Some filter labels need to be renamed only in this app and not at the
// source. The replacements are presented in the following format:
// "original label": "replacement label".
export const FILTER_LABEL_REPLACEMENTS_BY_LISTING_TYPE = {
  [LISTING_TYPE_SPORTS]: {
    "Autumn term": "Autumn season",
    "Spring term": "Spring season",
    "Kevätlukukausi": "Kevätkausi",
    "Syyslukukausi": "Syyskausi"
  }
};

export const PAGER_DELIMITER = "...";
