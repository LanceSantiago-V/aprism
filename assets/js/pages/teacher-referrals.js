document.addEventListener("DOMContentLoaded", () => {
  const flash = window.APRISM_REFERRAL_FLASH || {};
  const container = document.getElementById("toastContainer");
  const message = flash.error || flash.success;
  if (container && message) {
    const toast = document.createElement("div");
    toast.className = "toast-custom";
    toast.innerHTML = `<div class="toast-icon ${flash.error ? "warning" : "success"}"><i data-lucide="${flash.error ? "triangle-alert" : "circle-check"}"></i></div><div class="toast-content"><h5 class="toast-title">${flash.error ? "Referral not saved" : "Referral submitted"}</h5><p class="toast-text"></p></div>`;
    toast.querySelector(".toast-text").textContent = message;
    container.appendChild(toast);
    if (window.lucide?.createIcons) window.lucide.createIcons();
    setTimeout(() => toast.classList.add("show"), 10);
    setTimeout(() => {
      toast.classList.remove("show");
      setTimeout(() => toast.remove(), 240);
    }, 4800);
  }
  const classSelect = document.querySelector("[data-referral-class]");
  if (!classSelect) return;
  const studentSelect = document.querySelector("[data-referral-student]");
  const studentContext = document.querySelector(
    "[data-referral-student-context]",
  );
  const referralForm = document.querySelector("[data-referral-form]");

  const syncStudentContext = () => {
    const option = studentSelect?.selectedOptions?.[0];
    const name = option?.dataset.studentName || "";
    const number = option?.dataset.studentNumber || "";
    if (studentContext) {
      studentContext.textContent =
        name && number ? `Selected: ${name} · ${number}` : "";
      studentContext.hidden = !(name && number);
    }
  };

  studentSelect?.addEventListener("change", syncStudentContext);
  syncStudentContext();

  referralForm?.addEventListener("submit", () => {
    const submitButton = referralForm.querySelector('button[type="submit"]');
    if (submitButton && !submitButton.disabled) {
      submitButton.disabled = true;
      submitButton.setAttribute("aria-disabled", "true");
    }
  });

  classSelect.addEventListener("change", () => {
    const url = new URL(window.location.href);
    if (classSelect.value) {
      url.searchParams.set("class_id", classSelect.value);
    } else {
      url.searchParams.delete("class_id");
    }
    window.location.assign(url.toString());
  });
});
