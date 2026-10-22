document.addEventListener("DOMContentLoaded", () => {
  const workspace = document.querySelector(".referral-workspace");
  if (!workspace) return;

  const rows = Array.from(workspace.querySelectorAll("[data-referral-row]"));
  const search = workspace.querySelector("[data-referral-search]");
  const section = workspace.querySelector("[data-referral-section]");
  const statusButtons = Array.from(workspace.querySelectorAll("[data-referral-status-filter]"));
  const clearButton = workspace.querySelector("[data-referral-clear]");
  const table = workspace.querySelector(".referral-table");
  const range = workspace.querySelector("[data-referral-range]");
  const pageControls = workspace.querySelector("[data-referral-page-controls]");
  const emptyMessage = workspace.querySelector("[data-referral-no-matches]");
  const exportButton = workspace.querySelector("[data-referral-export]");
  let activeStatus = "all";
  let currentPage = 1;
  const pageSize = 8;

  const getMatches = () => {
    const query = (search?.value || "").trim().toLocaleLowerCase();
    const selectedSection = section?.value || "";
    return rows.filter((row) => {
      const matchesStatus = activeStatus === "all" || row.dataset.status === activeStatus;
      const matchesSection = !selectedSection || row.dataset.section === selectedSection;
      const searchable = `${row.textContent} ${row.dataset.student || ""} ${row.dataset.studentNumber || ""} ${row.dataset.subject || ""} ${row.dataset.section || ""} ${row.dataset.reason || ""} ${row.dataset.note || ""} ${row.dataset.realStatus || ""}`.toLocaleLowerCase();
      const matchesQuery = !query || searchable.includes(query);
      return matchesStatus && matchesSection && matchesQuery;
    });
  };

  const render = () => {
    const matches = getMatches();
    const totalPages = Math.max(1, Math.ceil(matches.length / pageSize));
    currentPage = Math.min(currentPage, totalPages);
    const start = (currentPage - 1) * pageSize;
    const visibleRows = new Set(matches.slice(start, start + pageSize));

    rows.forEach((row) => {
      const visible = visibleRows.has(row);
      row.hidden = !visible;
    });

    if (range) {
      range.textContent = matches.length
        ? `Showing ${start + 1} to ${Math.min(start + pageSize, matches.length)} of ${matches.length} referral records`
        : "Showing 0 referral records";
    }
    if (emptyMessage) emptyMessage.hidden = matches.length !== 0;
    if (table) table.hidden = rows.length > 0 && matches.length === 0;

    if (!pageControls) return;
    pageControls.replaceChildren();
    pageControls.hidden = matches.length === 0;
    if (matches.length === 0) return;
    const addControl = (label, text, page, disabled = false, current = false) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = current ? "is-current" : "";
      button.textContent = text;
      button.setAttribute("aria-label", label);
      if (current) button.setAttribute("aria-current", "page");
      button.disabled = disabled;
      button.addEventListener("click", () => { currentPage = page; render(); });
      pageControls.appendChild(button);
    };

    const addIconControl = (label, glyph, page, disabled) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "referral-pagination-icon";
      button.textContent = glyph;
      button.setAttribute("aria-label", label);
      button.title = label;
      button.disabled = disabled;
      button.addEventListener("click", () => { currentPage = page; render(); });
      pageControls.appendChild(button);
    };

    addIconControl("First page", "«", 1, currentPage <= 1);
    addIconControl("Previous page", "‹", Math.max(1, currentPage - 1), currentPage <= 1);
    const visiblePages = new Set();
    if (totalPages <= 5) {
      for (let page = 1; page <= totalPages; page++) visiblePages.add(page);
    } else {
      visiblePages.add(1);
      visiblePages.add(totalPages);
      for (let page = Math.max(1, currentPage - 1); page <= Math.min(totalPages, currentPage + 1); page++) visiblePages.add(page);
    }
    let previousPage = 0;
    [...visiblePages].sort((a, b) => a - b).forEach((page) => {
      if (previousPage && page - previousPage > 1) {
        const dots = document.createElement("span");
        dots.className = "referral-pagination-ellipsis";
        dots.textContent = "…";
        dots.setAttribute("aria-hidden", "true");
        pageControls.appendChild(dots);
      }
      addControl(`Page ${page}`, String(page), page, false, page === currentPage);
      previousPage = page;
    });
    addIconControl("Next page", "›", Math.min(totalPages, currentPage + 1), currentPage >= totalPages);
    addIconControl("Last page", "»", totalPages, currentPage >= totalPages);
  };

  statusButtons.forEach((button) => button.addEventListener("click", () => {
    activeStatus = button.dataset.referralStatusFilter || "all";
    currentPage = 1;
    statusButtons.forEach((item) => {
      const selected = item === button;
      item.classList.toggle("is-active", selected);
      item.setAttribute("aria-pressed", String(selected));
    });
    render();
  }));
  search?.addEventListener("input", () => { currentPage = 1; render(); });
  section?.addEventListener("change", () => { currentPage = 1; render(); });
  clearButton?.addEventListener("click", () => {
    if (search) search.value = "";
    if (section) section.value = "";
    activeStatus = "all";
    currentPage = 1;
    statusButtons.forEach((button) => {
      const selected = button.dataset.referralStatusFilter === "all";
      button.classList.toggle("is-active", selected);
      button.setAttribute("aria-pressed", String(selected));
    });
    render();
    search?.focus();
  });

  const csvCell = (value) => {
    let text = String(value ?? "").replace(/[\r\n]+/g, " ").trim();
    if (/^[\s]*[=+\-@]/.test(text)) text = `'${text}`;
    return `"${text.replace(/"/g, '""')}"`;
  };
  exportButton?.addEventListener("click", () => {
    const records = getMatches();
    const lines = [["Student", "Student Number", "Subject", "Section", "Referral Reason", "Supporting Note", "Submitted", "Status"]];
    records.forEach((row) => lines.push([
      row.dataset.student,
      row.dataset.studentNumber,
      row.dataset.subject,
      row.dataset.section,
      row.dataset.reason,
      row.dataset.note,
      row.dataset.submitted,
      row.dataset.realStatus,
    ]));
    const blob = new Blob(["\uFEFF" + lines.map((line) => line.map(csvCell).join(",")).join("\r\n")], { type: "text/csv;charset=utf-8" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "aprism-referral-history.csv";
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(link.href);
  });
  workspace.querySelector("[data-referral-print]")?.addEventListener("click", () => window.print());
  window.addEventListener("beforeprint", () => getMatches().forEach((row) => { row.hidden = false; }));
  window.addEventListener("afterprint", render);

  render();
});
