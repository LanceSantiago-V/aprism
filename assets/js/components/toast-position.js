(() => {
  "use strict";

  const mobileLayout = window.matchMedia("(max-width: 767.98px)");
  const tabletLayout = window.matchMedia("(min-width: 768px) and (max-width: 1023.98px)");
  let updateFrame = 0;

  function setPosition(container, position) {
    if (position) {
      if (container.dataset.toastPosition !== position) container.dataset.toastPosition = position;
    } else {
      container.removeAttribute("data-toast-position");
    }
  }

  function setBottomOffset(container, value) {
    if (value === null) {
      container.style.removeProperty("--aprism-toast-bottom-offset");
    } else if (container.style.getPropertyValue("--aprism-toast-bottom-offset") !== value) {
      container.style.setProperty("--aprism-toast-bottom-offset", value);
    }
  }

  function findBottomObstruction(container, mobile) {
    const viewportHeight = window.visualViewport?.height || window.innerHeight;
    const viewportWidth = document.documentElement.clientWidth;
    const toastWidth = Math.min(380, viewportWidth - (mobile ? 24 : 40));
    const horizontalStart = mobile ? (viewportWidth - toastWidth) / 2 : viewportWidth - toastWidth - 20;
    const horizontalEnd = mobile ? horizontalStart + toastWidth : viewportWidth - 20;
    let highestTop = viewportHeight;

    for (const element of document.body.querySelectorAll("*")) {
      if (element === container || container.contains(element) || element.closest(".toast-container-custom")) continue;

      const style = window.getComputedStyle(element);
      if (style.position !== "fixed" && style.position !== "sticky") continue;
      if (style.display === "none" || style.visibility === "hidden" || Number(style.opacity) === 0) continue;

      const rect = element.getBoundingClientRect();
      const overlapsToast = rect.left < horizontalEnd && rect.right > horizontalStart;
      const touchesBottom = rect.bottom >= viewportHeight - 24 && rect.top < viewportHeight;
      const reasonableHeight = rect.height >= 28 && rect.height < viewportHeight * 0.65;

      if (overlapsToast && touchesBottom && reasonableHeight) highestTop = Math.min(highestTop, rect.top);
    }

    return highestTop < viewportHeight ? highestTop : null;
  }

  function updateContainer(container) {
    const hasToast = container.matches(".toast-custom") || container.querySelector(".toast-custom");
    if (!hasToast) {
      setPosition(container, null);
      setBottomOffset(container, null);
      return;
    }

    const mobile = mobileLayout.matches;
    const tablet = tabletLayout.matches;
    const visualViewport = window.visualViewport;
    const keyboardLikelyOpen = (mobile || tablet) && visualViewport &&
      window.innerHeight - visualViewport.height > 120;
    const obstructionTop = keyboardLikelyOpen ? 0 : findBottomObstruction(container, mobile);

    if ((mobile || tablet) && (keyboardLikelyOpen || obstructionTop !== null)) {
      setPosition(container, "top-center");
      setBottomOffset(container, null);
      return;
    }

    setPosition(container, null);
    if (!mobile && obstructionTop !== null) {
      const viewportHeight = visualViewport?.height || window.innerHeight;
      const bottomOffset = `${Math.max(20, viewportHeight - obstructionTop + 12)}px`;
      setBottomOffset(container, bottomOffset);
    } else {
      setBottomOffset(container, null);
    }
  }

  function scheduleUpdate() {
    if (updateFrame) return;
    updateFrame = window.requestAnimationFrame(() => {
      updateFrame = 0;
      document.querySelectorAll(".toast-container-custom").forEach(updateContainer);
    });
  }

  function animateDirectRemoval(record) {
    const parent = record.target;
    for (const node of record.removedNodes) {
      if (!(node instanceof HTMLElement) || !node.matches(".toast-custom.show") || node.classList.contains("toast-leaving")) continue;
      if (!(parent instanceof HTMLElement) || !parent.matches(".toast-container-custom") || !parent.isConnected) continue;

      node.setAttribute("aria-hidden", "true");
      parent.insertBefore(node, record.nextSibling?.parentNode === parent ? record.nextSibling : null);
      void node.offsetWidth;
      node.classList.add("toast-leaving");
      window.setTimeout(() => node.remove(), 180);
    }
  }

  const observer = new MutationObserver((records) => {
    for (const record of records) {
      if (record.type === "childList") animateDirectRemoval(record);
    }
    scheduleUpdate();
  });

  function start() {
    observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ["class", "style", "hidden", "aria-hidden"]
    });

    window.addEventListener("resize", scheduleUpdate, { passive: true });
    window.addEventListener("scroll", scheduleUpdate, { passive: true, capture: true });
    window.visualViewport?.addEventListener("resize", scheduleUpdate, { passive: true });
    window.visualViewport?.addEventListener("scroll", scheduleUpdate, { passive: true });
    mobileLayout.addEventListener?.("change", scheduleUpdate);
    tabletLayout.addEventListener?.("change", scheduleUpdate);
    scheduleUpdate();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start, { once: true });
  } else {
    start();
  }
})();
