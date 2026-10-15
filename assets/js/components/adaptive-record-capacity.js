(() => {
  const roots = [...document.querySelectorAll("[data-adaptive-record-capacity]")];
  if (!roots.length) return;

  roots.forEach((root) => {
    const table = root.querySelector("table");
    const body = table?.tBodies?.[0];
    if (!table || !body) return;

    const pageParam = root.dataset.pageParam || "per_page";
    const anchorParam = root.dataset.anchorParam || "anchor_id";
    const focusParam = root.dataset.focusParam || "focus_id";
    const pagination = document.querySelector(root.dataset.paginationSelector || "");
    const surface = root.closest("[data-adaptive-capacity-surface]") || root.parentElement;
    const pageShell = root.closest(".main-content") || document.documentElement;
    let timer = 0;
    let navigationPending = false;
    let forceCapacityCheck = false;

    const records = () => [...body.querySelectorAll("[data-adaptive-record]")];

    const reveal = () => {
      root.dataset.capacityPending = "false";
      root.setAttribute("aria-busy", "false");
      if (pagination) pagination.dataset.capacityPending = "false";
    };

    const markPending = () => {
      root.dataset.capacityPending = "true";
      root.setAttribute("aria-busy", "true");
      if (pagination) pagination.dataset.capacityPending = "true";
    };

    const height = (element) => element?.getBoundingClientRect().height || 0;

    const calculateCapacity = (items) => {
      const rowHeights = items
        .map(height)
        .filter((rowHeight) => rowHeight > 0);
      const rowHeight = rowHeights.length ? Math.max(...rowHeights) : 0;
      if (!rowHeight) return 0;

      const rootStyle = window.getComputedStyle(root);
      const surfaceStyle = window.getComputedStyle(surface);
      const shellStyle = window.getComputedStyle(pageShell);
      const tableStyle = window.getComputedStyle(table);
      const rootPadding =
        (Number.parseFloat(rootStyle.paddingTop) || 0) +
        (Number.parseFloat(rootStyle.paddingBottom) || 0);
      const tableMargins =
        (Number.parseFloat(tableStyle.marginTop) || 0) +
        (Number.parseFloat(tableStyle.marginBottom) || 0);
      const surfaceBottomPadding = Number.parseFloat(surfaceStyle.paddingBottom) || 0;
      const shellBottomPadding = Number.parseFloat(shellStyle.paddingBottom) || 0;
      const headerHeight = height(table.tHead);
      const rootRect = root.getBoundingClientRect();
      const paginationRect = pagination?.getBoundingClientRect();
      const paginationGap = paginationRect
        ? Math.max(0, paginationRect.top - rootRect.bottom)
        : 0;
      const footerHeight = height(pagination);
      const totalRecords = Math.max(0, Number(root.dataset.totalRecords) || 0);
      const footerInfo = pagination?.querySelector(".pagination-info, .backup-pagination-info");
      const footerControls = pagination?.querySelector(".pagination-controls, .backup-pagination-controls");
      const infoHeight = height(footerInfo);
      const controlsHeight = height(footerControls);
      const footerFixedSpace = Math.max(0, footerHeight - Math.max(infoHeight, controlsHeight));
      const allRecordsPageFooter = footerFixedSpace + infoHeight;
      const currentPageFooter = footerFixedSpace + Math.max(infoHeight, controlsHeight);
      const topDocument = Math.max(0, rootRect.top + window.scrollY);
      const viewportDataRegion = Math.max(
        0,
        window.innerHeight - topDocument - shellBottomPadding - surfaceBottomPadding - paginationGap
      );
      const usableBody = (candidate) => {
        const footerReserve = candidate >= totalRecords && totalRecords > 0
          ? allRecordsPageFooter
          : currentPageFooter;
        return Math.max(
          0,
          viewportDataRegion - footerReserve - rootPadding - tableMargins - headerHeight
        );
      };
      const tbodyStyle = window.getComputedStyle(body);
      const rowGap = Number.parseFloat(tbodyStyle.rowGap || tbodyStyle.gap || "0") || 0;
      const rowPitch = rowHeight + rowGap;
      let capacity = Math.max(1, Math.min(100,
        Math.floor((usableBody(0) + 0.5) / rowPitch)
      ));

      /* A one-page result can remove the pagination controls. Test that exact
         candidate with its resulting footer, then use the largest complete fit. */
      if (totalRecords > 0) {
        const allRecordsFit = totalRecords <= 100 &&
          totalRecords * rowPitch <= usableBody(totalRecords) + 0.5;
        capacity = allRecordsFit ? totalRecords : Math.min(capacity, totalRecords);
      }

      return capacity;
    };

    const updateCapacity = () => {
      if (navigationPending) return;
      forceCapacityCheck = false;

      const items = records();
      if (!items.length) {
        reveal();
        return;
      }

      const capacity = calculateCapacity(items);
      if (!capacity) {
        reveal();
        return;
      }

      const currentCapacity = Number(root.dataset.pageSize) || 0;
      if (capacity === currentCapacity) {
        const params = new URLSearchParams(window.location.search);
        if (params.has(anchorParam) && !params.has(focusParam)) {
          params.delete(anchorParam);
          const query = params.toString();
          window.history.replaceState(null, "", `${window.location.pathname}${query ? `?${query}` : ""}${window.location.hash}`);
        }
        reveal();
        return;
      }

      const params = new URLSearchParams(window.location.search);
      params.set(pageParam, String(capacity));

      if (!params.has(focusParam)) {
        const anchor = items[0]?.dataset.recordAnchor;
        if (anchor) params.set(anchorParam, anchor);
      }

      navigationPending = true;
      markPending();
      window.location.search = params.toString();
    };

    const scheduleUpdate = (force = false) => {
      forceCapacityCheck = forceCapacityCheck || force;
      window.clearTimeout(timer);
      timer = window.setTimeout(updateCapacity, 220);
    };

    let lastViewportSize = {
      width: window.innerWidth,
      height: window.innerHeight,
      visualHeight: window.visualViewport?.height || 0,
    };

    const scheduleViewportUpdate = (force = false) => {
      const nextViewportSize = {
        width: window.innerWidth,
        height: window.innerHeight,
        visualHeight: window.visualViewport?.height || 0,
      };
      const changed = Object.keys(nextViewportSize).some((key) =>
        Math.abs(nextViewportSize[key] - lastViewportSize[key]) >= 2
      );
      if (!force && !changed) return;
      lastViewportSize = nextViewportSize;
      scheduleUpdate(true);
    };

    window.addEventListener("resize", () => scheduleViewportUpdate(), { passive: true });
    window.addEventListener("orientationchange", () => scheduleViewportUpdate(), { passive: true });
    window.visualViewport?.addEventListener("resize", () => scheduleViewportUpdate(), { passive: true });
    document.getElementById("sidebarToggle")?.addEventListener("click", () => scheduleViewportUpdate(true));

    if ("ResizeObserver" in window && root.dataset.adaptiveRecheck !== "viewport") {
      const observer = new ResizeObserver(() => scheduleUpdate(false));
      observer.observe(root);
      if (surface) observer.observe(surface);
      observer.observe(pageShell);
      if (records()[0]) observer.observe(records()[0]);
      if (pagination) observer.observe(pagination);
    }

    updateCapacity();
  });
})();
