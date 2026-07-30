// Technical Admin sidebar

const sidebar = document.getElementById("sidebar");
const mainContent = document.querySelector(".main-content");
const sidebarToggle = document.getElementById("sidebarToggle");
const menuToggle = document.getElementById("menuToggle");

// Restore sidebar state
const sidebarCollapsed =
  localStorage.getItem("technicalAdminSidebarCollapsed") === "true";

if (sidebarCollapsed) {
  sidebar?.classList.add("collapsed");
  mainContent?.classList.add("expanded");
  sidebarToggle?.classList.add("rotated");
}

// Mobile sidebar
menuToggle?.addEventListener("click", () => {
  sidebar?.classList.toggle("open");
});

// Close mobile sidebar when clicking outside
document.addEventListener("click", (e) => {
  if (window.innerWidth <= 1200) {
    if (
      sidebar &&
      menuToggle &&
      !sidebar.contains(e.target) &&
      !menuToggle.contains(e.target) &&
      sidebar.classList.contains("open")
    ) {
      sidebar.classList.remove("open");
    }
  }
});

// Desktop sidebar
sidebarToggle?.addEventListener("click", () => {
  sidebar?.classList.toggle("collapsed");
  mainContent?.classList.toggle("expanded");
  sidebarToggle.classList.toggle("rotated");

  const isCollapsed = sidebar?.classList.contains("collapsed");

  localStorage.setItem("technicalAdminSidebarCollapsed", isCollapsed);

  document.documentElement.classList.toggle("sidebar-collapsed", isCollapsed);
});

/* Dashboard Navigation Cards */

document.querySelectorAll(".dashboard-nav-card").forEach((card) => {
  card.addEventListener("click", function () {
    const url = this.dataset.url;

    if (url) {
      window.location.href = url;
    }
  });
});
