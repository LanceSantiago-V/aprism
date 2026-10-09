(function () {
  "use strict";
  const search = document.querySelector("[data-ledger-search]");
  search?.addEventListener("input", () => {
    const query = search.value.trim().toLowerCase();
    document.querySelectorAll("[data-ledger-row]").forEach((row) => {
      row.hidden =
        query !== "" && !row.textContent.toLowerCase().includes(query);
    });
  });
})();
