(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [
    ...root.querySelectorAll(selector),
  ];
  let admin;
  const get = (key, fallback = []) => DG.getData(key, fallback);
  const setText = (selector, value, root = document) => {
    const element = typeof selector === "string" ? $(selector, root) : selector;
    if (element) element.textContent = value ?? "";
  };
  
  function init() {
    admin = AUTH.requireRole("admin");
    if (!admin) return;
    DG.loadProfileElements();
    APP.applyTheme();
    APP.updateNotif();
    $("#open")?.addEventListener("click", () =>
      $("#side")?.classList.toggle("-translate-x-full"),
    );
    $$("[data-theme-toggle]").forEach((button) =>
      button.addEventListener("click", () => APP.toggleTheme()),
    );
    $$("[data-logout]").forEach((button) =>
      button.addEventListener("click", () => AUTH.logout()),
    );
    // Live updates functionality can be added here if needed
    lucide.createIcons();
  }
  
  window.ADMIN_ACCOUNT_REQUESTS = { init };
})();

ADMIN_ACCOUNT_REQUESTS.init();
