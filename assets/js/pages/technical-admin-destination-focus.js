(() => {
  const target = document.querySelector(".destination-focus-target");
  if (!target) return;

  window.requestAnimationFrame(() => {
    const behavior = window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth";
    target.scrollIntoView({ behavior, block: "center", inline: "nearest" });
    target.focus({ preventScroll: true });
    target.classList.add("destination-focus-active");
    window.setTimeout(() => {
      target.classList.remove("destination-focus-active");
      target.classList.remove("destination-focus-target");
      target.removeAttribute("tabindex");
    }, 1900);
  });
})();
