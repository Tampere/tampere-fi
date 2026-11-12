let lastSearch: CustomEvent | undefined;

// Registered at bundle load so the first search is captured too.
window.addEventListener("cludoSearchSuccess", (event) => {
  lastSearch = event as CustomEvent;
});

// Cludo's AI summary mounts after the first search has already fired
// `cludoSearchSuccess`, so it never sees it. Replay the event once if the
// summary is still empty after mounting.
export function replayMissedSearchForAiSummary(): () => void {
  let timer: number | undefined;

  const observer = new MutationObserver(() => {
    if (!document.querySelector(".cludo-ai-summary")) {
      return;
    }
    observer.disconnect();
    timer = window.setTimeout(() => {
      const summary = document.querySelector(".cludo-ai-summary");
      if (lastSearch && summary && !summary.textContent?.trim()) {
        window.dispatchEvent(
          new CustomEvent("cludoSearchSuccess", {
            detail: lastSearch.detail,
            bubbles: true,
          }),
        );
      }
    }, 500);
  });
  observer.observe(document.body, { childList: true, subtree: true });

  return () => {
    observer.disconnect();
    window.clearTimeout(timer);
  };
}
