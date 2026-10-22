document.addEventListener("DOMContentLoaded", () => {
  const layer = document.querySelector("[data-referral-sheet]");
  if (!layer) return;

  const dialog = layer.querySelector("[data-referral-sheet-dialog]");
  const form = layer.querySelector("[data-referral-form]");
  const classSelect = layer.querySelector("[data-referral-class]");
  const studentSelect = layer.querySelector("[data-referral-student]");
  const combo = layer.querySelector("[data-referral-student-combobox]");
  const studentInput = combo?.querySelector('[role="combobox"]');
  const listbox = combo?.querySelector('[role="listbox"]');
  const studentStatus = combo?.querySelector("[data-referral-student-status]");
  const studentError = layer.querySelector("[data-referral-student-error]");
  const noteToggle = layer.querySelector("[data-referral-note-toggle]");
  const noteRegion = layer.querySelector("[data-referral-note-region]");
  const reason = layer.querySelector("[data-referral-specific-reason]");
  const reasonOptions = reason ? Array.from(reason.options).slice(1).map((option) => option.cloneNode(true)) : [];
  const categoryButtons = Array.from(layer.querySelectorAll("[data-referral-category]"));
  const reasonGroups = {
    attendance: ["frequent_absences", "repeated_lateness"],
    academic: ["low_academic_performance", "missing_late_requirements"],
    behavior: ["behavior_conduct"],
    other: ["other"],
  };
  const categoryForReason = (code) => Object.keys(reasonGroups).find((category) => reasonGroups[category].includes(code)) || "";
  const selectedReasonCode = () => reason?.value || "";
  const note = layer.querySelector("[data-referral-note]");
  const noteError = layer.querySelector("[data-referral-note-error]");
  const discardButton = layer.querySelector("[data-referral-sheet-discard]");
  const discardConfirm = document.querySelector("[data-referral-discard-confirm]");
  const discardConfirmedButton = discardConfirm?.querySelector("[data-referral-discard-confirmed]");
  const discardKeepButtons = Array.from(discardConfirm?.querySelectorAll("[data-referral-discard-keep]") || []);
  const evidenceEmpty = layer.querySelector("[data-referral-evidence-empty]");
  const evidenceContent = layer.querySelector("[data-referral-evidence-content]");
  const evidenceToken = layer.querySelector("[data-referral-evidence-token]");
  const evidenceContext = layer.querySelector("[data-referral-source-context]");
  const contextSection = layer.querySelector("[data-referral-context-section]");
  const sheetTitle = layer.querySelector("[data-referral-sheet-title]");
  const submitButton = form?.querySelector('button[type="submit"]');
  const dataNode = layer.querySelector("[data-referral-sheet-data]");
  let rosterData = {};
  try { rosterData = JSON.parse(dataNode?.textContent || "{}"); } catch { rosterData = {}; }

  let opener = null;
  let filtered = [];
  let activeIndex = -1;
  let closingTimer = null;
  let closeSequence = 0;
  let isClosing = false;
  let evidenceRequestSequence = 0;
  let evidencePending = false;
  let evidenceSubjectKey = "";
  let discardContext = {
    classId: layer.dataset.referralInitialClassId || "",
    sceId: layer.dataset.referralInitialSceId || "",
    studentId: "",
  };
  let cleanDraftState = {
    classId: discardContext.classId,
    sceId: discardContext.sceId,
    category: "",
    reason: "",
    note: "",
  };
  let preserveInitialStudentError = Boolean(studentError && !studentError.hidden);

  const setStudentError = (visible, message = "Select a Student from the matching results.") => {
    if (studentError) {
      if (message) studentError.textContent = message;
      studentError.hidden = !visible;
    }
    if (studentInput) {
      studentInput.setAttribute("aria-invalid", visible ? "true" : "false");
      if (visible) studentInput.setAttribute("aria-describedby", studentError?.id || "");
      else studentInput.removeAttribute("aria-describedby");
    }
  };
  const setNoteError = (visible) => {
    if (noteError) noteError.hidden = !visible;
    if (note) {
      note.setAttribute("aria-invalid", visible ? "true" : "false");
      const helpId = layer.querySelector("[data-referral-note]")?.getAttribute("aria-describedby")?.split(/\s+/).find((id) => id.endsWith("-note-help"));
      const errorId = noteError?.id;
      const descriptions = [helpId, visible ? errorId : null].filter(Boolean);
      if (descriptions.length) note.setAttribute("aria-describedby", descriptions.join(" "));
    }
  };

  const setEvidenceLoading = (message) => {
    evidencePending = true;
    evidenceSubjectKey = "";
    evidenceContent?.setAttribute("aria-busy", "true");
    if (evidenceEmpty) { evidenceEmpty.hidden = false; evidenceEmpty.textContent = message; }
    if (evidenceContent) evidenceContent.hidden = true;
    if (evidenceToken) evidenceToken.value = "";
    if (submitButton) submitButton.disabled = true;
  };

  const renderEvidence = (snapshot, token) => {
    const assessable = Boolean(snapshot?.assessable);
    const field = (selector, value) => { const node = layer.querySelector(selector); if (node) node.textContent = value ?? "—"; };
    field("[data-referral-evidence-tier]", snapshot?.tier_label || "Insufficient evidence");
    field("[data-referral-evidence-reason]", snapshot?.reason_strings?.[0] || (assessable ? "Current attendance evidence" : "Attendance evidence is not available yet."));
    field("[data-evidence-recorded]", String(Number(snapshot?.recorded_count || 0)));
    field("[data-evidence-absent]", assessable ? String(Number(snapshot?.absent_count || 0)) : "—");
    field("[data-evidence-late]", assessable ? String(Number(snapshot?.late_count || 0)) : "—");
    field("[data-evidence-effective]", assessable ? String(Number(snapshot?.effective_absent_count || 0)) : "—");
    const captured = layer.querySelector("[data-evidence-captured-at]");
    if (captured) {
      const date = new Date(snapshot?.captured_at || "");
      captured.textContent = Number.isNaN(date.getTime()) ? "this session" : new Intl.DateTimeFormat("en-PH", { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit", timeZone: "Asia/Manila" }).format(date) + " (Philippine time)";
      if (!Number.isNaN(date.getTime())) captured.dateTime = date.toISOString();
    }
    layer.querySelectorAll("[data-evidence-assessable-row]").forEach((row) => { row.hidden = !assessable; });
    if (evidenceEmpty) evidenceEmpty.hidden = true;
    if (evidenceContent) evidenceContent.hidden = false;
    if (evidenceToken) evidenceToken.value = token || "";
    evidencePending = false;
    evidenceContent?.setAttribute("aria-busy", "false");
    if (submitButton) submitButton.disabled = !token;
  };

  const classRecord = () => rosterData[String(classSelect?.value || "")] || null;
  const studentRecord = () => classRecord()?.students?.find((student) => String(student.student_class_enrollment_id) === String(studentSelect?.value)) || null;

  const getDraftState = () => ({
    classId: classSelect?.value || "",
    sceId: studentSelect?.value || "",
    category: categoryButtons.find((button) => button.getAttribute("aria-pressed") === "true")?.dataset.referralCategory || "",
    reason: selectedReasonCode(),
    note: note?.value || "",
  });
  const draftIsDirty = () => {
    const current = getDraftState();
    return Object.keys(cleanDraftState).some((key) => current[key] !== cleanDraftState[key]);
  };
  const setCleanDraftState = () => { cleanDraftState = getDraftState(); };

  const updateHeader = () => {
    const cls = classRecord();
    const student = studentRecord();
    const context = layer.querySelector("[data-referral-sheet-context]");
    const classContext = layer.querySelector("[data-referral-sheet-class-context]");
    const identity = layer.querySelector("[data-referral-identity]");
    const avatar = layer.querySelector("[data-referral-avatar]");
    if (context) {
      context.textContent = student ? `${student.student_name}${student.student_number ? ` · ${student.student_number}` : ""}` : "";
    }
    if (classContext) {
      classContext.textContent = cls?.label || "";
    }
    if (identity) identity.hidden = !student;
    if (avatar) avatar.textContent = student?.student_name?.trim()?.charAt(0)?.toLocaleUpperCase() || "S";
    const id = layer.querySelector("[data-referral-student-id]");
    if (id) id.value = String(student?.student_id || "");
  };

  const setContextualMode = (contextual) => {
    const hasContextSelectionError = classSelect?.getAttribute("aria-invalid") === "true"
      || studentInput?.getAttribute("aria-invalid") === "true"
      || Boolean(studentError && !studentError.hidden);
    const enabled = Boolean(contextual && !hasContextSelectionError);
    layer.dataset.referralContextual = enabled ? "true" : "false";
    if (contextSection) contextSection.hidden = enabled;
    if (sheetTitle) sheetTitle.textContent = enabled ? "Refer Student" : "Submit Student Referral";
    const breadcrumbRoot = layer.querySelector("[data-referral-breadcrumb-root]");
    if (breadcrumbRoot) breadcrumbRoot.textContent = enabled ? "Risk Alerts" : "Referrals";
    const breadcrumb = layer.querySelector("[data-referral-breadcrumb]");
    if (breadcrumb) breadcrumb.textContent = enabled ? "Refer Student" : "New Referral";
  };

  const renderOptions = (query = "") => {
    if (!listbox) return;
    const normalized = query.trim().toLocaleLowerCase();
    const students = classRecord()?.students || [];
    filtered = normalized ? students.filter((student) => `${student.student_name} ${student.student_number}`.toLocaleLowerCase().includes(normalized)) : students;
    activeIndex = -1;
    studentInput?.removeAttribute("aria-activedescendant");
    listbox.replaceChildren();
    if (filtered.length === 0) {
      const empty = document.createElement("div");
      empty.className = "referral-student-empty";
      empty.setAttribute("role", "option");
      empty.setAttribute("aria-disabled", "true");
      empty.textContent = "No matching Students in this class.";
      listbox.appendChild(empty);
      if (studentStatus) studentStatus.textContent = empty.textContent;
      return;
    }
    if (studentStatus) studentStatus.textContent = `${filtered.length} Student${filtered.length === 1 ? "" : "s"} available.`;
    filtered.forEach((student, index) => {
      const option = document.createElement("div");
      option.id = `referral-sheet-option-${index}`;
      option.className = "referral-student-option";
      option.setAttribute("role", "option");
      option.setAttribute("aria-selected", String(String(student.student_class_enrollment_id) === String(studentSelect?.value)));
      const name = document.createElement("strong"); name.textContent = student.student_name || "Student"; option.appendChild(name);
      if (student.student_number) { const number = document.createElement("small"); number.textContent = student.student_number; option.appendChild(number); }
      option.addEventListener("mousedown", (event) => event.preventDefault());
      option.addEventListener("click", () => chooseStudent(student));
      listbox.appendChild(option);
    });
  };

  const setListOpen = (open) => {
    if (!studentInput || !listbox) return;
    const visible = Boolean(open && classSelect?.value);
    listbox.hidden = !visible;
    studentInput.setAttribute("aria-expanded", visible ? "true" : "false");
    if (!visible) { activeIndex = -1; studentInput.removeAttribute("aria-activedescendant"); }
  };

  const setActive = (index) => {
    if (!filtered.length || !studentInput || !listbox) return;
    activeIndex = (index + filtered.length) % filtered.length;
    const options = listbox.querySelectorAll('[role="option"]:not([aria-disabled="true"])');
    options.forEach((option, optionIndex) => { option.classList.toggle("is-active", optionIndex === activeIndex); option.setAttribute("aria-selected", String(optionIndex === activeIndex)); });
    const option = options[activeIndex];
    if (option) { studentInput.setAttribute("aria-activedescendant", option.id); option.scrollIntoView({ block: "nearest" }); }
  };

  const refreshEvidence = async () => {
    const cls = classSelect?.value || "";
    const sce = studentSelect?.value || "";
    if (!cls || !sce) {
      evidenceRequestSequence++;
      evidencePending = false;
      evidenceSubjectKey = "";
      evidenceContent?.setAttribute("aria-busy", "false");
      if (evidenceToken) evidenceToken.value = "";
      if (evidenceEmpty) { evidenceEmpty.hidden = false; evidenceEmpty.textContent = "Select a student to load attendance evidence."; }
      if (evidenceContent) evidenceContent.hidden = true;
      if (submitButton) submitButton.disabled = false;
      return;
    }
    const sequence = ++evidenceRequestSequence;
    setEvidenceLoading("Refreshing current attendance evidence…");
    const payload = new FormData(form);
    payload.set("operational_class_id", cls);
    payload.set("student_class_enrollment_id", sce);
    payload.set("source_context", evidenceContext?.value || "referrals");
    try {
      const response = await fetch(`${window.APP_URL}/actions/teacher/referral_evidence.php`, { method: "POST", body: payload, credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } });
      const result = await response.json();
      if (sequence !== evidenceRequestSequence) return;
      if (!response.ok || !result.ok) throw new Error(result.message || "Evidence could not be refreshed.");
      renderEvidence(result.snapshot, result.token);
      evidenceSubjectKey = `${cls}:${sce}`;
    } catch (error) {
      if (sequence !== evidenceRequestSequence) return;
      evidencePending = false;
      evidenceSubjectKey = "";
      evidenceContent?.setAttribute("aria-busy", "false");
      if (evidenceToken) evidenceToken.value = "";
      if (evidenceEmpty) { evidenceEmpty.hidden = false; evidenceEmpty.textContent = error.message || "Attendance evidence could not be verified. Try again."; }
      if (evidenceContent) evidenceContent.hidden = true;
      if (submitButton) submitButton.disabled = true;
    }
  };

  const chooseStudent = (student) => {
    if (!studentSelect || !studentInput) return;
    studentSelect.value = String(student.student_class_enrollment_id);
    studentInput.value = `${student.student_name}${student.student_number ? ` · ${student.student_number}` : ""}`;
    if (!preserveInitialStudentError) setStudentError(false);
    setListOpen(false);
    updateHeader();
    refreshEvidence();
  };

  const setClass = (classId, sceId = "", studentId = "") => {
    if (!classSelect || !studentSelect) return;
    classSelect.value = Object.prototype.hasOwnProperty.call(rosterData, String(classId)) ? String(classId) : "";
    const cls = classRecord();
    const students = cls?.students || [];
    const valid = students.find((student) => (sceId && String(student.student_class_enrollment_id) === String(sceId)) || (!sceId && studentId && String(student.student_id) === String(studentId)));
    studentSelect.replaceChildren(new Option(classSelect.value ? "Select a Student" : "Select a class first", ""));
    for (const student of students) {
      const option = new Option(`${student.student_name}${student.student_number ? ` · ${student.student_number}` : ""}`, String(student.student_class_enrollment_id));
      option.dataset.studentId = String(student.student_id);
      option.dataset.studentName = student.student_name;
      option.dataset.studentNumber = student.student_number || "";
      studentSelect.add(option);
    }
    studentSelect.disabled = !classSelect.value;
    studentSelect.hidden = Boolean(studentInput);
    studentSelect.required = !studentInput;
    if (studentInput && combo) {
      combo.hidden = false;
      studentInput.disabled = !classSelect.value;
      studentInput.setAttribute("aria-required", "true");
    }
    studentSelect.value = valid ? String(valid.student_class_enrollment_id) : "";
    if (studentInput) studentInput.value = valid ? `${valid.student_name}${valid.student_number ? ` · ${valid.student_number}` : ""}` : "";
    updateHeader();
    renderOptions("");
    setListOpen(false);
    if (!preserveInitialStudentError) setStudentError(false);
  };

  const finishClose = (sequence) => {
    if (sequence !== closeSequence || !isClosing) return;
    if (closingTimer) window.clearTimeout(closingTimer);
    closingTimer = null;
    layer.hidden = true;
    layer.inert = false;
    layer.classList.remove("is-open", "is-closing");
    isClosing = false;
  };

  const openSheet = (button) => {
    closeSequence++;
    if (closingTimer) window.clearTimeout(closingTimer);
    closingTimer = null;
    isClosing = false;
    layer.inert = false;
    layer.classList.remove("is-closing");
    opener = button;
    const source = button?.dataset.sourceContext === "risk_alerts" ? "risk_alerts" : "referrals";
    const wasDirty = draftIsDirty();
    if (evidenceContext) evidenceContext.value = source;
    const pageInput = layer.querySelector("[data-referral-source-page]");
    if (pageInput) pageInput.value = String(Math.max(1, Number(button?.dataset.sourcePage || 1)));
    if (button?.dataset.operationalClassId || button?.dataset.sceId || button?.dataset.studentId) {
      const sourceSelection = {
        classId: button.dataset.operationalClassId || "",
        sceId: button.dataset.sceId || "",
        studentId: button.dataset.studentId || "",
      };
      setClass(sourceSelection.classId, sourceSelection.sceId, sourceSelection.studentId);
      if (source === "risk_alerts") {
        discardContext = sourceSelection;
        if (!wasDirty) setCleanDraftState();
      }
    } else {
      setClass(classSelect?.value || "", studentSelect?.value || "");
    }
    setContextualMode(source === "risk_alerts" && Boolean(studentSelect?.value));
    layer.hidden = false;
    layer.setAttribute("aria-hidden", "true");
    document.body.classList.add("referral-sheet-open");
    requestAnimationFrame(() => {
      if (layer.hidden || isClosing) return;
      layer.setAttribute("aria-hidden", "false");
      layer.classList.add("is-open");
      const target = layer.dataset.referralContextual === "true" ? reason : (studentSelect?.value ? reason : (classSelect?.value ? studentInput : classSelect));
      (target && !target.disabled ? target : dialog)?.focus();
    });
    if (studentSelect?.value) {
      const subjectKey = `${classSelect?.value || ""}:${studentSelect.value}`;
      if (evidenceSubjectKey !== subjectKey || !evidenceToken?.value) refreshEvidence();
    }
  };

  const closeSheet = () => {
    if (layer.hidden || isClosing) return;
    isClosing = true;
    const sequence = ++closeSequence;
    setListOpen(false);
    document.body.classList.remove("referral-sheet-open");
    const fallbackFocus = document.querySelector("[data-referral-sheet-return-focus]") || document.querySelector('[data-referral-sheet-open]') || document.querySelector("main h1");
    (opener?.isConnected ? opener : fallbackFocus)?.focus();
    layer.setAttribute("aria-hidden", "true");
    layer.inert = true;
    layer.classList.add("is-closing");
    layer.classList.remove("is-open");
    if (window.matchMedia?.("(prefers-reduced-motion: reduce)").matches) {
      finishClose(sequence);
      return;
    }
    closingTimer = window.setTimeout(() => finishClose(sequence), 160);
  };

  if (studentSelect && studentInput && combo) {
    const initialStudentId = layer.querySelector("[data-referral-student-id]")?.value || "";
    const initialSceId = studentSelect.value;
    const initialClassId = classSelect?.value || "";
    setClass(initialClassId, initialSceId, initialStudentId);
    if (evidenceToken?.value && studentSelect.value && evidenceContent && !evidenceContent.hidden) {
      evidenceSubjectKey = `${classSelect?.value || ""}:${studentSelect.value}`;
      discardContext.studentId = initialStudentId;
    }
    setContextualMode(evidenceContext?.value === "risk_alerts" && Boolean(studentSelect.value));
    preserveInitialStudentError = false;
  }
  if (!layer.hidden) {
    document.body.classList.add("referral-sheet-open");
    requestAnimationFrame(() => {
      if (layer.hidden) return;
      layer.classList.add("is-open");
      const target = layer.dataset.referralContextual === "true" ? reason : (studentSelect?.value ? reason : (classSelect?.value ? studentInput : classSelect));
      (target && !target.disabled ? target : dialog)?.focus();
    });
    if (studentSelect?.value && (!evidenceToken?.value || evidenceSubjectKey !== `${classSelect?.value || ""}:${studentSelect.value}`)) refreshEvidence();
  }

  document.addEventListener("click", (event) => {
    const trigger = event.target.closest("[data-referral-sheet-open]");
    if (!trigger) return;
    event.preventDefault();
    openSheet(trigger);
  });
  layer.querySelectorAll("[data-referral-sheet-close]").forEach((button) => button.addEventListener("click", closeSheet));

  classSelect?.addEventListener("change", () => { setClass(classSelect.value); refreshEvidence(); });
  studentInput?.addEventListener("focus", () => { renderOptions(studentInput.value === (studentSelect?.selectedOptions?.[0]?.textContent.trim() || "") ? "" : studentInput.value); setListOpen(true); });
  studentInput?.addEventListener("input", () => { if (studentSelect) studentSelect.value = ""; updateHeader(); setStudentError(false); renderOptions(studentInput.value); setListOpen(true); refreshEvidence(); });
  studentInput?.addEventListener("keydown", (event) => {
    if (event.key === "ArrowDown") { event.preventDefault(); if (listbox?.hidden) { renderOptions(studentInput.value); setListOpen(true); } setActive(activeIndex + 1); }
    else if (event.key === "ArrowUp") { event.preventDefault(); if (listbox?.hidden) { renderOptions(studentInput.value); setListOpen(true); } setActive(activeIndex < 0 ? filtered.length - 1 : activeIndex - 1); }
    else if (event.key === "Enter" && listbox && !listbox.hidden) { event.preventDefault(); if (activeIndex >= 0) chooseStudent(filtered[activeIndex]); }
    else if (event.key === "Escape") { event.preventDefault(); event.stopPropagation(); setListOpen(false); }
  });
  document.addEventListener("pointerdown", (event) => { if (combo && !combo.contains(event.target)) setListOpen(false); });
  combo?.addEventListener("focusout", (event) => { if (!event.relatedTarget || !combo.contains(event.relatedTarget)) setListOpen(false); });

  noteToggle?.addEventListener("click", () => {
    if (selectedReasonCode() === "other") { noteRegion?.classList.add("is-open"); noteToggle.setAttribute("aria-expanded", "true"); note?.focus(); return; }
    const opening = !noteRegion?.classList.contains("is-open");
    noteRegion?.classList.toggle("is-open", opening);
    noteToggle.setAttribute("aria-expanded", opening ? "true" : "false");
    if (opening) note?.focus();
  });
  const syncOther = () => {
    const required = selectedReasonCode() === "other";
    if (note) { note.required = required; note.setAttribute("aria-required", required ? "true" : "false"); }
    const state = layer.querySelector("[data-referral-note-state]");
    if (state) state.textContent = required ? "(Required)" : "(Optional)";
    const toggleLabel = noteToggle?.querySelector("span");
    if (toggleLabel) toggleLabel.textContent = required ? "Supporting note required" : "Add supporting note";
    if (required) { noteRegion?.classList.add("is-open"); noteToggle?.setAttribute("aria-expanded", "true"); }
    else if (noteError?.textContent.includes("Other Concern")) setNoteError(false);
  };
  const applyReasonCategory = (category, preserveSelection = true) => {
    if (!reason || !Object.prototype.hasOwnProperty.call(reasonGroups, category)) return;
    const currentReason = preserveSelection ? reason.value : "";
    categoryButtons.forEach((button) => {
      const selected = button.dataset.referralCategory === category;
      button.classList.toggle("is-selected", selected);
      button.setAttribute("aria-pressed", String(selected));
    });
    const placeholder = new Option("Select a specific reason", "");
    const validOptions = reasonOptions
      .filter((option) => reasonGroups[category].includes(option.value))
      .map((option) => option.cloneNode(true));
    reason.replaceChildren(placeholder, ...validOptions);
    reason.disabled = false;
    reason.value = validOptions.some((option) => option.value === currentReason) ? currentReason : "";
  };
  const initialReasonCategory = categoryForReason(reason?.value || "");
  if (initialReasonCategory) {
    applyReasonCategory(initialReasonCategory);
  } else if (reason) {
    reason.replaceChildren(new Option("Select a specific reason", ""));
    reason.disabled = true;
  }
  categoryButtons.forEach((button) => button.addEventListener("click", () => {
    applyReasonCategory(button.dataset.referralCategory || "", true);
    const reasonError = layer.querySelector("[data-referral-reason-error]");
    if (reasonError) reasonError.hidden = true;
    reason?.setAttribute("aria-invalid", "false");
    reason?.removeAttribute("aria-describedby");
    syncOther();
  }));
  reason?.addEventListener("change", () => {
    const reasonError = layer.querySelector("[data-referral-reason-error]");
    if (reasonError) reasonError.hidden = true;
    reason.setAttribute("aria-invalid", "false");
    reason.removeAttribute("aria-describedby");
    syncOther();
  });
  reason?.addEventListener("invalid", () => {
    const reasonError = layer.querySelector("[data-referral-reason-error]");
    if (reasonError) { reasonError.textContent = "Choose a referral reason."; reasonError.hidden = false; }
    reason.setAttribute("aria-invalid", "true");
    reason.setAttribute("aria-describedby", reasonError?.id || "");
  });
  note?.addEventListener("input", () => { if (selectedReasonCode() !== "other" || note.value.trim()) setNoteError(false); });
  note?.addEventListener("invalid", (event) => {
    if (selectedReasonCode() !== "other" || note.value.trim() !== "") return;
    event.preventDefault();
    setNoteError(true);
    noteRegion?.classList.add("is-open");
    noteToggle?.setAttribute("aria-expanded", "true");
    note.focus();
  });
  syncOther();
  if (discardContext.sceId && !discardContext.studentId) {
    discardContext.studentId = studentSelect?.selectedOptions?.[0]?.dataset.studentId || "";
  }

  let discardConfirmCloseTimer = 0;
  const closeDiscardConfirm = (restoreFocus = true, immediate = false) => {
    if (!discardConfirm || discardConfirm.hidden) return;
    discardConfirm.setAttribute("aria-hidden", "true");
    discardConfirm.inert = true;
    layer.inert = false;
    window.clearTimeout(discardConfirmCloseTimer);
    const reducedMotion = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
    if (immediate || reducedMotion) {
      discardConfirm.hidden = true;
      discardConfirm.inert = false;
    } else {
      discardConfirm.classList.add("is-closing");
      discardConfirmCloseTimer = window.setTimeout(() => {
        discardConfirm.hidden = true;
        discardConfirm.inert = false;
        discardConfirm.classList.remove("is-closing");
      }, 200);
    }
    if (restoreFocus && !layer.hidden) discardButton?.focus();
  };
  const resetDraft = () => {
    evidenceRequestSequence++;
    evidencePending = false;
    evidenceSubjectKey = "";
    setClass(discardContext.classId, discardContext.sceId, discardContext.studentId);
    setContextualMode(evidenceContext?.value === "risk_alerts" && Boolean(studentSelect?.value));
    categoryButtons.forEach((button) => {
      button.classList.remove("is-selected");
      button.setAttribute("aria-pressed", "false");
    });
    if (reason) {
      reason.replaceChildren(new Option("Select a specific reason", ""));
      reason.value = "";
      reason.disabled = true;
      reason.setAttribute("aria-invalid", "false");
      reason.removeAttribute("aria-describedby");
    }
    if (note) {
      note.value = "";
      note.required = false;
      note.setAttribute("aria-required", "false");
      note.setAttribute("aria-invalid", "false");
      note.removeAttribute("aria-describedby");
    }
    noteRegion?.classList.remove("is-open");
    noteToggle?.setAttribute("aria-expanded", "false");
    const noteLabel = noteToggle?.querySelector("span");
    if (noteLabel) noteLabel.textContent = "Add supporting note";
    const noteState = layer.querySelector("[data-referral-note-state]");
    if (noteState) noteState.textContent = "(Optional)";
    if (studentInput) {
      const selectedStudent = studentRecord();
      studentInput.value = selectedStudent ? `${selectedStudent.student_name}${selectedStudent.student_number ? ` · ${selectedStudent.student_number}` : ""}` : "";
    }
    setStudentError(false);
    setNoteError(false);
    form?.querySelectorAll('[role="alert"]').forEach((message) => { message.hidden = true; });
    form?.querySelectorAll('[aria-invalid="true"]').forEach((field) => {
      field.setAttribute("aria-invalid", "false");
      field.removeAttribute("aria-describedby");
    });
    if (evidenceToken) evidenceToken.value = "";
    evidenceContent?.setAttribute("aria-busy", "false");
    if (evidenceContent) evidenceContent.hidden = true;
    if (evidenceEmpty) {
      evidenceEmpty.hidden = false;
      evidenceEmpty.textContent = "Select a student to load attendance evidence.";
    }
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.removeAttribute("aria-disabled");
    }
    setCleanDraftState();
  };
  const discardDraft = () => {
    closeDiscardConfirm(false, true);
    resetDraft();
    closeSheet();
  };
  discardButton?.addEventListener("click", () => {
    if (!draftIsDirty()) { discardDraft(); return; }
    if (!discardConfirm) return;
    window.clearTimeout(discardConfirmCloseTimer);
    discardConfirm.classList.remove("is-closing");
    discardConfirm.inert = false;
    discardConfirm.hidden = false;
    discardConfirm.setAttribute("aria-hidden", "false");
    layer.inert = true;
  });
  discardKeepButtons.forEach((button) => button.addEventListener("click", () => closeDiscardConfirm()));
  discardConfirmedButton?.addEventListener("click", discardDraft);
  discardConfirm?.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    event.preventDefault();
    event.stopPropagation();
    closeDiscardConfirm();
  });

  form?.addEventListener("submit", (event) => {
    if (!studentSelect?.value) { event.preventDefault(); setStudentError(true); studentInput?.focus(); setListOpen(true); return; }
    if (!selectedReasonCode()) {
      event.preventDefault();
      const reasonError = layer.querySelector("[data-referral-reason-error]");
      if (reasonError) { reasonError.textContent = "Choose a referral reason."; reasonError.hidden = false; }
      (reason?.disabled ? categoryButtons[0] : reason)?.focus();
      return;
    }
    if (selectedReasonCode() === "other" && !note?.value.trim()) {
      event.preventDefault();
      setNoteError(true);
      noteRegion?.classList.add("is-open");
      noteToggle?.setAttribute("aria-expanded", "true");
      note?.focus();
      return;
    }
    if (evidencePending || !evidenceToken?.value) {
      event.preventDefault();
      if (evidenceEmpty) { evidenceEmpty.hidden = false; evidenceEmpty.textContent = "Refresh the attendance evidence before submitting this referral."; }
      evidenceContent && (evidenceContent.hidden = true);
      refreshEvidence();
      return;
    }
    if (submitButton) { submitButton.disabled = true; submitButton.setAttribute("aria-disabled", "true"); }
  });

  layer.addEventListener("keydown", (event) => {
    if (isClosing) return;
    if (event.key === "Escape") { event.preventDefault(); closeSheet(); return; }
    if (event.key !== "Tab" || layer.hidden || layer.inert) return;
    const focusable = Array.from(dialog.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')).filter((node) => !node.hidden && node.getClientRects().length);
    if (!focusable.length) { event.preventDefault(); dialog.focus(); return; }
    const first = focusable[0], last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });

  const toastData = window.APRISM_REFERRAL_FLASH || {};
  const message = toastData.error || toastData.warning || toastData.success;
  if (message) {
    let container = document.getElementById("toastContainer");
    if (!container) { container = document.createElement("div"); container.id = "toastContainer"; container.className = "toast-container-custom referral-sheet-toast"; container.setAttribute("aria-live", "polite"); container.setAttribute("aria-atomic", "true"); document.body.appendChild(container); }
    const toast = document.createElement("div"); toast.className = "toast-custom";
    const icon = document.createElement("div"); icon.className = `toast-icon ${toastData.error || toastData.warning ? "warning" : "success"}`;
    const iconElement = document.createElement("i"); iconElement.setAttribute("data-lucide", toastData.error || toastData.warning ? "triangle-alert" : "circle-check"); iconElement.setAttribute("aria-hidden", "true"); icon.appendChild(iconElement);
    const content = document.createElement("div"); content.className = "toast-content";
    const title = document.createElement("h5"); title.className = "toast-title"; title.textContent = toastData.error ? "Referral not saved" : (toastData.warning ? "Referral already processed" : "Referral submitted");
    const body = document.createElement("p"); body.className = "toast-text"; body.textContent = message;
    content.append(title, body); toast.append(icon, content); container.appendChild(toast);
    window.lucide?.createIcons?.();
    requestAnimationFrame(() => toast.classList.add("show"));
    setTimeout(() => { toast.classList.remove("show"); setTimeout(() => toast.remove(), 240); }, 4800);
  }
});
