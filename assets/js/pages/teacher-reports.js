document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-report-print]").forEach((button) => {
    button.addEventListener("click", () => window.print());
  });
});
