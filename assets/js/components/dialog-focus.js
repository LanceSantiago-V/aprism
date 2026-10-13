(() => {
  "use strict";

  const marker = "[data-aprism-dialog-focus]";
  const stateByRoot = new WeakMap();
  const focusableSelector =
    'a[href], area[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), iframe, summary, [contenteditable="true"], [tabindex]:not([tabindex="-1"])';

  function dialogFor(root) {
    return root.matches('[role="dialog"][aria-modal="true"]')
      ? root
      : root.querySelector('[role="dialog"][aria-modal="true"]');
  }

  function isOpen(root) {
    const ariaHidden = root.getAttribute("aria-hidden");
    return ariaHidden === null ? !root.hidden : ariaHidden === "false";
  }

  function visibleFocusableElements(root) {
    const dialog = dialogFor(root);
    if (!dialog) return [];

    return Array.from(dialog.querySelectorAll(focusableSelector)).filter(
      (element) => !element.hidden && element.getClientRects().length > 0
    );
  }

  function focusInitialTarget(root) {
    const dialog = dialogFor(root);
    if (!dialog) return;

    const target = dialog.querySelector(
      "[data-aprism-dialog-initial-focus], h1, h2, h3, [autofocus]"
    );
    if (!target) return;

    const naturallyFocusable = target.matches(
      'a[href], area[href], button, input:not([type="hidden"]), select, textarea, iframe, summary, audio[controls], video[controls], [contenteditable="true"]'
    );
    if (!naturallyFocusable && !target.hasAttribute("tabindex")) {
      target.setAttribute("tabindex", "-1");
    }

    target.focus({ preventScroll: true });
  }

  function updateRoot(root) {
    let state = stateByRoot.get(root);
    if (!state) {
      state = { open: isOpen(root), returnFocus: null };
      stateByRoot.set(root, state);
      return;
    }

    const nextOpen = isOpen(root);
    if (state.open === nextOpen) return;
    state.open = nextOpen;

    if (nextOpen) {
      state.returnFocus = document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null;
      window.setTimeout(() => {
        if (isOpen(root)) focusInitialTarget(root);
      }, 0);
      return;
    }

    const returnFocusTarget = state.returnFocus;
    state.returnFocus = null;
    if (returnFocusTarget?.isConnected) {
      window.setTimeout(() => returnFocusTarget.focus({ preventScroll: true }), 0);
    }
  }

  function syncRoots(scope) {
    if (scope instanceof Element && scope.matches(marker)) updateRoot(scope);
    scope.querySelectorAll?.(marker).forEach(updateRoot);
  }

  document.querySelectorAll(marker).forEach(updateRoot);
  document.addEventListener("shown.bs.modal", (event) => {
    const root = event.target;
    if (root instanceof Element && root.matches(marker)) {
      focusInitialTarget(root);
    }
  });

  new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      if (mutation.type === "attributes") {
        const root = mutation.target.closest?.(marker);
        if (root) updateRoot(root);
      } else {
        syncRoots(mutation.target);
      }
    }
  }).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ["aria-hidden", "hidden"],
    childList: true,
    subtree: true
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Tab") return;

    const openRoots = Array.from(document.querySelectorAll(marker)).filter(isOpen);
    const root = openRoots[openRoots.length - 1];
    if (!root) return;

    const focusableElements = visibleFocusableElements(root);
    const dialog = dialogFor(root);
    if (focusableElements.length === 0) {
      event.preventDefault();
      dialog?.focus({ preventScroll: true });
      return;
    }

    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];
    const activeIndex = focusableElements.indexOf(document.activeElement);

    if (activeIndex === -1) {
      event.preventDefault();
      (event.shiftKey ? lastElement : firstElement).focus();
    } else if (event.shiftKey && activeIndex === 0) {
      event.preventDefault();
      lastElement.focus();
    } else if (!event.shiftKey && activeIndex === focusableElements.length - 1) {
      event.preventDefault();
      firstElement.focus();
    }
  });
})();
