const toggleSidebar = document.getElementById("toggleSidebar");
const closeSidebar = document.getElementById("closeSidebar");
const sidebar = document.getElementById("sidebar");
const overlay = document.getElementById("sidebarOverlay");
const sidebarLinks = document.querySelectorAll(".sidebar-link");

toggleSidebar.addEventListener("click", () => {
  if (sidebar.classList.contains('active')) {
    sidebar.classList.remove("active");
    overlay.classList.remove("active");
    toggleSidebar.classList.remove('open');
  } else {
    sidebar.classList.add("active");
    overlay.classList.add("active");
    toggleSidebar.classList.add('open');
  }
});

closeSidebar.addEventListener("click", () => {
  sidebar.classList.remove("active");
  overlay.classList.remove("active");
  toggleSidebar.classList.remove('open');
});

overlay.addEventListener("click", () => {
  sidebar.classList.remove("active");
  overlay.classList.remove("active");
});

sidebarLinks.forEach((link) => {
  link.addEventListener("click", () => {
    sidebar.classList.remove("active");
    overlay.classList.remove("active");
  });
});

const outcomeTabs = document.querySelectorAll(".outcome-tab");
const outcomePanels = document.querySelectorAll(".outcome-panel");

outcomeTabs.forEach((tab) => {
  tab.addEventListener("click", () => {
    const targetPanel = document.getElementById(tab.dataset.target);

    outcomeTabs.forEach((item) => {
      item.classList.remove("active");
      item.setAttribute("aria-selected", "false");
    });

    outcomePanels.forEach((panel) => {
      panel.classList.remove("active");
    });

    tab.classList.add("active");
    tab.setAttribute("aria-selected", "true");
    targetPanel.classList.add("active");
  });
});

window.addEventListener("scroll", () => {
  let current = "";
  const sections = document.querySelectorAll(".content-section");

  sections.forEach((section) => {
    const sectionTop = section.offsetTop;
    if (pageYOffset >= sectionTop - 100) {
      current = section.getAttribute("id");
    }
  });

  sidebarLinks.forEach((link) => {
    link.classList.remove("active");
    if (link.getAttribute("href").slice(1) === current) {
      link.classList.add("active");
    }
  });
});
