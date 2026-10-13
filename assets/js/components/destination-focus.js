(() => {
  "use strict";

  let cleanupTimer;
  let activeDestination;

  function clearDestinationFocus() {
    if (cleanupTimer) window.clearTimeout(cleanupTimer);
    cleanupTimer = undefined;

    if (!activeDestination) return;

    const { target, addedTabindex, focusWasAlreadyOnTarget } = activeDestination;
    target.classList.remove("aprism-destination-focus");
    if (
      !focusWasAlreadyOnTarget &&
      document.activeElement === target &&
      !target.matches(":focus-visible")
    ) {
      target.blur();
    }
    if (addedTabindex && target.getAttribute("tabindex") === "-1") {
      target.removeAttribute("tabindex");
    }
    activeDestination = undefined;
  }

  function focusDestination(isSamePageNavigation = false) {
    clearDestinationFocus();

    const rawFragment = window.location.hash.slice(1);
    if (!rawFragment) return;

    let id;
    try {
      id = decodeURIComponent(rawFragment);
    } catch {
      return;
    }

    const target = document.getElementById(id);
    if (!target || (!isSamePageNavigation && !target.hasAttribute("data-aprism-destination-focus"))) return;

    const naturallyFocusable = target.matches(
      'a[href], area[href], button, input:not([type="hidden"]), select, textarea, iframe, summary, audio[controls], video[controls], [contenteditable="true"]'
    );
    const addedTabindex = !naturallyFocusable && !target.hasAttribute("tabindex");
    if (addedTabindex) {
      target.setAttribute("tabindex", "-1");
    }

    target.classList.remove("aprism-destination-focus");
    target.scrollIntoView({
      behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth",
      block: "center",
      inline: "nearest"
    });
    const previousActiveElement = document.activeElement;
    const focusWasAlreadyOnTarget = previousActiveElement === target;
    target.focus({ preventScroll: true });
    target.classList.add("aprism-destination-focus");
    activeDestination = { target, addedTabindex, focusWasAlreadyOnTarget };

    cleanupTimer = window.setTimeout(() => {
      clearDestinationFocus();
    }, 1900);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => focusDestination(false), { once: true });
  } else {
    focusDestination();
  }

  window.addEventListener("hashchange", () => focusDestination(true));
})();
