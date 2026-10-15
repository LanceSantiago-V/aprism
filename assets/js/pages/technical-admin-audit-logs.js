(() => {
  const init = () => {
    const form = document.getElementById("auditFiltersForm");
    const input = document.getElementById("auditSearch");
    const clearButton = document.getElementById("clearAuditSearch");
    const suggestions = document.getElementById("auditSearchSuggestions");
    const root = document.querySelector("[data-audit-table]");
    const body = root?.querySelector(".audit-table tbody");
    const pagination = document.querySelector(".pagination-container");
    const recordCount = document.querySelector(".page-header .record-count");
    if (!form || !input || !clearButton || !suggestions || !root || !body || !pagination) return;

    let debounceTimer = 0;
    let requestSequence = 0;
    let controller = null;
    let activeSuggestion = -1;
    let suggestionItems = [];
    let suggestionsDismissed = false;

    const currentPageSize = () => Number(root.dataset.pageSize) || Number(form.elements.per_page?.value) || 6;
    const collectParams = (page = 1) => {
      const params = new URLSearchParams();
      const data = new FormData(form);
      for (const [key, value] of data.entries()) {
        const normalized = String(value).trim();
        if (normalized !== "") params.set(key, normalized);
      }
      params.set("page", String(page));
      params.set("per_page", String(currentPageSize()));
      params.delete("focus_id");
      params.delete("anchor_id");
      return params;
    };

    const urlFor = (params, ajax = false) => {
      const next = new URLSearchParams(params);
      if (ajax) next.set("ajax", "1");
      else next.delete("ajax");
      return `${window.location.pathname}${next.toString() ? `?${next}` : ""}`;
    };

    const hideSuggestions = () => {
      suggestions.hidden = true;
      suggestions.replaceChildren();
      input.setAttribute("aria-expanded", "false");
      input.removeAttribute("aria-activedescendant");
      activeSuggestion = -1;
      suggestionItems = [];
    };

    const dismissSuggestions = () => {
      suggestionsDismissed = true;
      hideSuggestions();
    };

    const updateClearButton = () => {
      clearButton.hidden = input.value.length === 0;
    };

    const setText = (selector, value) => {
      const target = document.querySelector(selector);
      if (target) target.textContent = value;
    };

    const createCell = (className, content) => {
      const cell = document.createElement("td");
      if (className) cell.className = className;
      if (content instanceof Node) cell.append(content);
      else cell.textContent = String(content ?? "");
      return cell;
    };

    const createDetailButton = (record) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "details-view-btn";
      button.title = "View details";
      button.setAttribute("aria-label", `View details for ${record.full_name || "audit record"}`);
      button.dataset.aprismTooltip = "View details";
      button.dataset.bsToggle = "modal";
      button.dataset.bsTarget = "#auditDetailsModal";
      button.dataset.auditId = String(record.audit_log_id);
      button.dataset.user = record.full_name || "";
      button.dataset.employee = record.employee_number || "";
      button.dataset.username = record.username || "";
      button.dataset.role = record.role_name || "";
      button.dataset.action = record.action || "";
      button.dataset.ip = record.ip_address || "—";
      button.dataset.date = record.timestamp || "";
      button.dataset.description = record.description || "";
      const icon = document.createElement("i");
      icon.dataset.lucide = "eye";
      icon.setAttribute("aria-hidden", "true");
      button.append(icon);
      return button;
    };

    const renderRecords = (records, hasFilters) => {
      body.replaceChildren();
      if (!records.length) {
        const row = document.createElement("tr");
        const cell = document.createElement("td");
        cell.colSpan = 6;
        const state = document.createElement("div");
        state.className = "empty-state";
        const icon = document.createElement("i");
        icon.dataset.lucide = "file-search";
        icon.setAttribute("aria-hidden", "true");
        const title = document.createElement("div");
        title.className = "empty-state-title";
        title.textContent = hasFilters ? "No matching audit records." : "No audit records yet.";
        const message = document.createElement("p");
        message.className = "empty-state-text";
        message.textContent = hasFilters
          ? "Try adjusting or clearing the current filters."
          : "Administrative and system activity will automatically appear here.";
        state.append(icon, title, message);
        cell.append(state);
        row.append(cell);
        body.append(row);
        return;
      }

      const fragment = document.createDocumentFragment();
      for (const record of records) {
        const row = document.createElement("tr");
        row.id = `audit-record-${record.audit_log_id}`;
        row.dataset.adaptiveRecord = "";
        row.dataset.recordAnchor = String(record.audit_log_id);

        row.append(createCell("font-mono-custom audit-timestamp", record.timestamp));
        const userValue = document.createElement("div");
        userValue.className = "audit-user-value";
        const name = document.createElement("div");
        name.className = "audit-user";
        name.textContent = record.full_name || "—";
        const employee = document.createElement("div");
        employee.className = "audit-employee";
        employee.textContent = record.employee_number || "";
        userValue.append(name, employee);
        row.append(createCell("", userValue));

        const role = document.createElement("span");
        role.className = "badge-role";
        role.textContent = record.role_name || "—";
        row.append(createCell("text-center", role));

        const action = document.createElement("span");
        action.className = "action-badge";
        action.textContent = record.action || "—";
        row.append(createCell("text-center", action));
        row.append(createCell("font-mono-custom audit-ip-address", record.ip_address || "—"));
        row.append(createCell("text-center", createDetailButton(record)));
        fragment.append(row);
      }
      body.append(fragment);
      if (window.lucide?.createIcons) window.lucide.createIcons();
    };

    const pageLink = (page, label, icon, disabled, currentPage = false) => {
      const element = document.createElement(disabled ? "span" : "a");
      element.className = `page-link-custom${disabled ? " disabled" : ""}${currentPage ? " active" : ""}`;
      element.setAttribute("aria-label", label);
      element.title = label;
      if (disabled) {
        element.setAttribute("aria-disabled", "true");
      } else {
        const params = new URLSearchParams(window.location.search);
        params.delete("ajax");
        params.set("page", String(page));
        params.set("per_page", String(currentPageSize()));
        element.href = urlFor(params);
        element.dataset.auditPage = String(page);
        if (icon) element.dataset.aprismTooltip = label;
      }
      if (currentPage) element.setAttribute("aria-current", "page");
      if (icon) {
        const node = document.createElement("i");
        node.dataset.lucide = icon;
        node.setAttribute("aria-hidden", "true");
        element.append(node);
      } else {
        element.textContent = String(page);
      }
      return element;
    };

    const renderPagination = (data) => {
      pagination.hidden = data.total_records === 0;
      pagination.dataset.capacityPending = "false";
      setText("#auditPaginationStart", Number(data.first_record).toLocaleString());
      setText("#auditPaginationEnd", Number(data.last_record).toLocaleString());
      setText("#auditPaginationTotal", Number(data.total_records).toLocaleString());
      if (recordCount) recordCount.textContent = `${Number(data.total_records).toLocaleString()} ${data.total_records === 1 ? "record" : "records"}`;

      let nav = pagination.querySelector("#auditPaginationControls");
      if (!nav) {
        nav = document.createElement("nav");
        nav.className = "pagination-controls";
        nav.id = "auditPaginationControls";
        nav.setAttribute("aria-label", "Audit log pages");
        pagination.append(nav);
      }
      nav.replaceChildren();
      const pages = Number(data.total_pages) || 0;
      if (pages <= 1) {
        nav.hidden = true;
        return;
      }
      nav.hidden = false;
      const current = Number(data.current_page) || 1;
      nav.append(pageLink(1, "First page", "chevrons-left", current <= 1));
      nav.append(pageLink(current - 1, "Previous page", "chevron-left", current <= 1));
      const firstPage = Math.max(1, Math.min(current - 1, pages - 2));
      const lastPage = Math.min(pages, firstPage + 2);
      for (let page = firstPage; page <= lastPage; page += 1) {
        nav.append(pageLink(page, `Page ${page}`, null, false, page === current));
      }
      nav.append(pageLink(current + 1, "Next page", "chevron-right", current >= pages));
      nav.append(pageLink(pages, "Last page", "chevrons-right", current >= pages));
      if (window.lucide?.createIcons) window.lucide.createIcons();
    };

    const renderSuggestions = (items) => {
      suggestionItems = items.slice(0, 5);
      activeSuggestion = -1;
      suggestions.replaceChildren();
      if (suggestionsDismissed || !input.value.trim() || !suggestionItems.length) {
        hideSuggestions();
        return;
      }
      suggestionItems.forEach((item, index) => {
        const option = document.createElement("button");
        option.type = "button";
        option.id = `audit-suggestion-${index}`;
        option.className = "audit-search-suggestion";
        option.setAttribute("role", "option");
        option.setAttribute("aria-selected", "false");
        option.dataset.suggestionIndex = String(index);
        const value = document.createElement("span");
        value.className = "audit-search-suggestion-value";
        value.textContent = item.value;
        const kind = document.createElement("span");
        kind.className = "audit-search-suggestion-kind";
        kind.textContent = item.type;
        option.append(value, kind);
        option.addEventListener("pointerdown", (event) => event.preventDefault());
        option.addEventListener("click", () => applySuggestion(index));
        suggestions.append(option);
      });
      suggestions.hidden = false;
      input.setAttribute("aria-expanded", "true");
    };

    const setActiveSuggestion = (index) => {
      if (!suggestionItems.length) return;
      activeSuggestion = (index + suggestionItems.length) % suggestionItems.length;
      [...suggestions.querySelectorAll('[role="option"]')].forEach((option, optionIndex) => {
        const active = optionIndex === activeSuggestion;
        option.setAttribute("aria-selected", String(active));
        if (active) input.setAttribute("aria-activedescendant", option.id);
      });
    };

    const applySuggestion = (index) => {
      const item = suggestionItems[index];
      if (!item) return;
      input.value = item.value;
      updateClearButton();
      dismissSuggestions();
      requestRecords({ page: 1, history: "replace" });
      input.focus();
    };

    const requestRecords = async ({ page = 1, history = "replace" } = {}) => {
      const params = collectParams(page);
      const displayUrl = urlFor(params);
      if (history === "push") window.history.pushState({}, "", displayUrl);
      else window.history.replaceState({}, "", displayUrl);
      const sequence = ++requestSequence;
      controller?.abort();
      controller = new AbortController();
      try {
        const response = await fetch(urlFor(params, true), {
          headers: { Accept: "application/json" },
          credentials: "same-origin",
          signal: controller.signal,
        });
        if (!response.ok) throw new Error("Audit records could not be refreshed.");
        const data = await response.json();
        if (sequence !== requestSequence || !data.success) return;
        renderRecords(data.records || [], Boolean(data.has_filters));
        renderPagination(data);
        root.dataset.currentPage = String(data.current_page || 1);
        if (!suggestionsDismissed) renderSuggestions(data.suggestions || []);
      } catch (error) {
        if (error.name === "AbortError") return;
        window.location.assign(displayUrl);
      }
    };

    form.addEventListener("submit", (event) => {
      event.preventDefault();
      window.clearTimeout(debounceTimer);
      dismissSuggestions();
      requestRecords({ page: 1, history: "replace" });
    });

    input.addEventListener("input", () => {
      updateClearButton();
      suggestionsDismissed = false;
      if (!input.value.trim()) hideSuggestions();
      window.clearTimeout(debounceTimer);
      debounceTimer = window.setTimeout(() => requestRecords({ page: 1, history: "replace" }), 180);
    });

    clearButton.addEventListener("click", () => {
      input.value = "";
      updateClearButton();
      dismissSuggestions();
      requestRecords({ page: 1, history: "replace" });
      input.focus();
    });

    for (const control of [form.elements.action, form.elements.date_from, form.elements.date_to]) {
      control?.addEventListener("change", () => {
        window.clearTimeout(debounceTimer);
        dismissSuggestions();
        requestRecords({ page: 1, history: "replace" });
      });
    }

    input.addEventListener("keydown", (event) => {
      if (event.key === "ArrowDown" && !suggestions.hidden) {
        event.preventDefault();
        setActiveSuggestion(activeSuggestion + 1);
      } else if (event.key === "ArrowUp" && !suggestions.hidden) {
        event.preventDefault();
        setActiveSuggestion(activeSuggestion < 0 ? suggestionItems.length - 1 : activeSuggestion - 1);
      } else if (event.key === "Enter" && activeSuggestion >= 0 && !suggestions.hidden) {
        event.preventDefault();
        applySuggestion(activeSuggestion);
      } else if (event.key === "Escape" && !suggestions.hidden) {
        event.preventDefault();
        dismissSuggestions();
      }
    });

    document.addEventListener("click", (event) => {
      if (!form.contains(event.target)) dismissSuggestions();
    });

    pagination.addEventListener("click", (event) => {
      const link = event.target.closest("a[data-audit-page]");
      if (!link) return;
      event.preventDefault();
      dismissSuggestions();
      const targetPage = Number(link.dataset.auditPage) || 1;
      requestRecords({ page: targetPage, history: "push" });
    });

    window.addEventListener("popstate", () => {
      const params = new URLSearchParams(window.location.search);
      for (const name of ["search", "action", "date_from", "date_to"]) {
        if (form.elements[name]) form.elements[name].value = params.get(name) || "";
      }
      updateClearButton();
      dismissSuggestions();
      requestRecords({ page: Number(params.get("page")) || 1, history: "replace" });
    });

    updateClearButton();
    pagination.addEventListener("focusin", (event) => {
      const item = event.target.closest("[data-aprism-tooltip]");
      if (item) item.setAttribute("aria-label", item.getAttribute("aria-label") || item.dataset.aprismTooltip);
    });
  };

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init, { once: true });
  else init();
})();
