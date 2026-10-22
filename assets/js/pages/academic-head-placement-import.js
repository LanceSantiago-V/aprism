document.addEventListener("DOMContentLoaded", () => {
  const container = document.getElementById("placementImportToast");
  const message = container?.dataset.warningMessage?.trim();
  if (!container || !message) return;

  const toast = document.createElement("div");
  toast.className = "toast-custom";

  const iconWrap = document.createElement("div");
  iconWrap.className = "toast-icon warning";
  const icon = document.createElement("i");
  icon.setAttribute("data-lucide", "alert-circle");
  icon.setAttribute("aria-hidden", "true");
  iconWrap.append(icon);

  const content = document.createElement("div");
  content.className = "toast-content";
  const title = document.createElement("h5");
  title.className = "toast-title";
  title.textContent = "Placement import completed";
  const text = document.createElement("p");
  text.className = "toast-text";
  text.textContent = message;
  content.append(title, text);

  toast.append(iconWrap, content);
  container.append(toast);
  window.lucide?.createIcons({ root: toast });
  window.setTimeout(() => toast.classList.add("show"), 10);
  window.setTimeout(() => {
    toast.classList.remove("show");
    window.setTimeout(() => toast.remove(), 240);
  }, 6000);
});
