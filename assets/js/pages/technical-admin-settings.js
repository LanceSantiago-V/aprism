document.addEventListener("DOMContentLoaded", function () {
  // Prevent repeated system configuration submissions
  const systemConfigurationForm = document.getElementById(
    "systemConfigurationForm",
  );
  const saveSystemConfigurationBtn = document.getElementById(
    "saveSystemConfigurationBtn",
  );

  systemConfigurationForm?.addEventListener("submit", function () {
    if (!saveSystemConfigurationBtn) return;

    saveSystemConfigurationBtn.disabled = true;
    saveSystemConfigurationBtn.innerHTML = `
      <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
      <span>Saving...</span>
    `;
  });

  // Prevent repeated security form submissions
  const securitySettingsForm = document.getElementById("securitySettingsForm");
  const saveSecuritySettingsBtn = document.getElementById(
    "saveSecuritySettingsBtn",
  );

  securitySettingsForm?.addEventListener("submit", function () {
    if (!saveSecuritySettingsBtn) return;

    saveSecuritySettingsBtn.disabled = true;
    saveSecuritySettingsBtn.innerHTML = `
      <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
      <span>Saving...</span>
    `;
  });

  // Prevent repeated retention form submissions
  const backupRetentionForm = document.getElementById("backupRetentionForm");
  const saveBackupRetentionBtn = document.getElementById(
    "saveBackupRetentionBtn",
  );

  backupRetentionForm?.addEventListener("submit", function () {
    if (!saveBackupRetentionBtn) return;

    saveBackupRetentionBtn.disabled = true;
    saveBackupRetentionBtn.innerHTML = `
      <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
      <span>Saving...</span>
    `;
  });

  // Prevent repeated schedule form submissions
  const backupScheduleForm = document.getElementById("backupScheduleForm");
  const saveBackupScheduleBtn = document.getElementById(
    "saveBackupScheduleBtn",
  );

  backupScheduleForm?.addEventListener("submit", function () {
    if (!saveBackupScheduleBtn) return;

    saveBackupScheduleBtn.disabled = true;
    saveBackupScheduleBtn.innerHTML = `
      <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
      <span>Saving...</span>
    `;
  });

  // Restore selected settings tab
  const settingsTabs = document.querySelectorAll(
    'button[data-bs-toggle="pill"]',
  );

  const requestedTab = window.location.hash;

  if (requestedTab) {
    const tabButton = document.querySelector(
      'button[data-bs-target="' + requestedTab + '"]',
    );

    if (tabButton) {
      const bootstrapTab = bootstrap.Tab.getOrCreateInstance(tabButton);

      bootstrapTab.show();
    }
  }

  settingsTabs.forEach(function (tab) {
    tab.addEventListener("shown.bs.tab", function () {
      lucide.createIcons();
    });
  });

  // Automatic backup schedule controls
  const backupScheduleEnabled = document.getElementById(
    "backupScheduleEnabled",
  );
  const backupScheduleFrequency = document.getElementById(
    "backupScheduleFrequency",
  );
  const backupScheduleTime = document.getElementById("backupScheduleTime");
  const backupScheduleDay = document.getElementById("backupScheduleDay");
  const backupScheduleDayContainer = document.getElementById(
    "backupScheduleDayContainer",
  );

  function updateBackupScheduleControls() {
    if (
      !backupScheduleEnabled ||
      !backupScheduleFrequency ||
      !backupScheduleTime ||
      !backupScheduleDay ||
      !backupScheduleDayContainer
    ) {
      return;
    }

    const isEnabled = backupScheduleEnabled.value === "1";

    const isWeekly = backupScheduleFrequency.value === "weekly";

    backupScheduleFrequency.disabled = !isEnabled;
    backupScheduleTime.disabled = !isEnabled;

    backupScheduleDayContainer.hidden = !isEnabled || !isWeekly;

    backupScheduleDay.disabled = !isEnabled || !isWeekly;
  }

  backupScheduleEnabled?.addEventListener(
    "change",
    updateBackupScheduleControls,
  );

  backupScheduleFrequency?.addEventListener(
    "change",
    updateBackupScheduleControls,
  );

  updateBackupScheduleControls();

  // Warn before leaving with unsaved changes
  let hasUnsavedChanges = false;

  const settingsForms = document.querySelectorAll("form");

  settingsForms.forEach((form) => {
    form.addEventListener("input", () => {
      hasUnsavedChanges = true;
    });

    form.addEventListener("change", () => {
      hasUnsavedChanges = true;
    });

    form.addEventListener("submit", () => {
      hasUnsavedChanges = false;
    });
  });

  window.addEventListener("beforeunload", (event) => {
    if (!hasUnsavedChanges) {
      return;
    }

    event.preventDefault();
    event.returnValue = "";
  });

  // Toast notifications
  const toastContainer = document.getElementById("toastContainer");

  function showToast(title, text, type = "success") {
    if (!toastContainer || !text) return;

    const toast = document.createElement("div");
    toast.className = "toast-custom";

    let icon = "check";

    if (type === "warning") {
      icon = "alert-circle";
    }

    if (type === "info") {
      icon = "info";
    }

    toast.innerHTML = `
    <div class="toast-icon ${type}">
      <i data-lucide="${icon}"></i>
    </div>

    <div class="toast-content">
      <h5 class="toast-title">${title}</h5>
      <p class="toast-text"></p>
    </div>
  `;

    const toastText = toast.querySelector(".toast-text");

    if (toastText) {
      toastText.textContent = text;
    }

    toastContainer.appendChild(toast);

    lucide.createIcons();

    setTimeout(() => {
      toast.classList.add("show");
    }, 10);

    setTimeout(() => {
      toast.classList.remove("show");

      setTimeout(() => {
        toast.remove();
      }, 300);
    }, 4500);
  }

  if (toastContainer) {
    const successMessage = toastContainer.dataset.successMessage;

    const errorMessage = toastContainer.dataset.errorMessage;

    const warningMessage = toastContainer.dataset.warningMessage;

    if (successMessage) {
      showToast("Success", successMessage, "success");
    }

    if (errorMessage) {
      showToast("Error", errorMessage, "warning");
    }

    if (warningMessage) {
      showToast("Warning", warningMessage, "warning");
    }
  }

  // Render icons
  lucide.createIcons();
});
