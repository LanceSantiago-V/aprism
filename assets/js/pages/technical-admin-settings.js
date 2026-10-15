document.addEventListener("DOMContentLoaded", function () {
  const settingsForms = Array.from(
    document.querySelectorAll("form[data-settings-form]")
  );
  const reviewModalElement = document.getElementById("settingsReviewModal");
  const reviewChanges = document.getElementById("settingsReviewChanges");
  const reviewTitle = document.getElementById("settingsReviewTitle");
  const confirmButton = document.getElementById("confirmSettingsSaveBtn");
  const reviewModal = reviewModalElement && window.bootstrap
    ? bootstrap.Modal.getOrCreateInstance(reviewModalElement)
    : null;
  const touchedControls = new WeakSet();
  const baselines = new WeakMap();
  let pendingForm = null;
  let confirmedSubmitInProgress = false;

  const getControl = (form, name) =>
    Array.from(form.elements).find((element) => element.name === name);

  const normalizeValue = (control) => {
    const value = String(control?.value ?? "").trim();
    if (control?.type === "number" && value !== "" && /^\d+$/.test(value)) {
      return String(Number(value));
    }
    return value;
  };

  function effectiveValues(form) {
    const values = {};
    const controls = Array.from(form.elements).filter(
      (control) =>
        control.name &&
        control.name !== "csrf_token" &&
        !control.disabled &&
        control.type !== "submit" &&
        control.type !== "button"
    );

    controls.forEach((control) => {
      values[control.name] = normalizeValue(control);
    });

    return values;
  }

  function scheduleChanges(form, baseline) {
    const enabled = getControl(form, "backup_schedule_enabled");
    const frequency = getControl(form, "backup_schedule_frequency");
    const time = getControl(form, "backup_schedule_time");
    const day = getControl(form, "backup_schedule_day");
    if (!enabled || !frequency || !time || !day) return [];

    const currentEnabled = normalizeValue(enabled);
    const beforeEnabled = baseline.backup_schedule_enabled ?? "0";
    const changed = [];
    const add = (name, before, after) => {
      if (before !== after) changed.push({ name, before, after });
    };

    add("backup_schedule_enabled", beforeEnabled, currentEnabled);

    // The existing endpoint persists schedule details only when enabled.
    // Turning scheduling off changes only that switch and retains prior details.
    if (currentEnabled !== "1") return changed;

    const currentFrequency = normalizeValue(frequency);
    const beforeFrequency = baseline.backup_schedule_frequency ?? "";
    const currentTime = normalizeValue(time);
    const beforeTime = baseline.backup_schedule_time ?? "";
    add("backup_schedule_frequency", beforeFrequency, currentFrequency);
    add("backup_schedule_time", beforeTime, currentTime);

    // The server normalizes a daily schedule's unused day to Monday. Do not
    // surface that implementation detail as a meaningful daily setting.
    if (currentFrequency === "weekly") {
      const currentDay = normalizeValue(day);
      const beforeDay = baseline.backup_schedule_day ?? "";
      add("backup_schedule_day", beforeDay, currentDay);
    }

    return changed;
  }

  function changedValues(form) {
    const baseline = baselines.get(form) || {};
    if (form.id === "backupScheduleForm") {
      return scheduleChanges(form, baseline);
    }

    const current = effectiveValues(form);
    const names = new Set([...Object.keys(baseline), ...Object.keys(current)]);
    return Array.from(names)
      .filter((name) => baseline[name] !== current[name])
      .map((name) => ({ name, before: baseline[name] ?? "", after: current[name] ?? "" }));
  }

  function isInteger(value) {
    return /^-?\d+$/.test(value) && Number.isSafeInteger(Number(value));
  }

  function validationMessage(control, form) {
    if (!control || control.disabled) return "";
    const value = String(control.value ?? "").trim();
    const required = control.required ||
      ["session_timeout", "temporary_lock_duration"].includes(control.name) ||
      (form.id === "backupScheduleForm" &&
        normalizeValue(getControl(form, "backup_schedule_enabled")) === "1" &&
        ["backup_schedule_frequency", "backup_schedule_time"].includes(control.name)) ||
      (form.id === "backupScheduleForm" &&
        normalizeValue(getControl(form, "backup_schedule_enabled")) === "1" &&
        normalizeValue(getControl(form, "backup_schedule_frequency")) === "weekly" &&
        control.name === "backup_schedule_day");
    const enabledSchedule = form.id === "backupScheduleForm" &&
      normalizeValue(getControl(form, "backup_schedule_enabled")) === "1";

    if (control.type === "number" && control.validity?.badInput && value === "") {
      return "Enter a whole number.";
    }
    if (required && value === "") return "This field is required.";

    if (control.type === "number" && value !== "") {
      if (!isInteger(value)) return "Enter a whole number.";
      const number = Number(value);
      if (control.min !== "" && number < Number(control.min)) {
        return `Enter ${control.min} or more.`;
      }
      if (control.max !== "" && number > Number(control.max)) {
        return `Enter ${control.max} or less.`;
      }
    }

    if (control.name === "maximum_failed_login_attempts" && !["", "3", "5", "10"].includes(value)) {
      return "Choose a supported attempt limit.";
    }

    if (control.name === "backup_schedule_frequency" && enabledSchedule && !["daily", "weekly"].includes(value)) {
      return "Choose Daily or Weekly.";
    }

    if (control.name === "backup_schedule_time" && enabledSchedule && !/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(value)) {
      return "Choose a valid backup time.";
    }

    if (control.name === "backup_schedule_day" && enabledSchedule &&
        normalizeValue(getControl(form, "backup_schedule_frequency")) === "weekly" &&
        !["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"].includes(value)) {
      return "Choose a weekday for the weekly schedule.";
    }

    return "";
  }

  function showValidation(control, message) {
    const error = control.id
      ? document.getElementById(`${control.id}Error`)
      : null;
    control.setAttribute("aria-invalid", message ? "true" : "false");
    if (error) error.textContent = message;
  }

  function validateForm(form, revealAll = false) {
    let firstInvalid = null;
    Array.from(form.elements).forEach((control) => {
      if (!control.name || control.name === "csrf_token" || control.type === "submit") return;
      if (revealAll) touchedControls.add(control);
      const message = validationMessage(control, form);
      const shouldShow = touchedControls.has(control) || revealAll;
      showValidation(control, shouldShow ? message : "");
      if (message && !firstInvalid) firstInvalid = control;
    });
    return firstInvalid;
  }

  function updateSaveButton(form) {
    const button = form.querySelector('button[type="submit"]');
    if (!button) return;
    const invalid = Array.from(form.elements).some(
      (control) => control.name && control.name !== "csrf_token" && validationMessage(control, form)
    );
    button.disabled = changedValues(form).length === 0 || invalid;
    button.setAttribute("aria-disabled", button.disabled ? "true" : "false");
    button.title = button.disabled
      ? invalid ? "Correct the highlighted fields before saving." : "No unsaved changes."
      : "Save changed settings.";
  }

  function labelFor(form, name) {
    const control = getControl(form, name);
    if (control?.id) {
      const label = form.querySelector(`label[for="${CSS.escape(control.id)}"]`);
      if (label) return label.textContent.trim();
    }
    return name.replaceAll("_", " ").replace(/^./, (letter) => letter.toUpperCase());
  }

  function displayValue(form, name, value) {
    const control = getControl(form, name);
    if (!control) return value || "Not configured";
    let text = value;
    if (control.tagName === "SELECT") {
      const option = Array.from(control.options).find((item) => item.value === value);
      text = option?.textContent.trim() || (value || "Not configured");
    } else if (value === "") {
      text = "Not configured";
    }

    const addon = control.closest(".settings-input-group")?.querySelector(".settings-addon");
    if (addon && value !== "") {
      const unit = addon.textContent.trim().toLowerCase();
      text = `${text} ${unit === "mins" ? "minutes" : unit.toLowerCase()}`;
    }
    return text;
  }

  function renderReview(form) {
    if (!reviewChanges || !reviewTitle) return;
    reviewTitle.textContent = form.dataset.confirmationTitle || "Review changes";
    reviewChanges.replaceChildren();

    changedValues(form).forEach(({ name, before, after }) => {
      const row = document.createElement("div");
      row.className = "settings-review-change";
      const term = document.createElement("dt");
      term.textContent = labelFor(form, name);
      const description = document.createElement("dd");
      const previous = document.createElement("span");
      previous.textContent = displayValue(form, name, before);
      const arrow = document.createElement("span");
      arrow.className = "settings-review-arrow";
      arrow.setAttribute("aria-hidden", "true");
      arrow.textContent = "→";
      const next = document.createElement("strong");
      next.textContent = displayValue(form, name, after);
      description.append(previous, arrow, next);
      row.append(term, description);
      reviewChanges.append(row);
    });
  }

  function initializeForm(form) {
    form.noValidate = true;
    baselines.set(form, effectiveValues(form));

    form.addEventListener("input", (event) => {
      if (event.target instanceof HTMLElement && event.target.name) {
        touchedControls.add(event.target);
        showValidation(event.target, validationMessage(event.target, form));
      }
      updateSaveButton(form);
    });

    form.addEventListener("change", (event) => {
      if (event.target instanceof HTMLElement && event.target.name) {
        touchedControls.add(event.target);
        showValidation(event.target, validationMessage(event.target, form));
      }
      updateSaveButton(form);
    });

    form.addEventListener("blur", (event) => {
      if (event.target instanceof HTMLElement && event.target.name) {
        touchedControls.add(event.target);
        showValidation(event.target, validationMessage(event.target, form));
        updateSaveButton(form);
      }
    }, true);

    form.addEventListener("submit", (event) => {
      if (form.dataset.confirmedSubmit === "true") {
        delete form.dataset.confirmedSubmit;
        confirmedSubmitInProgress = true;
        const button = form.querySelector('button[type="submit"]');
        if (button) {
          button.disabled = true;
          button.querySelector("span").textContent = "Saving…";
        }
        return;
      }

      event.preventDefault();
      const invalid = validateForm(form, true);
      updateSaveButton(form);
      if (invalid) {
        invalid.focus();
        return;
      }
      if (changedValues(form).length === 0 || !reviewModal) return;

      pendingForm = form;
      renderReview(form);
      reviewModal.show();
    });

    updateSaveButton(form);
  }

  // Keep the existing schedule dependencies in sync before the initial dirty
  // snapshot and whenever their controlling values change.
  const backupScheduleForm = document.getElementById("backupScheduleForm");
  const backupScheduleEnabled = document.getElementById("backupScheduleEnabled");
  const backupScheduleFrequency = document.getElementById("backupScheduleFrequency");
  const backupScheduleTime = document.getElementById("backupScheduleTime");
  const backupScheduleDay = document.getElementById("backupScheduleDay");
  const backupScheduleDayContainer = document.getElementById("backupScheduleDayContainer");

  function updateBackupScheduleControls() {
    if (!backupScheduleEnabled || !backupScheduleFrequency || !backupScheduleTime || !backupScheduleDay || !backupScheduleDayContainer) return;
    const isEnabled = backupScheduleEnabled.value === "1";
    const isWeekly = backupScheduleFrequency.value === "weekly";
    backupScheduleFrequency.disabled = !isEnabled;
    backupScheduleTime.disabled = !isEnabled;
    backupScheduleDayContainer.hidden = !isEnabled || !isWeekly;
    backupScheduleDay.disabled = !isEnabled || !isWeekly;
  }

  backupScheduleEnabled?.addEventListener("change", updateBackupScheduleControls);
  backupScheduleFrequency?.addEventListener("change", updateBackupScheduleControls);
  updateBackupScheduleControls();
  settingsForms.forEach(initializeForm);

  confirmButton?.addEventListener("click", () => {
    if (!pendingForm) return;
    const form = pendingForm;
    pendingForm = null;
    form.dataset.confirmedSubmit = "true";
    reviewModal?.hide();
    const saveButton = form.querySelector('button[type="submit"]');
    if (typeof form.requestSubmit === "function") {
      form.requestSubmit(saveButton || undefined);
    } else {
      form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
    }
  });

  reviewModalElement?.addEventListener("hidden.bs.modal", () => {
    pendingForm = null;
    if (!confirmedSubmitInProgress) {
      settingsForms.forEach(updateSaveButton);
    }
  });

  // Preserve the page's existing leave warning, but base it on real section
  // changes so reverting edits restores the clean state.
  window.addEventListener("beforeunload", (event) => {
    if (confirmedSubmitInProgress || !settingsForms.some((form) => changedValues(form).length > 0)) return;
    event.preventDefault();
    event.returnValue = "";
  });

  // Restore selected settings tab.
  const requestedTab = window.location.hash;
  if (requestedTab) {
    const tabButton = document.querySelector(`button[data-bs-target="${CSS.escape(requestedTab)}"]`);
    if (tabButton && window.bootstrap?.Tab) {
      bootstrap.Tab.getOrCreateInstance(tabButton).show();
    }
  }

  document.querySelectorAll('button[data-bs-toggle="pill"]').forEach((tab) => {
    tab.addEventListener("shown.bs.tab", () => window.lucide?.createIcons());
  });

  // Existing Settings flash messages use APRISM's shared toast surface and
  // shared responsive placement owner.
  const toastContainer = document.getElementById("toastContainer");
  function showToast(title, text, type = "success") {
    if (!toastContainer || !text) return;
    const toast = document.createElement("div");
    toast.className = "toast-custom";
    const icon = type === "warning" ? "alert-circle" : type === "info" ? "info" : "check";
    const iconWrap = document.createElement("div");
    iconWrap.className = `toast-icon ${type}`;
    const iconElement = document.createElement("i");
    iconElement.setAttribute("data-lucide", icon);
    iconWrap.append(iconElement);
    const content = document.createElement("div");
    content.className = "toast-content";
    const heading = document.createElement("h5");
    heading.className = "toast-title";
    heading.textContent = title;
    const message = document.createElement("p");
    message.className = "toast-text";
    message.textContent = text;
    content.append(heading, message);
    toast.append(iconWrap, content);
    toastContainer.append(toast);
    window.lucide?.createIcons();
    window.setTimeout(() => toast.classList.add("show"), 10);
    window.setTimeout(() => {
      toast.classList.remove("show");
      window.setTimeout(() => toast.remove(), 300);
    }, 4500);
  }

  if (toastContainer) {
    if (toastContainer.dataset.successMessage) showToast("Success", toastContainer.dataset.successMessage, "success");
    if (toastContainer.dataset.errorMessage) showToast("Error", toastContainer.dataset.errorMessage, "warning");
    if (toastContainer.dataset.warningMessage) showToast("Warning", toastContainer.dataset.warningMessage, "warning");
  }

  window.lucide?.createIcons();
});
