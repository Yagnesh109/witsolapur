const DEPT_TITLES = {
  cse: "Computer Science & Engineering",
  ce: "Civil Engineering",
  entc: "Electronics & Telecommunication",
  ecs: "Electronics & Computer Engineering",
  me: "Mechanical and Automation Engineering",
  it: "Information Technology",
  aiml: "Artificial Intelligence & Machine Learning",
  ge: "General Engineering"
};

const DEPT_NAV_LINKS = [
  { label: "HOD Desk", emoji: "🧑‍💼", section: "hod-desk" },
  { label: "About Department", emoji: "💻", section: "about-department" },
  { label: "Vision & Mission", emoji: "👁️", section: "vision-mission" },
  { label: "PO · PSO · PEO", emoji: "📋", section: "po-pso-peo" },
  { label: "Faculty", emoji: "👨‍🏫", section: "faculty" },
  { label: "Infrastructure", emoji: "🏢", section: "infrastructure" },
  { label: "Syllabus", emoji: "📖", section: "syllabus" },
  { label: "Achievements", emoji: "🏆", section: "achievements" }
];

function ensureFontAwesome() {
  if (document.querySelector('link[href*="font-awesome"]')) return;

  const link = document.createElement("link");
  link.rel = "stylesheet";
  link.href = "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css";
  document.head.appendChild(link);
}

function buildSidebarMarkup(title) {
  const dept = document.body.dataset.department;
  let linksList = [...DEPT_NAV_LINKS];
  if (dept === "aiml") {
    linksList = linksList.filter((item) =>
      item.section === "hod-desk" ||
      item.section === "about-department" ||
      item.section === "vision-mission" ||
      item.section === "po-pso-peo" ||
      item.section === "faculty" ||
      item.section === "syllabus"
    );
  }
  if (dept === "ge") {
    linksList = linksList.filter((item) => item.section !== "po-pso-peo");
  }
  if (dept === "it" || dept === "ce") {
    linksList.push({ label: "Student Activity", emoji: "🏃", section: "student-activity" });
  }
  if (dept === "entc" || dept === "ecs" || dept === "ge") {
    linksList.push({ label: "Activities", emoji: "🏃", section: "activities" });
  }
  if (dept === "me") {
    linksList.push({ label: "Eminent Alumni", emoji: "🎓", section: "../../assets/documents/alumni/me/Eminent%20Alumni_Mech_Dept%20(1).pdf" });
  }
  const links = linksList.map((item) => {
    const isPdf = item.section.endsWith('.pdf');
    const href = isPdf ? item.section : `#${item.section}`;
    const targetAttr = isPdf ? ' target="_blank" rel="noopener"' : '';
    return `
      <a href="${href}" class="dept-sidebar__link sidebar-link" data-section="${item.section}"${targetAttr}>
        <span class="dept-sidebar__emoji" aria-hidden="true">${item.emoji}</span>
        <span class="dept-sidebar__label">${item.label}</span>
      </a>
    `;
  }).join("");

  return `
    <aside class="dept-sidebar" id="deptSidebar" aria-label="${title} navigation">
      <button class="dept-sidebar__collapse" id="collapseSidebar" type="button" aria-label="Collapse sidebar" aria-expanded="true">
        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
      </button>
      <nav class="dept-sidebar__nav">
        ${links}
      </nav>
    </aside>
    <div class="dept-sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>
    <button class="dept-sidebar-mobile-btn" id="deptNavToggle" type="button" aria-label="Open department menu" aria-expanded="false" aria-controls="deptSidebar">
      <i class="fa-solid fa-bars" aria-hidden="true"></i>
      <span>Menu</span>
    </button>
  `;
}

function initDepartmentSidebar() {
  const dept = document.body.dataset.department;
  if (!dept || !DEPT_TITLES[dept]) return;

  ensureFontAwesome();

  const layout = document.querySelector(".dept-layout");
  if (!layout || document.getElementById("deptSidebar")) return;

  const mount = document.createElement("div");
  mount.innerHTML = buildSidebarMarkup(DEPT_TITLES[dept]);

  const sidebar = mount.querySelector("#deptSidebar");
  const overlay = mount.querySelector("#sidebarOverlay");
  const mobileBtn = mount.querySelector("#deptNavToggle");

  layout.parentNode.insertBefore(sidebar, layout);
  layout.parentNode.insertBefore(overlay, layout);
  document.body.appendChild(mobileBtn);

  const collapseBtn = sidebar.querySelector("#collapseSidebar");
  const sidebarLinks = sidebar.querySelectorAll(".dept-sidebar__link");
  const siteHeader = document.getElementById("siteHeader");

  const isMobile = () => window.matchMedia("(max-width: 1024px)").matches;

  function updateHeader() {
    if (siteHeader) {
      siteHeader.classList.toggle("scrolled", window.scrollY > 28);
    }
  }

  function openMobileSidebar() {
    sidebar.classList.add("is-open");
    overlay.classList.add("active");
    document.body.classList.add("dept-sidebar-open");
    mobileBtn.setAttribute("aria-expanded", "true");
  }

  function closeMobileSidebar() {
    sidebar.classList.remove("is-open");
    overlay.classList.remove("active");
    document.body.classList.remove("dept-sidebar-open");
    mobileBtn.setAttribute("aria-expanded", "false");
  }

  function toggleCollapse() {
    if (isMobile()) {
      closeMobileSidebar();
      return;
    }
    const collapsed = document.body.classList.toggle("dept-sidebar-collapsed");
    collapseBtn.setAttribute("aria-expanded", String(!collapsed));
    collapseBtn.setAttribute("aria-label", collapsed ? "Expand sidebar" : "Collapse sidebar");
  }

  collapseBtn.addEventListener("click", toggleCollapse);
  mobileBtn.addEventListener("click", () => {
    if (sidebar.classList.contains("is-open")) closeMobileSidebar();
    else openMobileSidebar();
  });
  overlay.addEventListener("click", closeMobileSidebar);

  sidebarLinks.forEach((link) => {
    link.addEventListener("click", () => {
      if (isMobile()) closeMobileSidebar();
    });
  });

  window.addEventListener("resize", () => {
    if (!isMobile()) closeMobileSidebar();
  });

  window.addEventListener("scroll", () => {
    updateHeader();

    let current = "";
    document.querySelectorAll(".content-section").forEach((section) => {
      if (window.scrollY >= section.offsetTop - 140) {
        current = section.getAttribute("id");
      }
    });

    sidebarLinks.forEach((link) => {
      const isActive = link.dataset.section === current;
      link.classList.toggle("active", isActive);
      
      if (isActive && isMobile()) {
        const navContainer = sidebar.querySelector(".dept-sidebar__nav");
        if (navContainer) {
          const linkLeft = link.offsetLeft;
          const linkWidth = link.offsetWidth;
          const containerWidth = navContainer.offsetWidth;
          navContainer.scrollTo({
            left: linkLeft - (containerWidth / 2) + (linkWidth / 2),
            behavior: "smooth"
          });
        }
      }
    });
  }, { passive: true });

  updateHeader();
}

const outcomeTabs = document.querySelectorAll(".outcome-tab");
const outcomePanels = document.querySelectorAll(".outcome-panel");

outcomeTabs.forEach((tab) => {
  tab.addEventListener("click", () => {
    const targetPanel = document.getElementById(tab.dataset.target);
    if (!targetPanel) return;

    outcomeTabs.forEach((item) => {
      item.classList.remove("active");
      item.setAttribute("aria-selected", "false");
    });
    outcomePanels.forEach((panel) => panel.classList.remove("active"));

    tab.classList.add("active");
    tab.setAttribute("aria-selected", "true");
    targetPanel.classList.add("active");
  });
});

initDepartmentSidebar();

// Dynamic Read More / Show More functionality for HOD Desk and About Department
function initReadMoreSupport() {
  const targets = document.querySelectorAll(".hod-message, .about-content");
  targets.forEach((target) => {
    if (target.querySelector(".read-more-wrapper")) return;

    const wrapper = document.createElement("div");
    wrapper.className = "read-more-wrapper";

    while (target.firstChild) {
      wrapper.appendChild(target.firstChild);
    }
    target.appendChild(wrapper);

    // Add a short delay to ensure browser layout computation is complete
    setTimeout(() => {
      const thresholdHeight = 160; // 5 lines limit (~140px) + some buffer
      if (wrapper.scrollHeight > thresholdHeight) {
        const btn = document.createElement("button");
        btn.className = "read-more-btn";
        btn.setAttribute("type", "button");
        btn.innerHTML = 'Read More <i class="fa-solid fa-chevron-right"></i>';

        btn.addEventListener("click", () => {
          const isExpanded = wrapper.classList.contains("expanded");

          if (!isExpanded) {
            // Expand
            const startHeight = wrapper.offsetHeight;
            const endHeight = wrapper.scrollHeight;

            wrapper.style.maxHeight = startHeight + "px";
            wrapper.offsetHeight; // Force layout reflow

            wrapper.classList.add("expanded");
            wrapper.style.maxHeight = endHeight + "px";

            // Update button label
            btn.innerHTML = 'Read Less <i class="fa-solid fa-chevron-up"></i>';

            // Clean up inline styles once transition is done
            wrapper.addEventListener("transitionend", function handler(e) {
              if (e.propertyName === "max-height" && wrapper.classList.contains("expanded")) {
                wrapper.style.maxHeight = "";
                wrapper.removeEventListener("transitionend", handler);
              }
            });
          } else {
            // Collapse
            const startHeight = wrapper.scrollHeight;
            const endHeight = 140; // The truncated limit in CSS

            wrapper.style.maxHeight = startHeight + "px";
            wrapper.offsetHeight; // Force layout reflow

            wrapper.classList.remove("expanded");
            wrapper.style.maxHeight = endHeight + "px";

            // Update button label
            btn.innerHTML = 'Read More <i class="fa-solid fa-chevron-right"></i>';

            // Clean up inline styles once transition is done
            wrapper.addEventListener("transitionend", function handler(e) {
              if (e.propertyName === "max-height" && !wrapper.classList.contains("expanded")) {
                wrapper.style.maxHeight = "";
                wrapper.removeEventListener("transitionend", handler);
              }
            });

            // Smooth scroll target into view if the top goes offscreen
            const rect = target.getBoundingClientRect();
            if (rect.top < 0) {
              target.scrollIntoView({ behavior: "smooth", block: "nearest" });
            }
          }
        });

        target.appendChild(btn);
      } else {
        wrapper.classList.add("expanded");
      }
    }, 50);
  });
}

// Initialize Read More support when DOM is parsed
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initReadMoreSupport);
} else {
  initReadMoreSupport();
}

