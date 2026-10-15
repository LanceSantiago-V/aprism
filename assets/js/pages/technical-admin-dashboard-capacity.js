(() => {
  const dashboard = document.querySelector(".main-content.aprism-dashboard");
  if (!dashboard) return;

  const auditContainer = dashboard.querySelector("[data-dashboard-audit-preview]");
  const backupStack = dashboard.querySelector(".backup-stack");
  const previewGrid = dashboard.querySelector(".dashboard-secondary-grid");
  const auditBody = auditContainer?.querySelector("tbody");
  const auditTable = auditContainer?.querySelector("table");
  let timer = 0;
  let request = null;
  let loadedCount = auditBody?.querySelectorAll("[data-dashboard-audit-row]").length || 0;

  const shortDate = (value) => {
    const date = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(date.getTime())) return String(value || "");
    return new Intl.DateTimeFormat("en-US", {
      month: "short", day: "2-digit", year: "numeric",
      hour: "2-digit", minute: "2-digit", hour12: true
    }).format(date).replace(",", "");
  };

  const addCell = (row, content, className = "") => {
    const cell = document.createElement("td");
    if (className) cell.className = className;
    if (content instanceof Node) cell.append(content);
    else cell.textContent = content == null || content === "" ? "—" : String(content);
    row.append(cell);
    return cell;
  };

  const makeAuditRow = (record) => {
    const row = document.createElement("tr");
    row.dataset.dashboardAuditRow = "";
    row.dataset.auditId = String(record.audit_log_id);

    const timeCell = document.createElement("td");
    timeCell.className = "font-mono-custom";
    const recordLink = document.createElement("a");
    recordLink.className = "dashboard-audit-record-link";
    recordLink.href = `${window.APP_URL}/dashboard/technical_admin_audit_logs.php?focus_id=${encodeURIComponent(record.audit_log_id)}`;
    const hiddenName = document.createElement("span");
    hiddenName.className = "visually-hidden";
    hiddenName.textContent = `Open audit record from ${shortDate(record.created_at)}`;
    recordLink.append(hiddenName);
    timeCell.append(recordLink, document.createTextNode(shortDate(record.created_at)));
    row.append(timeCell);

    addCell(row, record.full_name || "System");
    const roleCell = document.createElement("td");
    const role = document.createElement("span");
    role.className = "badge-role admin";
    role.textContent = record.role_name || "System";
    roleCell.append(role);
    row.append(roleCell);
    addCell(row, record.action);
    addCell(row, record.ip_address || "—", "text-center font-mono-custom");

    const detailCell = document.createElement("td");
    detailCell.className = "text-center";
    const detail = document.createElement("button");
    detail.type = "button";
    detail.className = "details-view-btn";
    detail.title = "View audit event details";
    detail.setAttribute("aria-label", `View audit event details for ${record.full_name || "System"}`);
    detail.dataset.bsToggle = "modal";
    detail.dataset.bsTarget = "#dashboardAuditDetailsModal";
    detail.dataset.auditUser = record.full_name || "System";
    detail.dataset.auditRole = record.role_name || "System";
    detail.dataset.auditAction = record.action || "";
    detail.dataset.auditIp = record.ip_address || "—";
    detail.dataset.auditDate = shortDate(record.created_at);
    detail.dataset.auditDescription = record.description || "";
    const icon = document.createElement("i");
    icon.setAttribute("data-lucide", "eye");
    detail.append(icon);
    detailCell.append(detail);
    row.append(detailCell);
    return row;
  };

  const isSideBySide = () => {
    return dashboard.clientWidth >= 1008;
  };

  const auditCapacity = () => {
    if (!auditContainer || !auditTable || !isSideBySide()) return loadedCount;
    const head = auditTable.tHead?.getBoundingClientRect().height || 0;
    const screenRoom = Math.max(0, window.innerHeight - auditContainer.getBoundingClientRect().top - 12);
    const available = Math.max(0, Math.min(auditContainer.clientHeight, screenRoom) - head - 2);
    const rows = [...(auditBody?.querySelectorAll("[data-dashboard-audit-row]") || [])];
    const heights = rows.map((row) => row.getBoundingClientRect().height).filter((height) => height > 0);
    const average = heights.length ? heights.reduce((sum, height) => sum + height, 0) / heights.length : 0;
    return average ? Math.max(1, Math.min(1000, Math.floor(available / average))) : loadedCount;
  };

  const fitAuditRows = () => {
    if (!auditContainer || !auditBody || !isSideBySide()) return;
    const records = [...auditBody.querySelectorAll("[data-dashboard-audit-row]")];
    records.forEach((row) => { row.hidden = false; });
    const tableHeadHeight = auditTable?.tHead?.getBoundingClientRect().height || 0;
    const screenRoom = Math.max(0, window.innerHeight - auditContainer.getBoundingClientRect().top - 12);
    let remaining = Math.max(0, Math.min(auditContainer.clientHeight, screenRoom) - tableHeadHeight - 2);
    let hasRoom = true;
    records.forEach((row) => {
      const height = row.getBoundingClientRect().height;
      const fits = hasRoom && height > 0 && height <= remaining + 1;
      row.hidden = !fits;
      if (fits) remaining -= height;
      else hasRoom = false;
    });
  };

  const fitBackups = () => {
    if (!backupStack || !isSideBySide()) return;
    const items = [...backupStack.querySelectorAll("[data-dashboard-backup-preview-item]")];
    items.forEach((item) => { item.hidden = false; });
    const screenRoom = Math.max(0, window.innerHeight - backupStack.getBoundingClientRect().top - 20);
    let remaining = Math.min(backupStack.clientHeight, screenRoom);
    const gap = Number.parseFloat(getComputedStyle(backupStack).rowGap) || 0;
    let hasRoom = true;
    items.forEach((item, index) => {
      const height = item.getBoundingClientRect().height;
      const fits = hasRoom && height > 0 && height + (index ? gap : 0) <= remaining + 1;
      item.hidden = !fits;
      if (fits) remaining -= height + (index ? gap : 0);
      else hasRoom = false;
    });
  };

  const loadMoreAuditRows = async (capacity) => {
    if (!auditBody || !auditContainer || capacity <= loadedCount || request) return;
    request = new AbortController();
    try {
      const url = new URL(window.location.href);
      url.search = "";
      url.searchParams.set("dashboard_audit_preview", "json");
      url.searchParams.set("limit", String(capacity));
      const response = await fetch(url, { credentials: "same-origin", signal: request.signal });
      if (!response.ok) return;
      const records = await response.json();
      if (!Array.isArray(records)) return;
      auditBody.replaceChildren(...records.map(makeAuditRow));
      loadedCount = records.length;
      if (window.lucide?.createIcons) window.lucide.createIcons();
      fitAuditRows();
    } catch (error) {
      if (error.name !== "AbortError") console.warn("Dashboard audit preview could not be expanded.");
    } finally {
      request = null;
    }
  };

  const update = () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(async () => {
      const sideBySide = isSideBySide();
      if (previewGrid && sideBySide) {
        const gridTop = previewGrid.getBoundingClientRect().top;
        previewGrid.style.height = `${Math.max(0, window.innerHeight - gridTop - 20)}px`;
        previewGrid.style.flex = "1 1 auto";
        previewGrid.style.minHeight = "0";
      } else if (previewGrid) {
        previewGrid.style.removeProperty("height");
        previewGrid.style.removeProperty("flex");
        previewGrid.style.removeProperty("min-height");
      }

      if (!sideBySide) {
        if (auditBody) [...auditBody.querySelectorAll("[data-dashboard-audit-row]")].forEach((row) => { row.hidden = false; });
        if (backupStack) [...backupStack.querySelectorAll("[data-dashboard-backup-preview-item]")].forEach((item) => { item.hidden = false; });
        return;
      }
      await loadMoreAuditRows(auditCapacity());
      fitAuditRows();
      fitBackups();
    }, 160);
  };

  window.addEventListener("resize", update, { passive: true });
  window.addEventListener("orientationchange", update, { passive: true });
  document.getElementById("sidebarToggle")?.addEventListener("click", update);
  if ("ResizeObserver" in window) {
    const observer = new ResizeObserver(update);
    [dashboard, auditContainer, backupStack].filter(Boolean).forEach((element) => observer.observe(element));
  }
  update();
})();
