// Shared APRISM sidebar

const sidebar = document.getElementById("sidebar");
const mainContent = document.querySelector(".main-content");
const sidebarToggle = document.getElementById("sidebarToggle");
const menuToggle = document.getElementById("menuToggle");
const aprismSidebarNavigation = document.querySelector(".sidebar-navigation");
const sidebarScrollStorageKey = "aprism.sidebar.scrollTop";

// Disable transitions during initial page load
document.documentElement.classList.add("preload");

// Preserve only the shared sidebar's position across normal page navigation.
// The value is UI state and is intentionally kept out of the server session.
const persistSidebarScroll = () => {
  if (!aprismSidebarNavigation) return;

  try {
    sessionStorage.setItem(
      sidebarScrollStorageKey,
      String(aprismSidebarNavigation.scrollTop)
    );
  } catch (error) {
    // Storage can be unavailable in restricted browser contexts.
  }
};

const restoreSidebarScroll = () => {
  if (!aprismSidebarNavigation) return;

  let storedScrollTop = null;
  try {
    storedScrollTop = Number(
      sessionStorage.getItem(sidebarScrollStorageKey)
    );
  } catch (error) {
    return;
  }

  if (Number.isFinite(storedScrollTop) && storedScrollTop >= 0) {
    window.requestAnimationFrame(() => {
      aprismSidebarNavigation.scrollTop = storedScrollTop;
    });
    return;
  }

  const activeLink = aprismSidebarNavigation.querySelector(".sidebar-link.active");
  activeLink?.scrollIntoView({ block: "nearest" });
};

aprismSidebarNavigation?.addEventListener("scroll", persistSidebarScroll, {
  passive: true,
});

aprismSidebarNavigation?.querySelectorAll(".sidebar-link:not(.disabled)").forEach((link) => {
  link.addEventListener("click", persistSidebarScroll);
});

window.addEventListener("beforeunload", persistSidebarScroll);
restoreSidebarScroll();

// Desktop sidebar
sidebarToggle?.addEventListener("click", () => {
  const collapsed = sidebar.classList.toggle("collapsed");

  mainContent?.classList.toggle("expanded", collapsed);
  sidebarToggle?.classList.toggle("rotated", collapsed);
  sidebarToggle?.setAttribute("aria-expanded", String(!collapsed));

  fetch(`${window.APP_URL}/actions/system/save_sidebar_state.php`, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams({
      csrf_token: document.querySelector('meta[name="aprism-csrf-token"]')?.content || "",
      collapsed: String(collapsed),
    }),
  });
});

// Shared mobile navigation drawer.
const mobileSidebarQuery = window.matchMedia("(max-width: 1200px)");

const isMobileSidebarOpen = () =>
  sidebar?.classList.contains("open") ||
  sidebar?.classList.contains("mobile-open") ||
  false;

const setMobileSidebarOpen = (
  isOpen,
  { returnFocus = false, focusFirstLink = false } = {}
) => {
  if (!sidebar || !menuToggle) return;

  if (!mobileSidebarQuery.matches) {
    sidebar.classList.remove("open", "mobile-open");
    document.body.classList.remove("sidebar-drawer-open");
    sidebar.inert = false;
    sidebar.removeAttribute("aria-hidden");
    menuToggle.setAttribute("aria-expanded", "false");
    return;
  }

  if (isOpen) {
    sidebar.classList.add("open");
    document.body.classList.add("sidebar-drawer-open");
    sidebar.inert = false;
    sidebar.removeAttribute("aria-hidden");
    menuToggle.setAttribute("aria-expanded", "true");
    if (focusFirstLink) {
      sidebar
        .querySelector('.sidebar-link:not(.disabled):not([aria-disabled="true"])')
        ?.focus({ preventScroll: true });
    }
    return;
  }

  const focusWasInDrawer = sidebar.contains(document.activeElement);
  sidebar.classList.remove("open", "mobile-open");
  document.body.classList.remove("sidebar-drawer-open");
  sidebar.inert = true;
  sidebar.setAttribute("aria-hidden", "true");
  menuToggle.setAttribute("aria-expanded", "false");

  if (returnFocus || focusWasInDrawer) {
    menuToggle.focus({ preventScroll: true });
  }
};

const syncMobileSidebar = () => {
  if (!sidebar || !menuToggle) return;

  if (mobileSidebarQuery.matches) {
    setMobileSidebarOpen(isMobileSidebarOpen());
    return;
  }

  sidebar.classList.remove("open", "mobile-open");
  document.body.classList.remove("sidebar-drawer-open");
  sidebar.inert = false;
  sidebar.removeAttribute("aria-hidden");
  menuToggle.setAttribute("aria-expanded", "false");
};

syncMobileSidebar();

menuToggle?.addEventListener("click", () => {
  if (!mobileSidebarQuery.matches) return;
  const isOpening = !isMobileSidebarOpen();
  setMobileSidebarOpen(isOpening, { focusFirstLink: isOpening });
});

document.addEventListener("click", (event) => {
  if (
    mobileSidebarQuery.matches &&
    isMobileSidebarOpen() &&
    sidebar &&
    menuToggle &&
    !sidebar.contains(event.target) &&
    !menuToggle.contains(event.target)
  ) {
    const outsideTarget = event.target instanceof Element ? event.target : null;
    const clickedFocusable = outsideTarget?.closest(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"]), [contenteditable="true"]'
    );
    setMobileSidebarOpen(false, { returnFocus: !clickedFocusable });
  }
});

document.addEventListener("keydown", (event) => {
  if (
    event.key === "Escape" &&
    mobileSidebarQuery.matches &&
    isMobileSidebarOpen()
  ) {
    event.preventDefault();
    setMobileSidebarOpen(false, { returnFocus: true });
  }
});

mobileSidebarQuery.addEventListener("change", syncMobileSidebar);
