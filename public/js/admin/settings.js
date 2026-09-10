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
  const RESET_LABELS = {
    users: "Users",
    enrollments: "Enrollments",
    documentRequests: "Documents",
    grades: "Grades",
    competencies: "Competencies",
    notifications: "Notifications",
    announcements: "Announcements",
    attendance: "Attendance",
    requirements: "Requirements",
  };
  let admin;
  let settings;
  let lastBackupAt = null;
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
  async function saveSettings(event) {
    event.preventDefault();
    const button = $("#settingsForm [type='submit']");
    if (button) button.disabled = true;
    setText("#settingsFeedback", "Saving...");
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
    const sync = await DG.flushSync();
    if (sync.ok) {
      addAudit(
        "Settings updated",
        "Institution, registration, academic, and notification defaults updated.",
      );
      setText("#settingsFeedback", "Settings saved successfully.");
      APP.toast("Settings saved");
    } else {
      setText(
        "#settingsFeedback",
        `Settings saved locally, but server sync failed (${sync.failed.join(", ")}). Retrying in the background.`,
      );
      APP.toast("Save failed to reach server", "error");
    }
    if (button) button.disabled = false;
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
    lastBackupAt = backup.exportedAt;
    download(
      "digitech-portal-backup.json",
      JSON.stringify(backup, null, 2),
      "application/json",
    );
    addAudit("Backup exported", "Full MySQL backup downloaded.");
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
  async function importBackup(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.addEventListener("load", async () => {
      setText("#backupFeedback", "Reading backup...");
      try {
        const backup = JSON.parse(reader.result);
        if (!backup?.collections || typeof backup.collections !== "object")
          throw new Error("Invalid backup");
        if (
          !window.confirm(
            "Restore this backup? Current database collections will be replaced.",
          )
        )
          return;
        const restoredUsers = backup.collections.users;
        if (
          Array.isArray(restoredUsers) &&
          !restoredUsers.some((user) => user?.id === admin.id)
        ) {
          throw new Error(
            "This backup does not include your admin account — restoring it would lock you out.",
          );
        }
        storageKeys.forEach((key) => {
          if (Object.prototype.hasOwnProperty.call(backup.collections, key))
            save(key, backup.collections[key]);
        });
        addAudit(
          "Backup restored",
          `Backup from ${backup.exportedAt || "unknown date"} restored.`,
        );
        setText("#backupFeedback", "Restoring on the server...");
        APP.toast("Backup restored");
        const sync = await DG.flushSync();
        if (!sync.ok) {
          throw new Error(`Sync failed on: ${sync.failed.join(", ")}`);
        }
        setText("#backupFeedback", "Backup restored. Reloading portal data...");
        setTimeout(() => location.reload(), 300);
      } catch (error) {
        setText("#backupFeedback", `Restore failed: ${error.message}`);
        APP.toast("Restore failed", "error");
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
    const last = lastBackupAt;
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
  async function resetSelected() {
    const keys = $$("#resetOptions input:checked").map(
      (checkbox) => checkbox.dataset.resetKey,
    );
    if (!keys.length) {
      setText("#resetFeedback", "Select at least one collection to reset.");
      APP.toast("Nothing selected", "error");
      return;
    }
    const labels = keys
      .map((key) => RESET_LABELS[key] || key)
      .join(", ");
    const includesUsers = keys.includes("users");
    const message = includesUsers
      ? `Reset ${labels}? The ${get("users").length} account(s) will be deleted except the primary admin. Create a backup first if you may need them.`
      : `Reset ${labels}? Create a backup first if you may need the current records.`;
    if (!window.confirm(message)) return;
    const button = $("#resetSelectedBtn");
    if (button) button.disabled = true;
    setText("#resetFeedback", "Resetting on the server...");
    keys.forEach((key) => save(key, []));
    const sync = await DG.flushSync();
    if (sync.ok) {
      addAudit("Collections reset", `${labels} were reset.`);
      setText("#resetFeedback", "Reset complete.");
      APP.toast("Reset complete");
    } else {
      setText(
        "#resetFeedback",
        `Some resets failed on the server (${sync.failed.join(", ")}). Retrying in the background.`,
      );
      APP.toast("Some resets failed", "error");
    }
    if (button) button.disabled = false;
    $$("#resetOptions input:checked").forEach(
      (checkbox) => (checkbox.checked = false),
    );
    renderHealth();
    renderAudit();
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
    $$("[data-theme-toggle]").forEach((button) =>
      button.addEventListener("click", () => APP.toggleTheme()),
    );
    $$("[data-logout]").forEach((button) =>
      button.addEventListener("click", () => AUTH.logout()),
    );
    $("#settingsForm")?.addEventListener("submit", saveSettings);
    $("[data-export-backup]")?.addEventListener("click", exportBackup);
    $("#backupFile")?.addEventListener("change", importBackup);
    $("#resetSelectedBtn")?.addEventListener("click", resetSelected);
    renderHealth();
    renderAudit();
    lucide.createIcons();
  }
  window.ADMIN_SETTINGS = { init };
})();

ADMIN_SETTINGS.init();
