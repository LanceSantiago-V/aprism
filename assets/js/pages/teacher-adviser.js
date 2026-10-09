document.addEventListener("DOMContentLoaded", () => {
  const riskServerSearchInput = document.querySelector("[data-adviser-risk-server-search]");
  if (riskServerSearchInput?.form) {
    let riskSearchTimer;
    riskServerSearchInput.addEventListener("input", () => {
      window.clearTimeout(riskSearchTimer);
      riskSearchTimer = window.setTimeout(() => {
        const params = new URL(window.location.href).searchParams;
        const currentSearch = (params.get("search") || "").trim();
        const currentPage = Number.parseInt(params.get("page") || "1", 10);
        if (riskServerSearchInput.value.trim() === currentSearch && currentPage === 1) return;

        if (typeof riskServerSearchInput.form.requestSubmit === "function") {
          riskServerSearchInput.form.requestSubmit();
        } else {
          riskServerSearchInput.form.submit();
        }
      }, 400);
    });
  }

  const serverSearchInput = document.querySelector("[data-adviser-server-search]");
  if (serverSearchInput?.form) {
    let searchTimer;
    serverSearchInput.addEventListener("input", () => {
      window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(() => {
        const currentSearch = (new URL(window.location.href).searchParams.get("search") || "").trim();
        if (serverSearchInput.value.trim() === currentSearch) return;

        if (typeof serverSearchInput.form.requestSubmit === "function") {
          serverSearchInput.form.requestSubmit();
        } else {
          serverSearchInput.form.submit();
        }
      }, 400);
    });
  }

  const input = document.querySelector("[data-adviser-search]");
  const rows = [
    ...document.querySelectorAll(
      "[data-adviser-student-row], [data-adviser-monitoring-row], [data-adviser-risk-row], [data-adviser-followup-row]"
    ),
  ];
  input?.addEventListener("input", () => {
    const term = input.value.trim().toLowerCase();
    rows.forEach((row) => {
      row.hidden = term !== "" && !row.textContent.toLowerCase().includes(term);
    });
  });
});
