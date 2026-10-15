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

  // Referral detail pages load this file for flash feedback only.
});
