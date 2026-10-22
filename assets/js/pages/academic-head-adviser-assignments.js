(() => {
  "use strict";

  const renderToast = (container, type, title, message) => {
    if (!message) return;

    const toast = document.createElement("div");
    toast.className = "toast-custom";

    const iconWrap = document.createElement("div");
    iconWrap.className = `toast-icon ${type}`;
    const icon = document.createElement("i");
    icon.setAttribute("data-lucide", type === "success" ? "circle-check" : "triangle-alert");
    icon.setAttribute("aria-hidden", "true");
    iconWrap.append(icon);

    const content = document.createElement("div");
    content.className = "toast-content";
    const heading = document.createElement("h5");
    heading.className = "toast-title";
    heading.textContent = title;
    const body = document.createElement("p");
    body.className = "toast-text";
    body.textContent = message;
    content.append(heading, body);
    toast.append(iconWrap, content);
    container.append(toast);

    window.lucide?.createIcons({ root: toast });
    window.requestAnimationFrame(() => toast.classList.add("show"));
    window.setTimeout(() => {
      toast.classList.remove("show");
      window.setTimeout(() => toast.remove(), 240);
    }, 5000);
  };

  document.addEventListener("DOMContentLoaded", () => {
    const toastContainer = document.getElementById("toastContainer");
    if (toastContainer) {
      const warning = toastContainer.dataset.warningMessage || "";
      const error = toastContainer.dataset.errorMessage || "";
      const success = toastContainer.dataset.successMessage || "";
      if (warning) {
        renderToast(toastContainer, "warning", "Assignment saved", warning);
      } else if (error) {
        renderToast(toastContainer, "warning", "Assignment not saved", error);
      } else if (success) {
        renderToast(toastContainer, "success", "Assignment saved", success);
      }
    }

    const form = document.getElementById("adviserAssignmentForm");
    if (!form) return;

    const teacher = form.querySelector("#assignmentTeacher");
    const schoolYear = form.querySelector("#assignmentSchoolYear");
    const context = form.querySelector("#assignmentContext");
    const section = form.querySelector("#assignmentSection");
    const summary = form.querySelector("#assignmentReviewSummary");
    const conflictNotice = form.querySelector("#assignmentConflict");
    const submitButton = form.querySelector("#confirmAdviserAssignment");
    const editingId = Number(form.dataset.editAssignmentId || 0);
    const activeSchoolYearId = Number(form.dataset.activeSchoolYearId || 0);
    const currentShsContext = form.dataset.currentShsContext || "";

    let activeAssignments = [];
    try {
      activeAssignments = JSON.parse(form.dataset.activeAssignments || "[]");
    } catch (error) {
      activeAssignments = [];
    }

    const selectedLabel = (select) => select?.selectedOptions?.[0]?.textContent?.trim() || "";

    const refreshReview = () => {
      const teacherId = Number(teacher?.value || 0);
      const schoolYearId = Number(schoolYear?.value || 0);
      const contextValue = context?.value || "";
      const sectionId = Number(section?.value || 0);
      const complete = Boolean(teacherId && schoolYearId && contextValue && sectionId);
      let conflict = null;

      if (complete) {
        conflict = activeAssignments.find((assignment) => {
          if (Number(assignment.adviser_assignment_id) === editingId) return false;
          if (Number(assignment.school_year_id) !== schoolYearId) return false;
          if (assignment.teaching_context !== contextValue) return false;
          return Number(assignment.teacher_id) === teacherId
            || Number(assignment.section_id) === sectionId;
        }) || null;
      }

      if (complete) {
        summary.textContent = [
          selectedLabel(teacher),
          selectedLabel(section),
          contextValue,
          selectedLabel(schoolYear),
        ].join(" · ");
      } else {
        summary.textContent = "Select a Teacher, School Year, teaching context, and Section.";
      }

      if (conflict) {
        const isTeacherConflict = Number(conflict.teacher_id) === teacherId;
        conflictNotice.textContent = isTeacherConflict
          ? `This Teacher is already assigned to ${conflict.section_name} for ${contextValue}. Use Change or End in the records below.`
          : `${conflict.section_name} already has an Adviser for ${contextValue}. Use Change or End in the records below.`;
        conflictNotice.hidden = false;
      } else {
        conflictNotice.textContent = "";
        conflictNotice.hidden = true;
      }

      if (submitButton) submitButton.disabled = !complete || Boolean(conflict);
    };

    [teacher, schoolYear, context, section].forEach((control) => {
      control?.addEventListener("change", refreshReview);
    });
    schoolYear?.addEventListener("change", () => {
      if (editingId) return;
      context.value = Number(schoolYear.value) === activeSchoolYearId ? currentShsContext : "";
      refreshReview();
    });
    refreshReview();

    const fieldError = form.querySelector(".assignment-field-error[role='alert']");
    if (fieldError) {
      const field = fieldError.closest("label")?.querySelector("select, input");
      field?.focus({ preventScroll: true });
    }
  });
})();
