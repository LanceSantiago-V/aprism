(() => {
  "use strict";

  const page = document.querySelector(".teacher-analytics-page");
  if (!page) return;

  const frame = page.querySelector(".analytics-chart-frame");
  const lineChart = frame && frame.querySelector(".analytics-line-chart");
  const tooltip = page.querySelector("[data-chart-tooltip]");
  const summary = page.querySelector("[data-chart-selection-summary]");
  const reset = page.querySelector("[data-chart-reset]");
  const crosshair = page.querySelector("[data-chart-crosshair]");
  const points = Array.from(page.querySelectorAll("[data-meeting-point]"));
  const rows = Array.from(page.querySelectorAll("[data-meeting-row]"));
  const selectionPrompt = summary ? summary.textContent : "Select a meeting to see its attendance counts.";

  const byId = (items, id) => items.find((item) => item.dataset.meetingId === String(id));
  const valuesFor = (item) => ({
    label: item.dataset.meetingLabel || "Saved meeting",
    present: Number(item.dataset.present || 0),
    late: Number(item.dataset.late || 0),
    absent: Number(item.dataset.absent || 0),
    recorded: Number(item.dataset.recorded || 0)
  });
  const exactSummary = (item) => {
    const value = valuesFor(item);
    if (value.recorded === 0) {
      return value.label + ". No attendance outcomes are recorded for this saved meeting; category counts are unavailable.";
    }
    return value.label + ". Present: " + value.present + ". Late: " + value.late + ". Absent: " + value.absent + ". Total recorded: " + value.recorded + ".";
  };
  const tooltipText = (item) => {
    const value = valuesFor(item);
    return value.label + "\nPresent  " + value.present + "   ·   Late  " + value.late + "   ·   Absent  " + value.absent;
  };

  function positionTooltip(point) {
    if (!frame || !tooltip || !point) return;
    tooltip.hidden = false;
    const frameRect = frame.getBoundingClientRect();
    const pointRect = point.getBoundingClientRect();
    const markerRects = Array.from(point.querySelectorAll(".analytics-chart-meeting-marker"), (marker) => marker.getBoundingClientRect());
    const markerTop = markerRects.length
      ? Math.min(...markerRects.map((markerRect) => markerRect.top))
      : pointRect.top + pointRect.height / 2;
    const markerBottom = markerRects.length
      ? Math.max(...markerRects.map((markerRect) => markerRect.bottom))
      : pointRect.bottom;
    const idealLeft = pointRect.left + pointRect.width / 2 - frameRect.left - tooltip.offsetWidth / 2;
    const maxLeft = Math.max(8, frameRect.width - tooltip.offsetWidth - 8);
    tooltip.style.left = Math.max(8, Math.min(idealLeft, maxLeft)) + "px";
    const gap = 10;
    const abovePoint = markerTop - frameRect.top - tooltip.offsetHeight - gap;
    const belowPoint = markerBottom - frameRect.top + gap;
    const maxTop = Math.max(8, frameRect.height - tooltip.offsetHeight - 8);
    const belowFits = belowPoint + tooltip.offsetHeight <= frameRect.height - 8;
    tooltip.style.top = Math.max(8, Math.min(abovePoint >= 8 ? abovePoint : belowFits ? belowPoint : abovePoint, maxTop)) + "px";
  }

  function showTooltip(point) {
    if (!tooltip || !point) return;
    tooltip.textContent = tooltipText(point);
    positionTooltip(point);
  }

  function hideTooltip() {
    if (tooltip) tooltip.hidden = true;
  }

  function updateLineChartSizing() {
    if (!lineChart) return;
    const viewBoxWidth = lineChart.viewBox.baseVal.width;
    const renderedWidth = lineChart.getBoundingClientRect().width;
    if (!viewBoxWidth || !renderedWidth) return;

    const scale = renderedWidth / viewBoxWidth;
    const textScale = 1 / scale;
    lineChart.style.setProperty("--analytics-chart-text-scale", textScale.toFixed(3));
    lineChart.style.setProperty("--analytics-chart-marker-scale", Math.max(1, textScale).toFixed(3));

    const isPhoneLayout = window.matchMedia("(max-width: 620px)").matches;
    lineChart.querySelectorAll(".analytics-chart-hit-area").forEach((area) => {
      const baseX = Number(area.dataset.baseX);
      const baseWidth = Number(area.dataset.baseWidth);
      if (!Number.isFinite(baseX) || !Number.isFinite(baseWidth)) return;
      if (isPhoneLayout) {
        const center = baseX + baseWidth / 2;
        const touchWidth = Math.max(baseWidth, 44 / scale);
        area.setAttribute("x", (center - touchWidth / 2).toFixed(2));
        area.setAttribute("width", touchWidth.toFixed(2));
      } else {
        area.setAttribute("x", area.dataset.baseX);
        area.setAttribute("width", area.dataset.baseWidth);
      }
    });
  }

  if (lineChart) {
    updateLineChartSizing();
    if (typeof ResizeObserver === "function") {
      new ResizeObserver(updateLineChartSizing).observe(lineChart);
    }
    window.addEventListener("resize", updateLineChartSizing, { passive: true });
  }

  function selectMeeting(id) {
    const point = byId(points, id);
    const row = byId(rows, id);
    const source = point || (row && row.querySelector("[data-meeting-select]"));
    if (!source) return;
    const selectedId = String(id);
    points.forEach((item) => {
      const selected = item.dataset.meetingId === selectedId;
      item.dataset.selected = String(selected);
      item.setAttribute("aria-pressed", String(selected));
    });
    rows.forEach((item) => {
      const selected = item.dataset.meetingId === selectedId;
      item.classList.toggle("is-selected", selected);
      item.setAttribute("aria-selected", String(selected));
      const button = item.querySelector("[data-meeting-select]");
      if (button) button.setAttribute("aria-pressed", String(selected));
    });
    if (summary) summary.textContent = exactSummary(source);
    if (reset) reset.disabled = false;
    if (crosshair && point) {
      const guide = point.querySelector(".analytics-chart-meeting-guide");
      if (guide) {
        crosshair.setAttribute("x1", guide.getAttribute("x1"));
        crosshair.setAttribute("x2", guide.getAttribute("x2"));
        crosshair.style.opacity = "0.8";
      }
    }
  }

  function clearSelection() {
    points.forEach((item) => {
      item.dataset.selected = "false";
      item.setAttribute("aria-pressed", "false");
    });
    rows.forEach((item) => {
      item.classList.remove("is-selected");
      item.removeAttribute("aria-selected");
      const button = item.querySelector("[data-meeting-select]");
      if (button) button.setAttribute("aria-pressed", "false");
    });
    if (summary) summary.textContent = selectionPrompt;
    if (reset) reset.disabled = true;
    if (crosshair) crosshair.style.opacity = "0";
    hideTooltip();
  }

  page.addEventListener("pointerover", (event) => {
    const point = event.target.closest && event.target.closest("[data-meeting-point]");
    if (point && !point.contains(event.relatedTarget)) showTooltip(point);
  });
  page.addEventListener("pointerout", (event) => {
    const point = event.target.closest && event.target.closest("[data-meeting-point]");
    if (point && !point.contains(event.relatedTarget)) hideTooltip();
  });
  page.addEventListener("focusin", (event) => {
    const point = event.target.closest && event.target.closest("[data-meeting-point]");
    if (point) showTooltip(point);
  });
  page.addEventListener("focusout", (event) => {
    const point = event.target.closest && event.target.closest("[data-meeting-point]");
    if (point && !point.contains(event.relatedTarget)) hideTooltip();
  });
  page.addEventListener("click", (event) => {
    const point = event.target.closest && event.target.closest("[data-meeting-point]");
    const button = event.target.closest && event.target.closest("[data-meeting-select]");
    if (point) selectMeeting(point.dataset.meetingId);
    else if (button) selectMeeting(button.dataset.meetingId);
  });
  page.addEventListener("keydown", (event) => {
    const point = event.target.closest && event.target.closest("[data-meeting-point]");
    if (point && (event.key === "Enter" || event.key === " ")) {
      event.preventDefault();
      selectMeeting(point.dataset.meetingId);
    }
  });
  if (reset) reset.addEventListener("click", clearSelection);
  clearSelection();
})();
