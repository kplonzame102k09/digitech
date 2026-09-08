(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [
    ...root.querySelectorAll(selector),
  ];
  const storageKeys = [
    "users",
    "enrollments",
    "documentRequests",
    "grades",
    "competencies",
    "notifications",
    "announcements",
    "attendance",
    "auditLogs",
    "requirements",
    "settings",
  ];
  let admin;
  let settings;
  const get = (key, fallback = []) => DG.getData(key, fallback);
  const save = (key, value) => DG.saveData(key, value);
  const setText = (selector, value, root = document) => {
    const element = typeof selector === "string" ? $(selector, root) : selector;
    if (element) element.textContent = value ?? "";
  };
  const clone = (id) => {
    const template = document.getElementById(id);
    return template
      ? document.importNode(template.content, true).firstElementChild
      : null;
  };
  const fullName = (user) =>
    `${user?.firstName || ""} ${user?.lastName || ""}`.trim() ||
    user?.id ||
    "Admin";
  function addAudit(action, notes) {
    const logs = get("auditLogs");
    logs.push({
      id: DG.generateId("AUD"),
      entity: "system",
      recordId: "settings",
      action,
      actorId: admin.id,
      notes: notes || "",
      date: new Date().toISOString(),
    });
    save("auditLogs", logs);
  }
  function hydrate() {
    const values = {
      teacherReg: settings.teacherRegistration !== false,
      adminReg: settings.adminRegistration === true,
      institutionName: settings.institutionName || "Digitech College",
      schoolYear: settings.schoolYear || "2026-2027",
      passingGrade: settings.passingGrade ?? 75,
      enrollmentDeadline: settings.enrollmentDeadline || "",
      notifyStudents: settings.notifyStudents !== false,
      notifyParents: settings.notifyParents !== false,
      notifyTeachers: settings.notifyTeachers !== false,
      notifyAdmins: settings.notifyAdmins !== false,
    };
    Object.entries(values).forEach(([id, value]) => {
      const field = document.getElementById(id);
      if (!field) return;
      if (field.type === "checkbox") field.checked = value;
      else field.value = value;
    });
  }
  function saveSettings(event) {
    event.preventDefault();
    settings = {
      ...settings,
      teacherRegistration: $("#teacherReg").checked,
      adminRegistration: $("#adminReg").checked,
      institutionName: $("#institutionName").value.trim(),
      schoolYear: $("#schoolYear").value.trim(),
      passingGrade: Number($("#passingGrade").value) || 75,
      enrollmentDeadline: $("#enrollmentDeadline").value,
      notifyStudents: $("#notifyStudents").checked,
      notifyParents: $("#notifyParents").checked,
      notifyTeachers: $("#notifyTeachers").checked,
      notifyAdmins: $("#notifyAdmins").checked,
      updatedAt: new Date().toISOString(),
      updatedBy: admin.id,
    };
    save("settings", settings);
    addAudit(
      "Settings updated",
      "Institution, registration, academic, and notification defaults updated.",
    );
    setText("#settingsFeedback", "Settings saved successfully.");
    APP.toast("Settings saved");
    renderHealth();
  }
  function download(name, content, type) {
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([content], { type }));
    link.download = name;
    link.click();
    URL.revokeObjectURL(link.href);
  }
  function exportBackup() {
    const backup = {
      app: "Digitech College Portal",
      version: 1,
      exportedAt: new Date().toISOString(),
      collections: Object.fromEntries(
        storageKeys.map((key) => [key, get(key, key === "settings" ? {} : [])]),
      ),
    };
    localStorage.setItem("lastBackupAt", backup.exportedAt);
    download(
      "digitech-portal-backup.json",
      JSON.stringify(backup, null, 2),
      "application/json",
    );
    addAudit("Backup exported", "Full LocalStorage backup downloaded.");
    setText(
      "#backupFeedback",
      `Backup downloaded on ${new Date(backup.exportedAt).toLocaleString()}.`,
    );
    setText(
      "#lastBackup",
      `Last backup: ${new Date(backup.exportedAt).toLocaleString()}`,
    );
    APP.toast("Backup downloaded");
    renderAudit();
  }
  function importBackup(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.addEventListener("load", () => {
      try {
        const backup = JSON.parse(reader.result);
        if (!backup?.collections || typeof backup.collections !== "object")
          throw new Error("Invalid backup");
        if (
          !window.confirm(
            "Restore this backup? Current prototype data will be replaced.",
          )
        )
          return;
        storageKeys.forEach((key) => {
          if (Object.prototype.hasOwnProperty.call(backup.collections, key))
            save(key, backup.collections[key]);
        });
        addAudit(
          "Backup restored",
          `Backup from ${backup.exportedAt || "unknown date"} restored.`,
        );
        setText("#backupFeedback", "Backup restored. Reloading portal data...");
        APP.toast("Backup restored");
        setTimeout(() => location.reload(), 500);
      } catch (error) {
        setText("#backupFeedback", `Restore failed: ${error.message}`);
        APP.toast("Invalid backup file", "error");
      }
    });
    reader.readAsText(file);
    event.target.value = "";
  }
  function renderHealth() {
    const grid = $("#healthGrid");
    grid?.replaceChildren();
    const labels = {
      users: "Users",
      enrollments: "Enrollments",
      documentRequests: "Documents",
      grades: "Grades",
      competencies: "Competencies",
      notifications: "Notifications",
      auditLogs: "Audit logs",
      announcements: "Announcements",
      attendance: "Attendance",
    };
    Object.entries(labels).forEach(([key, label]) => {
      const box = document.createElement("div");
      box.className = "rounded-xl bg-slate-50 p-3 dark:bg-slate-800";
      const name = document.createElement("p");
      name.className = "text-xs text-slate-400";
      name.textContent = label;
      const count = document.createElement("b");
      count.className = "mt-1 block text-lg";
      count.textContent = get(key).length;
      box.append(name, count);
      grid?.append(box);
    });
    const last = localStorage.getItem("lastBackupAt");
    setText(
      "#lastBackup",
      last
        ? `Last backup: ${new Date(last).toLocaleString()}`
        : "Last backup: none recorded",
    );
  }
  function renderAudit() {
    const container = $("#auditRows");
    container?.replaceChildren();
    const logs = get("auditLogs")
      .filter((log) => log.entity === "system")
      .sort((a, b) => String(b.date).localeCompare(String(a.date)))
      .slice(0, 10);
    $("#auditEmpty")?.classList.toggle("hidden", logs.length > 0);
    logs.forEach((log) => {
      const row = clone("auditTemplate");
      if (!row) return;
      setText("[data-audit-title]", log.action, row);
      setText("[data-audit-date]", new Date(log.date).toLocaleString(), row);
      setText(
        "[data-audit-meta]",
        `Admin ${log.actorId} · ${log.notes || "No additional notes"}`,
        row,
      );
      container?.append(row);
    });
  }
  function resetKey(key) {
    const label = key === "documentRequests" ? "documents" : key;
    if (
      !window.confirm(
        `Reset ${label} demo data? Create a backup first if you may need the current records.`,
      )
    )
      return;
    localStorage.removeItem(key);
    addAudit("Collection reset", `${key} collection was reset.`);
    APP.toast(`${label} reset`);
    renderHealth();
    renderAudit();
  }
  function resetAll() {
    if (
      !window.confirm(
        "Reset all prototype data? This cannot be undone without a downloaded backup.",
      )
    )
      return;
    storageKeys.forEach((key) => localStorage.removeItem(key));
    localStorage.removeItem("lastBackupAt");
    location.href = "../index.html";
  }
  function init() {
    admin = AUTH.requireRole("admin");
    if (!admin) return;
    settings = get("settings", {});
    const avatar = $("#avatar");
    if (avatar) {
      avatar.src = getProfilePhoto(admin);
      avatar.alt = `${fullName(admin)} profile photo`;
    }
    hydrate();
    APP.applyTheme();
    APP.updateNotif();
    $("#open")?.addEventListener("click", () =>
      $("#side")?.classList.toggle("-translate-x-full"),
    );
    $$("[data-notifications]").forEach((button) =>
      button.addEventListener("click", () => APP.showNotifications()),
    );
    $$("[data-theme-toggle]").forEach((button) =>
      button.addEventListener("click", () => APP.toggleTheme()),
    );
    $$("[data-logout]").forEach((button) =>
      button.addEventListener("click", () => AUTH.logout()),
    );
    $("#settingsForm")?.addEventListener("submit", saveSettings);
    $("[data-export-backup]")?.addEventListener("click", exportBackup);
    $("#backupFile")?.addEventListener("change", importBackup);
    $$("[data-reset-key]").forEach((button) =>
      button.addEventListener("click", () => resetKey(button.dataset.resetKey)),
    );
    $("[data-reset-all]")?.addEventListener("click", resetAll);
    renderHealth();
    renderAudit();
    lucide.createIcons();
  }
  window.ADMIN_SETTINGS = { init };
})();

ADMIN_SETTINGS.init();
