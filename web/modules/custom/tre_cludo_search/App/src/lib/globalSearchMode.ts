export type SearchMode = "main" | "external";

let globalSearchMode: SearchMode = "main";

export const setGlobalSearchMode = (mode: SearchMode) => {
  globalSearchMode = mode;
};

export const getGlobalSearchMode = () => globalSearchMode;
