// Technical Admin sidebar

const sidebar = document.getElementById("sidebar");
const mainContent = document.querySelector(".main-content");
const sidebarToggle = document.getElementById("sidebarToggle");
const menuToggle = document.getElementById("menuToggle");

// Disable transitions during initial page load
document.documentElement.classList.add("preload");

// Desktop sidebar
sidebarToggle?.addEventListener("click", () => {
  const collapsed = sidebar.classList.toggle("collapsed");

  mainContent?.classList.toggle("expanded", collapsed);
  sidebarToggle?.classList.toggle("rotated", collapsed);

  fetch(`${window.APP_URL}/actions/system/save_sidebar_state.php`, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `collapsed=${collapsed}`,
  });
});

// Mobile sidebar
menuToggle?.addEventListener("click", () => {
  sidebar?.classList.toggle("open");
});

// Close mobile sidebar when clicking outside
document.addEventListener("click", (e) => {
  if (
    window.innerWidth <= 1200 &&
    sidebar &&
    menuToggle &&
    !sidebar.contains(e.target) &&
    !menuToggle.contains(e.target)
  ) {
    sidebar.classList.remove("open");
  }
});

// Dashboard cards
document.querySelectorAll(".dashboard-nav-card").forEach((card) => {
  card.addEventListener("click", () => {
    const url = card.dataset.url;
    if (url) {
      window.location.href = url;
    }
  });
});
