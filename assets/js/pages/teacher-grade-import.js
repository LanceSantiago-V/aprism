(function () {
  "use strict";

  const form = document.getElementById("gradeImportForm");
  const review = document.getElementById("gradeImportReview");
  const fileInput = document.getElementById("gradeImportFile");
  const fileName = document.getElementById("gradeImportFileName");
  const classSelect = form?.elements.operational_class_id;
  const context = document.getElementById("gradeImportContext");
  const contextTitle = document.getElementById("gradeImportContextTitle");
  const contextMeta = document.getElementById("gradeImportContextMeta");
  const periodList = document.getElementById("gradeImportPeriods");
  const config = window.APRISM_GRADE_IMPORT || {};
  const periodLabels = {
    PRELIMS: "Prelim",
    MIDTERM: "Midterm",
    "PRE-FINALS": "Pre-Final",
    FINALS: "Final",
  };
  let activeGradeImportToast = null;
  let activeGradeImportToastTimer = null;

  function escapeHtml(value) {
    return String(value ?? "").replace(
      /[&<>"']/g,
      (char) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#039;",
        })[char],
    );
  }

  function showToast(title, message, type = "warning") {
    const container = document.getElementById("toastContainer");
    if (!container || !message) return;
    if (activeGradeImportToastTimer) clearTimeout(activeGradeImportToastTimer);
    activeGradeImportToast?.remove();
    const toast = document.createElement("div");
    toast.className = "toast-custom";
    const icon = type === "success" ? "circle-check" : "triangle-alert";
    toast.innerHTML = `<div class="toast-icon ${escapeHtml(type)}"><i data-lucide="${icon}"></i></div><div class="toast-content"><h5 class="toast-title"></h5><p class="toast-text"></p></div>`;
    toast.querySelector(".toast-title").textContent = title;
    toast.querySelector(".toast-text").textContent = message;
    container.appendChild(toast);
    if (window.lucide?.createIcons) window.lucide.createIcons({ root: toast });
    activeGradeImportToast = toast;
    setTimeout(() => toast.classList.add("show"), 10);
    activeGradeImportToastTimer = setTimeout(() => {
      toast.classList.remove("show");
      setTimeout(() => {
        toast.remove();
        if (activeGradeImportToast === toast) activeGradeImportToast = null;
      }, 240);
      activeGradeImportToastTimer = null;
    }, 4800);
  }

  function normalizedPeriodName(period) {
    const rawName = String(period.period_name || period.source_label || "");
    return periodLabels[rawName.toUpperCase()] || rawName || "Grading period";
  }

  function renderClassContext() {
    const selected = classSelect?.selectedOptions?.[0];
    if (!selected?.value) {
      if (context) context.hidden = true;
      return;
    }
    const keyPrefix = `${selected.dataset.schoolYearId}|${selected.dataset.semester}|`;
    const periods = Object.entries(config.periods || {})
      .filter(([key]) => key.startsWith(keyPrefix))
      .flatMap(([, values]) => values);
    if (context) context.hidden = false;
    if (contextTitle)
      contextTitle.textContent = `${selected.dataset.subject} · ${selected.dataset.section}`;
    if (contextMeta)
      contextMeta.textContent = `SY ${selected.dataset.schoolYear} · ${selected.dataset.semester}`;
    if (periodList)
      periodList.innerHTML = periods.length
        ? `<span class="grade-import-period-label">Configured grading periods</span>${periods.map((period) => `<span>${escapeHtml(normalizedPeriodName(period))}</span>`).join("")}`
        : '<span class="grade-import-period-missing">No Academic Periods configured for this term.</span>';
  }

  function rowNote(row, periodConfigured) {
    if (!periodConfigured) return "Academic Period not configured";
    const blockingIssues = (row.issues || []).filter(
      (issue) => !String(issue).startsWith("Formula output was ignored"),
    );
    if (blockingIssues.length) return blockingIssues.join("<br>");
    if (row.match_status && row.match_status !== "MATCHED")
      return row.match_status;
    return (row.scores || []).length ? "Ready" : "No raw scores found";
  }

  function formatScore(value) {
    const number = Number(value);
    return Number.isInteger(number)
      ? String(number)
      : number.toFixed(2).replace(/0+$/, "").replace(/\.$/, "");
  }

  function reviewStatusClass(row, periodConfigured) {
    if (
      !periodConfigured ||
      row.match_status !== "MATCHED" ||
      (row.issues || []).some(
        (issue) => !String(issue).startsWith("Formula output was ignored"),
      )
    )
      return "warning";
    return (row.scores || []).length ? "success" : "neutral";
  }

  function renderScoreMatrix(period) {
    const components = Array.isArray(period.components)
      ? period.components
      : [];
    const rows = Array.isArray(period.rows) ? period.rows : [];
    if (!components.length)
      return '<div class="grade-import-score-matrix-empty">No raw-score components were detected for this period.</div>';
    const header = components
      .map(
        (component) =>
          `<th scope="col"><span>${escapeHtml(component.code)}</span>${component.max_score !== null && component.max_score !== undefined ? `<small>Max ${escapeHtml(formatScore(component.max_score))}</small>` : ""}</th>`,
      )
      .join("");
    const body = rows.length
      ? rows
          .map((row) => {
            const scores = new Map(
              (row.scores || []).map((score) => [String(score.code), score]),
            );
            return `<tr><td class="grade-import-matrix-number">${escapeHtml(row.student_number || "—")}</td><th scope="row" class="grade-import-matrix-student"><strong>${escapeHtml(row.matched_student_name || row.student_name || "Unnamed student")}</strong><small>Workbook row ${escapeHtml(row.row)}</small></th>${components
              .map((component) => {
                const score = scores.get(String(component.code));
                return `<td class="grade-import-score-cell ${score?.existing_score_state === "EXISTING SCORE CONFLICT" ? "is-conflict" : ""}">${score ? `<span>${escapeHtml(formatScore(score.score))}</span>${score.existing_score_state === "EXISTING SCORE CONFLICT" ? "<small>Change</small>" : ""}` : '<span class="grade-import-score-missing">—</span>'}</td>`;
              })
              .join(
                "",
              )}<td class="grade-import-matrix-review"><span class="grade-import-review-status ${reviewStatusClass(row, period.period_configured)}">${escapeHtml(row.match_status || "REVIEW")}</span><small>${escapeHtml(rowNote(row, period.period_configured).replace(/<br>/g, " · "))}</small></td></tr>`;
          })
          .join("")
      : `<tr><td colspan="${components.length + 3}" class="grade-import-score-matrix-empty">No student score rows were found.</td></tr>`;
    return `<div class="grade-import-score-matrix" role="region" aria-label="Read-only raw score preview"><div class="grade-import-score-matrix-caption"><div><strong>Raw score preview</strong><span>Read-only values extracted from the selected period</span></div><span>${components.length} component${components.length === 1 ? "" : "s"}</span></div><div class="grade-import-score-matrix-scroll"><table><thead><tr><th scope="col">Student No.</th><th scope="col">Student</th>${header}<th scope="col">Review</th></tr></thead><tbody>${body}</tbody></table></div></div>`;
  }

  function activatePeriodTab(tabButtons, panels, index) {
    tabButtons.forEach((tab, tabIndex) => {
      const active = tabIndex === index;
      tab.classList.toggle("active", active);
      tab.setAttribute("aria-selected", active ? "true" : "false");
      tab.tabIndex = active ? 0 : -1;
    });
    panels.forEach((panel, panelIndex) => {
      panel.hidden = panelIndex !== index;
    });
  }

  function bindPeriodTabs() {
    const tabButtons = Array.from(
      review.querySelectorAll("[data-grade-period-tab]"),
    );
    const panels = Array.from(
      review.querySelectorAll("[data-grade-period-panel]"),
    );
    if (!tabButtons.length || !panels.length) return;
    tabButtons.forEach((tab) => {
      if (!tab.classList.contains("blocked")) return;
      const label = tab.textContent
        .replace(/\s*·\s*Not configured/i, "")
        .trim();
      tab.textContent = label;
      tab.title = "Not configured";
      const marker = document.createElement("span");
      marker.className = "grade-import-tab-warning";
      marker.setAttribute("aria-label", "Not configured");
      marker.textContent = "!";
      tab.append(" ", marker);
    });
    tabButtons.forEach((tab, index) => {
      tab.addEventListener("click", () =>
        activatePeriodTab(tabButtons, panels, index),
      );
      tab.addEventListener("keydown", (event) => {
        if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key))
          return;
        event.preventDefault();
        const nextIndex =
          event.key === "Home"
            ? 0
            : event.key === "End"
              ? tabButtons.length - 1
              : (index +
                  (event.key === "ArrowRight" ? 1 : -1) +
                  tabButtons.length) %
                tabButtons.length;
        tabButtons[nextIndex].focus();
        activatePeriodTab(tabButtons, panels, nextIndex);
      });
    });
    activatePeriodTab(tabButtons, panels, 0);
  }

  function renderReview(data) {
    const periods = Array.isArray(data.periods) ? data.periods : [];
    const statuses = [
      "MISSING STUDENT NUMBER",
      "DUPLICATE STUDENT NUMBER IN WORKBOOK",
      "STUDENT NOT FOUND",
      "STUDENT NOT ACTIVE",
      "NOT ENROLLED IN SELECTED CLASS",
      "STUDENT NAME DISCREPANCY",
      "IDENTITY RECONCILIATION AMBIGUOUS",
      "CANONICAL STUDENT EXISTS - RECONCILIATION REQUIRED",
    ];
    const periodIssues = Array.isArray(data.period_resolution_issues)
      ? data.period_resolution_issues
      : [];
    periods.forEach((period) => {
      period.period_name = normalizedPeriodName(period);
      period.period_configured =
        period.academic_period_id !== null &&
        period.academic_period_id !== undefined;
    });
    const allRows = periods.flatMap((period) => period.rows || []);
    const reconciliationRows = allRows.filter(
      (row) => row.match_status === "IDENTITY RECONCILIATION REQUIRED",
    );
    const uniqueStudents = new Set(
      allRows
        .map((row) => String(row.student_number || "").trim())
        .filter(Boolean),
    ).size;
    const blockingRows = allRows.filter(
      (row) =>
        statuses.includes(row.match_status) ||
        (row.issues || []).some(
          (issue) => !String(issue).startsWith("Formula output was ignored"),
        ),
    );
    const blocking = periodIssues.length + blockingRows.length;
    const alreadyConfirmed = data.previously_confirmed_batch_id !== null && data.previously_confirmed_batch_id !== undefined;
    const conflicts = allRows.some((row) =>
      (row.scores || []).some(
        (score) => score.existing_score_state === "EXISTING SCORE CONFLICT",
      ),
    );
    const missingNames = periodIssues
      .map((issue) =>
        String(issue).replace(
          / is not configured for this class term\.?$/i,
          "",
        ),
      )
      .filter(Boolean);
    const blockerMarkup = missingNames.length
      ? `<div class="grade-import-academic-blocker" role="status"><span class="grade-import-academic-blocker-icon"><i data-lucide="triangle-alert"></i></span><div><strong>Academic Setup incomplete</strong><p>This teaching term is missing: <b>${escapeHtml(missingNames.join(" · "))}</b></p><small>Complete Academic Setup before confirming this full-term Grade Import.</small></div></div>`
      : "";
    const reconciliationMarkup = reconciliationRows.length
      ? `<div class="grade-import-reconciliation-note" role="status"><span class="grade-import-academic-blocker-icon"><i data-lucide="user-round-check"></i></span><div><strong>Review same-class identity reconciliation</strong><p>${reconciliationRows.length} workbook row(s) match an active unresolved participant in this Operational Class. The authoritative Student Number will be assigned only after explicit confirmation.</p></div></div>`
      : "";
    if (missingNames.length)
      showToast(
        "Academic Setup incomplete",
        `Grade Import cannot continue because ${missingNames.join(" and ")} ${missingNames.length === 1 ? "is" : "are"} not configured for this teaching term.`,
      );
    review.hidden = false;
    review.innerHTML = `<div class="grade-import-review-heading"><div><p class="teacher-eyebrow">Detected ${escapeHtml(data.template_type)} term workbook</p><h2>Review the complete grading cycle</h2><p class="grade-import-helper">${escapeHtml(data.source_filename)} · ${uniqueStudents} unique student${uniqueStudents === 1 ? "" : "s"}</p></div><span class="grade-import-status ${blocking ? "warning" : "success"}">${blocking ? `${blocking} issue(s) to review` : alreadyConfirmed ? "Already confirmed" : "Structure ready"}</span></div><div class="grade-import-notice">${escapeHtml(data.persistence || "Review only until confirmed.")}</div>${blockerMarkup}${reconciliationMarkup}<div class="grade-import-term-tabs" role="tablist" aria-label="Detected grading periods">${periods.map((period, index) => `<button type="button" class="grade-import-term-tab ${index === 0 ? "active" : ""} ${period.period_configured ? "" : "blocked"}" id="grade-period-tab-${index}" data-grade-period-tab aria-controls="grade-period-panel-${index}" aria-selected="${index === 0 ? "true" : "false"}" role="tab" tabindex="${index === 0 ? "0" : "-1"}">${escapeHtml(period.period_name)}${period.period_configured ? "" : " · Not configured"}</button>`).join("")}</div>${periods.map((period, index) => `<section class="grade-import-period-review ${period.period_configured ? "" : "is-blocked"}" id="grade-period-panel-${index}" data-grade-period-panel role="tabpanel" aria-labelledby="grade-period-tab-${index}" tabindex="0" ${index === 0 ? "" : "hidden"}><div class="grade-import-subheading"><span class="teacher-eyebrow">${escapeHtml(period.period_name)}</span><span class="grade-import-helper">${period.period_configured ? `${(period.components || []).length} component(s)` : "Workbook structure detected · Academic Period not configured"}</span></div><div class="grade-import-category-list">${(period.categories || []).map((category) => `<div class="grade-import-category"><div><strong>${escapeHtml(category.category_name)}</strong><span>Not assigned by this import</span></div><p>${(category.components || []).map((component) => `${escapeHtml(component.code)} · max ${escapeHtml(component.max_score ?? "not detected")}`).join(" &nbsp; ")}</p></div>`).join("") || '<p class="grade-import-helper">No assessment components were detected.</p>'}</div>${renderScoreMatrix(period)}</section>`).join("")}<div class="grade-import-confirm"><p class="grade-import-helper">${blocking ? "Resolve all blocking findings before confirmation." : alreadyConfirmed ? "This exact workbook is already recorded. No new Student, SCE, or score rows will be created." : "All periods have passed the initial review gate."}</p>${reconciliationRows.length && !blocking ? '<label class="grade-import-confirm-check"><input type="checkbox" id="confirmIdentityReconciliations"> I confirm the reviewed same-class unresolved participants may receive the authoritative Student Number.</label>' : ""}${conflicts && !blocking ? '<label class="grade-import-confirm-check"><input type="checkbox" id="allowGradeConflicts"> I confirm the reviewed score changes should replace current values.</label>' : ""}<button type="button" class="grade-import-submit" id="confirmGradeImport" ${blocking || conflicts || reconciliationRows.length ? "disabled" : ""}>${alreadyConfirmed ? "Finish (already confirmed)" : "Confirm Grade Import"}</button></div>`;
    if (window.lucide?.createIcons) window.lucide.createIcons();
    bindPeriodTabs();
    review.scrollIntoView({ behavior: "smooth", block: "start" });
    const confirm = document.getElementById("confirmGradeImport");
    const conflictCheck = document.getElementById("allowGradeConflicts");
    const identityCheck = document.getElementById("confirmIdentityReconciliations");
    const updateConfirmState = () => {
      if (!confirm) return;
      confirm.disabled = Boolean(
        blocking ||
          (conflicts && !conflictCheck?.checked) ||
          (reconciliationRows.length && !identityCheck?.checked),
      );
    };
    conflictCheck?.addEventListener("change", updateConfirmState);
    identityCheck?.addEventListener("change", updateConfirmState);
    confirm?.addEventListener("click", async () => {
      confirm.disabled = true;
      confirm.textContent = "Saving…";
      try {
        const response = await fetch(config.confirmEndpoint, {
          method: "POST",
          body: new URLSearchParams({
            csrf_token: form.elements.csrf_token.value,
            review_token: data.review_token,
            allow_conflicts: conflictCheck?.checked ? "1" : "0",
            confirm_identity_reconciliations: identityCheck?.checked ? "1" : "0",
          }),
          credentials: "same-origin",
          headers: { Accept: "application/json" },
        });
        const payload = await response.json();
        if (!response.ok || !payload.success)
          throw new Error(
            payload.message || "The import could not be confirmed.",
          );
        showToast("Grade Import confirmed", payload.message, "success");
        const firstConfiguredPeriod = periods.find((period) =>
          period.period_configured && Number(period.academic_period_id) > 0,
        );
        const analyticsQuery = new URLSearchParams({
          class_id: classSelect.value,
        });
        if (firstConfiguredPeriod) {
          analyticsQuery.set(
            "period_id",
            String(firstConfiguredPeriod.academic_period_id),
          );
        }
        const analyticsHref = `${config.analyticsUrl || ""}?${analyticsQuery.toString()}`;
        review.innerHTML = `<div class="grade-import-complete"><span class="grade-import-complete-icon"><i data-lucide="circle-check"></i></span><div><p class="teacher-eyebrow">Import confirmed</p><h2>Raw assessment data is ready to review</h2><p>${escapeHtml(payload.message)}</p></div><a class="grade-import-submit" href="${escapeHtml(analyticsHref)}">View Grade Analytics</a></div>`;
        if (window.lucide?.createIcons) window.lucide.createIcons({ root: review });
      } catch (error) {
        confirm.disabled = false;
        confirm.textContent = alreadyConfirmed ? "Finish (already confirmed)" : "Confirm Grade Import";
        showToast("Grade Import could not be confirmed", error.message);
      }
    });
  }

  form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!classSelect?.value) {
      showToast(
        "Grade Import needs a class",
        "Select an Operational Class before reviewing a workbook.",
      );
      return;
    }
    if (!fileInput?.files?.length) {
      showToast(
        "Grade Import needs a workbook",
        "Choose an Excel or CSV grade file before continuing.",
      );
      return;
    }
    const button = document.getElementById("previewGradeImport");
    button.disabled = true;
    button.textContent = "Extracting…";
    try {
      const response = await fetch(config.endpoint, {
        method: "POST",
        body: new FormData(form),
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });
      const payload = await response.json();
      if (!response.ok || !payload.success)
        throw new Error(payload.message || "The source could not be reviewed.");
      renderReview(payload.data || {});
    } catch (error) {
      review.hidden = false;
      review.innerHTML = `<div class="grade-import-notice error">${escapeHtml(error.message)}</div>`;
    } finally {
      button.disabled = false;
      button.textContent = "Extract for Review";
    }
  });
  fileInput?.addEventListener("change", () => {
    const selected = fileInput.files?.[0];
    fileName.textContent = selected
      ? selected.name
      : "Choose an Excel or CSV workbook";
  });
  classSelect?.addEventListener("change", renderClassContext);
  renderClassContext();
  document
    .querySelector(".grade-import-upload")
    ?.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        fileInput?.click();
      }
    });
})();
