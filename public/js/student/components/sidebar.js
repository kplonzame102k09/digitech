const SIDEBAR_PATH = "components/sidebar.html";
const DESKTOP_BREAKPOINT = 1024;

async function loadSidebar() {
    const container = document.getElementById("global-sidebar");
    if (!container) return;

    try {
        const response = await fetch(new URL(SIDEBAR_PATH, document.baseURI));
        if (!response.ok) {
            throw new Error(`Failed to load sidebar (${response.status})`);
        }

        container.innerHTML = await response.text();

        if (window.lucide?.createIcons) {
            window.lucide.createIcons();
        }

        setActiveNavigation();
        setupLogout();
        setupMobileSidebar();
    } catch (error) {
        console.error("Sidebar loading error:", error);
    }
}

function getPageName(url) {
    try {
        const pathname = new URL(url, document.baseURI).pathname;
        return pathname.split("/").filter(Boolean).pop()?.toLowerCase() || "";
    } catch (error) {
        return "";
    }
}

function setActiveNavigation() {
    const currentPage = getPageName(window.location.href);
    const sidebar = document.getElementById("side");
    if (!sidebar || !currentPage) return;

    sidebar.querySelectorAll("nav a[href]").forEach(link => {
        const linkPage = getPageName(link.href);
        link.classList.toggle("nav-active", linkPage === currentPage);
    });
}

function setupLogout() {
    const logoutButton = document.querySelector("#side [data-logout]");
    if (!logoutButton) return;

    logoutButton.addEventListener("click", () => {
        localStorage.removeItem("currentUser");
        localStorage.removeItem("loggedInUser");
        window.location.assign("../login.html");
    });
}

function setupMobileSidebar() {
    const sidebar = document.getElementById("side");
    const menuButton = document.getElementById("open");
    if (!sidebar || !menuButton) return;

    const isDesktop = () => window.innerWidth >= DESKTOP_BREAKPOINT;
    let isOpen = isDesktop();

    let backdrop = document.getElementById("sidebar-backdrop");
    if (!backdrop) {
        backdrop = document.createElement("button");
        backdrop.id = "sidebar-backdrop";
        backdrop.type = "button";
        backdrop.className =
            "fixed inset-0 z-20 hidden bg-slate-950/40 lg:hidden";
        backdrop.setAttribute("aria-label", "Close sidebar");
        document.body.appendChild(backdrop);
    }

    const updateSidebar = () => {
        const desktop = isDesktop();
        const visible = desktop || isOpen;

        sidebar.classList.toggle("-translate-x-full", !visible);
        backdrop.classList.toggle("hidden", desktop || !isOpen);
        backdrop.setAttribute("aria-hidden", String(desktop || !isOpen));
        menuButton.setAttribute("aria-controls", "side");
        menuButton.setAttribute("aria-expanded", String(visible));
        document.body.classList.toggle("overflow-hidden", !desktop && isOpen);
    };

    const openSidebar = () => {
        isOpen = true;
        updateSidebar();
    };

    const closeSidebar = () => {
        isOpen = false;
        updateSidebar();
    };

    menuButton.addEventListener("click", event => {
        event.preventDefault();
        if (isOpen) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    backdrop.addEventListener("click", closeSidebar);

    sidebar.querySelectorAll("nav a").forEach(link => {
        link.addEventListener("click", () => {
            if (!isDesktop()) {
                closeSidebar();
            }
        });
    });

    document.addEventListener("keydown", event => {
        if (event.key === "Escape" && !isDesktop() && isOpen) {
            closeSidebar();
        }
    });

    window.addEventListener("resize", () => {
        isOpen = isDesktop();
        updateSidebar();
    });

    updateSidebar();
}

document.addEventListener("DOMContentLoaded", loadSidebar);
