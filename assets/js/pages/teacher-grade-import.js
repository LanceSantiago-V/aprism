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
  const levelResolution = document.getElementById("gradeImportLevelResolution");
  const levelResolutionHelp = document.getElementById("gradeImportLevelHelp");
  const levelSelect = document.getElementById("gradeImportAcademicLevel");
  const levelConfirm = document.getElementById("confirmClassAcademicLevel");
  const previewButton = document.getElementById("previewGradeImport");
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
      if (levelResolution) levelResolution.hidden = true;
      if (previewButton) previewButton.disabled = true;
      return;
    }
    const academicLevel = selected.dataset.academicLevel || "";
    const periods = academicLevel
      ? (config.periods || {})[
          `${selected.dataset.schoolYearId}|${selected.dataset.semester}|${academicLevel}`
        ] || []
      : [];
    if (levelResolution) levelResolution.hidden = Boolean(academicLevel);
    if (levelSelect) {
      const suggestion = selected.dataset.levelSuggestion || "";
      levelSelect.value = academicLevel || suggestion;
      if (levelResolutionHelp) {
        levelResolutionHelp.textContent = suggestion
          ? `This class has no confirmed Academic Level. Its linked institutional Program identifies ${suggestion === "Senior High School" ? "Senior High School" : suggestion}; confirm this class context to continue.`
          : "This class has no confirmed Academic Level or linked Program level. Select College or Senior High School to establish the class context before Grade Import.";
      }
    }
    if (previewButton) previewButton.disabled = !academicLevel;
    if (context) context.hidden = false;
    if (contextTitle) contextTitle.textContent = "";
    if (contextMeta)
      contextMeta.textContent = `${academicLevel || "Academic level needs confirmation"} · AY ${selected.dataset.schoolYear} · ${selected.dataset.semester}${periods.length ? ` · ${periods.map(normalizedPeriodName).join(" · ")}` : ""}`;
    if (periodList)
      periodList.textContent = periods.length
        ? ""
        : academicLevel
          ? "Grading periods are not configured for this class term."
          : "Class academic level needs confirmation before review.";
  }

  levelConfirm?.addEventListener("click", async () => {
    if (!classSelect?.value || !levelSelect?.value) {
      showToast("Academic Level required", "Choose College or Senior High School for this class.");
      return;
    }
    levelConfirm.disabled = true;
    levelConfirm.textContent = "Saving…";
    try {
      const response = await fetch(config.resolveAcademicLevelEndpoint, {
        method: "POST",
        body: new URLSearchParams({
          csrf_token: form.elements.csrf_token.value,
          operational_class_id: classSelect.value,
          academic_level: levelSelect.value,
        }),
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || "The class Academic Level could not be confirmed.");
      }
      const selected = classSelect.selectedOptions[0];
      selected.dataset.academicLevel = payload.data.academic_level;
      selected.dataset.levelSuggestion = "";
      renderClassContext();
      showToast("Academic Level confirmed", payload.message, "success");
    } catch (error) {
      showToast("Academic Level was not confirmed", error.message);
    } finally {
      levelConfirm.disabled = false;
      levelConfirm.textContent = "Confirm Academic Level";
    }
  });

  function rowNote(row, periodConfigured) {
    if (!periodConfigured) return "Academic Period not configured";
    const blockingIssues = (row.issues || []).filter(
      (issue) => !String(issue).startsWith("Formula output was ignored"),
    );
    if (blockingIssues.length) return blockingIssues.join("<br>");
    const formulaNotices = (row.issues || []).filter((issue) =>
      String(issue).startsWith("Formula output was ignored"),
    );
    if (formulaNotices.length) return formulaNotices.join("<br>");
    if (Array.isArray(row.identity_candidates) && row.identity_candidates.length) {
      return `${row.match_status}: ${row.identity_candidates.map((candidate) => candidate.name).join(", ")}`;
    }
    if (row.match_status && row.match_status !== "MATCHED")
      return row.match_status;
    return (row.scores || []).length ? "Ready" : "No raw scores found";
  }

  function explainIssue(issue) {
    const text = String(issue || "");
    if (/maximum score .* missing/i.test(text)) return [text, "Add the maximum score to the workbook, then review it again."];
    if (/maximum score .* conflicts/i.test(text)) return [text, "Use the maximum already configured for this class or ask the Academic Head to review it."];
    if (/outside .* numeric range|precision/i.test(text)) return [text, "Use a supported non-negative score with at most two decimal places."];
    if (/required grading-period block is missing/i.test(text)) return [text, "Restore the missing period section from the official workbook template."];
    if (/no supported component columns/i.test(text)) return [text, "Use the official workbook template for this grading period."];
    if (/no Student score rows/i.test(text)) return [text, "Add Student Number, Student name, and score rows to the workbook."];
    if (/is not numeric/i.test(text)) return [text.replace("is not numeric", "is not a number"), "Enter a numeric raw score in that workbook cell."];
    if (/outside the supported range|exceeds the configured maximum/i.test(text)) return [text, "Check the score against its maximum and correct the workbook cell."];
    if (/must be a non-negative value/i.test(text)) return [text, "Enter a non-negative score with no more than two decimal places."];
    if (/Student Number and name are both required/i.test(text)) return ["Student Number or name is missing.", "Complete both identity fields in the workbook row."];
    return [text, "Correct this item in the workbook or class setup, then review the workbook again."];
  }

  function humanMatchIssue(status) {
    const messages = {
      "MISSING STUDENT NUMBER": ["Student Number is missing.", "Add the Student Number from the official class roster."],
      "DUPLICATE STUDENT NUMBER IN WORKBOOK": ["This Student Number appears more than once.", "Keep one row per Student for this workbook."],
      "STUDENT NOT FOUND": ["Student Number was not found.", "Check the number against the official Student record and selected class roster."],
      "STUDENT NOT ACTIVE": ["This Student is inactive.", "Confirm the correct Student and class roster before continuing."],
      "NOT ENROLLED IN SELECTED CLASS": ["Not enrolled in selected class.", "Choose the correct class or workbook, or update the class roster before importing."],
      "STUDENT NAME DISCREPANCY": ["The name does not match this Student Number.", "Check the Student Number and name against the official roster."],
      "IDENTITY RECONCILIATION AMBIGUOUS": ["This Student could not be matched safely.", "Review the Student Number and class roster; APRISM will not match by name alone."],
      "CANONICAL STUDENT EXISTS - RECONCILIATION REQUIRED": ["This Student Number needs review.", "Ask an authorized user to resolve the existing Student record before importing."],
    };
    return messages[status] || null;
  }

  function renderScoreChanges(periods) {
    const changes = [];
    periods.forEach((period) => (period.rows || []).forEach((row) => (row.scores || []).forEach((score) => {
      if (score.existing_score_state === "EXISTING SCORE CONFLICT") {
        changes.push(`<tr><th scope="row">${escapeHtml(row.matched_student_name || row.student_name || "Student")}<small>${escapeHtml(row.student_number || "No Student Number")}</small></th><td>${escapeHtml(period.period_name)} · ${escapeHtml(score.code)}</td><td>${escapeHtml(formatScore(score.existing_raw_score))}</td><td>${escapeHtml(formatScore(score.score))}</td></tr>`);
      }
    })));
    if (!changes.length) return "";
    return `<details class="grade-import-disclosure grade-import-changes"><summary>View changes (${changes.length})</summary><div class="grade-import-review-table"><table><thead><tr><th>Student</th><th>Period / assessment</th><th>Current</th><th>Incoming</th></tr></thead><tbody>${changes.join("")}</tbody></table></div></details>`;
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
    const workbookIssues = Array.isArray(data.workbook_issues)
      ? data.workbook_issues
      : [];
    const structureIssues = periods.flatMap((period) =>
      Array.isArray(period.structure_issues) ? period.structure_issues : [],
    );
    const periodBlockers = periods.flatMap((period) =>
      Array.isArray(period.blocking_issues) ? period.blocking_issues : [],
    );
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
    const blockingRows = allRows.filter(
      (row) =>
        statuses.includes(row.match_status) ||
        (row.issues || []).some(
          (issue) => !String(issue).startsWith("Formula output was ignored"),
        ),
    );
    const blocking =
      periodIssues.length +
      workbookIssues.length +
      periodBlockers.length +
      blockingRows.length;
    const alreadyConfirmed =
      data.previously_confirmed_batch_id !== null &&
      data.previously_confirmed_batch_id !== undefined;
    const conflicts = allRows.some((row) =>
      (row.scores || []).some(
        (score) => score.existing_score_state === "EXISTING SCORE CONFLICT",
      ),
    );
    const contextData = data.class_context || {};
    const rawScoreCount = allRows.reduce(
      (count, row) => count + (row.scores || []).length,
      0,
    );
    const updateCount = allRows.reduce(
      (count, row) =>
        count +
        (row.scores || []).filter(
          (score) => score.existing_score_state === "EXISTING SCORE CONFLICT",
        ).length,
      0,
    );
    const unchangedCount = allRows.reduce(
      (count, row) =>
        count +
        (row.scores || []).filter(
          (score) => score.existing_score_state === "EXISTING SCORE UNCHANGED",
        ).length,
      0,
    );
    const ignoredFormulaCount = allRows.reduce(
      (count, row) =>
        count +
        (row.issues || []).filter((issue) =>
          String(issue).startsWith("Formula output was ignored"),
        ).length,
      0,
    );
    const matchedStudents = new Set(
      allRows
        .filter((row) => row.match_status === "MATCHED")
        .map((row) => String(row.student_number || "").trim())
        .filter(Boolean),
    ).size;
    const rowIdentityKey = (row, index) => {
      const studentNumber = String(row.student_number || "").trim();
      if (studentNumber) return `student-number:${studentNumber}`;
      const workbookRow = String(row.row || "").trim();
      if (workbookRow) {
        const name = String(row.student_name || "").trim().toLocaleLowerCase();
        return `workbook-row:${workbookRow}:${name}`;
      }
      return `unidentified-row:${index}`;
    };
    const detectedStudentKeys = new Set(
      allRows.map((row, index) => rowIdentityKey(row, index)),
    );
    const attentionStudentKeys = new Set(
      blockingRows.map((row, index) => rowIdentityKey(row, index)),
    );
    const allIssueItems = [];
    periodIssues.forEach((issue) =>
      allIssueItems.push({
        period: "Academic Setup",
        problem: String(issue),
        action:
          "Ask the Academic Head to complete Academic Setup before importing this full-term workbook.",
      }),
    );
    workbookIssues.forEach((issue) => {
      const [problem, action] = explainIssue(issue);
      allIssueItems.push({ period: "Workbook", problem, action });
    });
    periods.forEach((period) => {
      (period.blocking_issues || []).forEach((issue) => {
        const [problem, action] = explainIssue(issue);
        allIssueItems.push({ period: period.period_name, problem, action });
      });
      (period.rows || []).forEach((row) => {
        const identityIssue = humanMatchIssue(row.match_status);
        if (identityIssue) {
          allIssueItems.push({
            period: period.period_name,
            row: row.row,
            student: row.student_name,
            number: row.student_number,
            problem: identityIssue[0],
            action: identityIssue[1],
          });
        }
        (row.issues || [])
          .filter(
            (issue) => !String(issue).startsWith("Formula output was ignored"),
          )
          .forEach((issue) => {
            const [problem, action] = explainIssue(issue);
            allIssueItems.push({
              period: period.period_name,
              row: row.row,
              student: row.student_name,
              number: row.student_number,
              problem,
              action,
            });
          });
      });
    });
    const issueGroups = new Map();
    const generalIssueGroups = new Map();
    allIssueItems.forEach((item, index) => {
      if (item.student || item.number) {
        const identity = item.number
          ? `student-number:${String(item.number).trim()}`
          : item.row
            ? `workbook-row:${item.row}:${String(item.student || "").trim().toLocaleLowerCase()}`
            : `unidentified-issue:${index}`;
        const key = JSON.stringify([identity, item.problem, item.action]);
        const group = issueGroups.get(key) || { ...item, periods: [], occurrences: [] };
        if (item.period && !group.periods.includes(item.period)) group.periods.push(item.period);
        group.occurrences.push(item);
        issueGroups.set(key, group);
      } else {
        const key = JSON.stringify([item.problem, item.action]);
        const group = generalIssueGroups.get(key) || { ...item, periods: [], occurrences: [] };
        if (item.period && !group.periods.includes(item.period)) group.periods.push(item.period);
        group.occurrences.push(item);
        generalIssueGroups.set(key, group);
      }
    });
    const groupedIssueItems = [...issueGroups.values()];
    const generalIssues = [...generalIssueGroups.values()];
    const notEnrolledCount = new Set(
      allRows
        .map((row, index) => ({ row, key: rowIdentityKey(row, index) }))
        .filter(({ row }) => row.match_status === "NOT ENROLLED IN SELECTED CLASS")
        .map(({ key }) => key),
    ).size;
    const sharedGuidance = [...new Set(allIssueItems.map((item) => item.action).filter(Boolean))];
    const issueMarkup = allIssueItems.length
      ? `<section class="grade-import-issues" role="alert" aria-labelledby="gradeImportIssueTitle">
          <h3 id="gradeImportIssueTitle">${blocking ? "Import blocked" : "Some entries need attention"}</h3>
          <p class="grade-import-issues-intro">${notEnrolledCount
            ? `${notEnrolledCount} ${notEnrolledCount === 1 ? "student is" : "students are"} not enrolled in the selected class.`
            : `${Math.max(groupedIssueItems.length, generalIssues.length)} workbook ${groupedIssueItems.length + generalIssues.length === 1 ? "entry needs" : "entries need"} attention.`}</p>
          ${sharedGuidance.length === 1 ? `<p class="grade-import-issues-guidance">${escapeHtml(sharedGuidance[0])}</p>` : ""}
          ${groupedIssueItems.length ? `<ul class="grade-import-issue-groups">${groupedIssueItems.map((item) => `<li>
            <strong>${escapeHtml(item.student || (item.number ? `Student No. ${item.number}` : "Workbook entry"))}</strong>
            ${item.number && item.student ? `<span>Student No. ${escapeHtml(item.number)}</span>` : ""}
            <p>${escapeHtml(item.problem)}</p>
            ${item.periods.length ? `<small>Appears in: ${escapeHtml(item.periods.join(" · "))}</small>` : ""}
            ${sharedGuidance.length > 1 ? `<small>${escapeHtml(item.action)}</small>` : ""}
          </li>`).join("")}</ul>` : ""}
          ${generalIssues.length ? `<ul class="grade-import-issue-groups">${generalIssues.map((item) => `<li>
            <strong>${escapeHtml(item.periods.join(" · ") || item.period || "Workbook")}</strong>
            <p>${escapeHtml(item.problem)}</p>
            ${sharedGuidance.length > 1 ? `<small>${escapeHtml(item.action)}</small>` : ""}
          </li>`).join("")}</ul>` : ""}
          <details class="grade-import-issue-details">
            <summary>View issue details (${allIssueItems.length})</summary>
            <ul>${allIssueItems.map((item) => `<li>
              <strong>${escapeHtml(item.student || (item.number ? `Student No. ${item.number}` : item.period))}</strong>
              ${item.number && item.student ? `<span>Student No. ${escapeHtml(item.number)}</span>` : ""}
              <span>${escapeHtml(item.row ? `Workbook row ${item.row} · ` : "")}${escapeHtml(item.period)}</span>
              <p>${escapeHtml(item.problem)}</p>
            </li>`).join("")}</ul>
          </details>
        </section>`
      : "";
    const reconciliationMarkup = reconciliationRows.length
      ? `<section class="grade-import-reconciliation-note">
          <strong>Confirm Student identity</strong>
          <p>${reconciliationRows.length} workbook row(s) match an active class participant whose Student Number is not yet recorded. APRISM will assign the workbook number only if you confirm below.</p>
        </section>`
      : "";
    const structureIssueMarkup = structureIssues.length
      ? `<p class="grade-import-omissions">Some assessment components will not be added: ${structureIssues.map(escapeHtml).join(" · ")}</p>`
      : "";
    const periodText = periods.map((period) => period.period_name).join(" · ");
    const contextLine = `${contextData.section_name || "Selected class"} · ${contextData.semester || ""} · AY ${contextData.school_year || ""}`;
    const categoryDetails = periods
      .map(
        (period, index) => `<section class="grade-import-period-review ${period.period_configured ? "" : "is-blocked"}" id="grade-period-panel-${index}" data-grade-period-panel role="tabpanel" aria-labelledby="grade-period-tab-${index}" tabindex="0" ${index ? "hidden" : ""}>
          <div class="grade-import-subheading"><strong>${escapeHtml(period.period_name)}</strong><span>${period.period_configured ? `${(period.components || []).length} assessment components` : "Grading period not configured"}</span></div>
          <div class="grade-import-category-list">${(period.categories || [])
            .map(
              (category) => `<div class="grade-import-category">
                <div><strong>${escapeHtml(category.category_name)}</strong><span>Not assigned by this import</span></div>
                <p>${(category.components || [])
                  .map(
                    (component) => `${escapeHtml(component.code)} · max ${escapeHtml(component.max_score ?? "not detected")}${component.max_score === null ? " · not added without a maximum" : ""}`,
                  )
                  .join(" · ")}</p>
              </div>`,
            )
            .join("")}</div>
          ${renderScoreMatrix(period)}
        </section>`,
      )
      .join("");
    const newScoreCount = Math.max(0, rawScoreCount - updateCount - unchangedCount);
    const scoresToSave = newScoreCount + updateCount;
    review.hidden = false;
    const classLabel = contextData.subject_name || "Selected class";
    const workbookLabel =
      data.template_type === "SHS"
        ? "SHS grading workbook"
        : "College grading workbook";
    const summaryMarkup = `<dl class="grade-import-summary grade-import-summary-compact">
        <div><dt>Students detected</dt><dd>${detectedStudentKeys.size}</dd></div>
        <div><dt>Students matched</dt><dd>${matchedStudents}</dd></div>
        <div><dt>Students needing attention</dt><dd>${attentionStudentKeys.size}</dd></div>
        <div><dt>Grading periods</dt><dd>${periods.length}<small>${escapeHtml(periodText)}</small></dd></div>
      </dl>
      <p class="grade-import-review-meta">${escapeHtml(workbookLabel)} · ${rawScoreCount} raw scores · ${updateCount} existing scores to update · ${ignoredFormulaCount} formula cells ignored</p>`;
    const periodTabs = periods
      .map(
        (period, index) => `<button type="button" class="grade-import-term-tab ${index === 0 ? "active" : ""} ${period.period_configured ? "" : "blocked"}" id="grade-period-tab-${index}" data-grade-period-tab aria-controls="grade-period-panel-${index}" aria-selected="${index === 0 ? "true" : "false"}" role="tab" tabindex="${index === 0 ? "0" : "-1"}">
          ${escapeHtml(period.period_name)}${period.period_configured ? "" : " · Not configured"}
        </button>`,
      )
      .join("");
    const confirmationMarkup = `
      <div class="grade-import-confirm">
        ${reconciliationRows.length ? '<label class="grade-import-confirm-check"><input type="checkbox" id="confirmIdentityReconciliations"> I confirm these Student Numbers match the listed class participants.</label>' : ""}
        ${updateCount ? '<label class="grade-import-confirm-check"><input type="checkbox" id="allowGradeConflicts"> I confirm these existing scores should be replaced.</label>' : ""}
        <div class="grade-import-confirm-actions">
          ${!alreadyConfirmed ? '<button type="button" class="grade-import-secondary" id="chooseAnotherWorkbook">Choose another workbook</button>' : ""}
          <button type="button" class="grade-import-submit" id="confirmGradeImport" ${blocking || updateCount > 0 || reconciliationRows.length ? "disabled" : ""}>${alreadyConfirmed ? "Done" : "Import grades"}</button>
        </div>
      </div>`;
    review.innerHTML = `
      <div class="grade-import-review-heading">
        <div>
          <h2>${blocking ? "Workbook review" : "Workbook reviewed"}</h2>
          <p>${escapeHtml(classLabel)}</p>
          <span class="grade-import-review-context">${escapeHtml(contextLine)}</span>
        </div>
        ${alreadyConfirmed ? '<span class="grade-import-status neutral">Already imported</span>' : ""}
      </div>
      ${summaryMarkup}
      ${issueMarkup}${reconciliationMarkup}${structureIssueMarkup}
      ${updateCount ? `<div class="grade-import-update-warning"><strong>${updateCount} existing score${updateCount === 1 ? "" : "s"} will be updated.</strong>${renderScoreChanges(periods)}</div>` : ""}
      <details class="grade-import-disclosure">
        <summary>View score details</summary>
        <div class="grade-import-term-tabs" role="tablist" aria-label="Grading periods">${periodTabs}</div>
        ${categoryDetails}
      </details>
      ${confirmationMarkup}`;
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
        if (!response.ok || !payload.success) {
          const error = new Error(
            payload.message || "The import could not be confirmed.",
          );
          error.outcomeUncertain = Boolean(payload.data?.outcome_uncertain);
          throw error;
        }
        showToast(alreadyConfirmed ? "Already imported" : "Grades imported", payload.message, "success");
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
        const successMessage = alreadyConfirmed
          ? "This workbook was already imported. No new grades were saved."
          : `${matchedStudents} Students · ${scoresToSave} raw score entries saved${updateCount ? ` · ${updateCount} existing scores updated` : ""} · ${escapeHtml(periodText)}`;
        review.innerHTML = `<div class="grade-import-complete"><div><h2>${alreadyConfirmed ? "Already imported" : "Grades imported"}</h2><p>${successMessage}</p></div><div class="grade-import-complete-actions"><a class="grade-import-secondary" href="${escapeHtml(analyticsHref)}">View Grade Analytics</a><button type="button" class="grade-import-submit" id="importAnotherWorkbook">Import another workbook</button></div></div>`;
        if (window.lucide?.createIcons) window.lucide.createIcons({ root: review });
        document.getElementById("importAnotherWorkbook")?.addEventListener("click", resetForAnotherWorkbook);
      } catch (error) {
        if (error.outcomeUncertain) {
          confirm.disabled = true;
          confirm.textContent = "Check import status";
          showToast("Check Grade Import status", error.message);
        } else {
          confirm.disabled = false;
          confirm.textContent = alreadyConfirmed ? "Done" : "Import grades";
          showToast("Grade Import could not be confirmed", error.message);
        }
      }
    });
    document.getElementById("chooseAnotherWorkbook")?.addEventListener("click", resetForAnotherWorkbook);
  }

  function resetForAnotherWorkbook() {
    if (fileInput) fileInput.value = "";
    if (fileName) fileName.textContent = "Choose workbook";
    if (review) {
      review.hidden = true;
      review.innerHTML = "";
    }
    document.getElementById("gradeImportForm")?.scrollIntoView({ behavior: "smooth", block: "start" });
    fileInput?.focus();
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
    const button = previewButton;
    button.disabled = true;
    button.textContent = "Reviewing…";
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
      review.innerHTML = `<div class="grade-import-notice error" role="alert"><strong>Workbook could not be reviewed</strong><p>${escapeHtml(error.message)}</p><button type="button" class="grade-import-secondary" id="tryReviewAgain">Try again</button></div>`;
      document.getElementById("tryReviewAgain")?.addEventListener("click", () => form.requestSubmit());
    } finally {
      button.disabled = false;
      button.textContent = "Review workbook";
    }
  });
  fileInput?.addEventListener("change", () => {
    const selected = fileInput.files?.[0];
    fileName.textContent = selected
      ? selected.name
      : "Choose workbook";
    if (review && !review.hidden) {
      review.hidden = true;
      review.innerHTML = "";
    }
  });
  classSelect?.addEventListener("change", () => {
    renderClassContext();
    if (review && !review.hidden) {
      review.hidden = true;
      review.innerHTML = "";
    }
  });
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
