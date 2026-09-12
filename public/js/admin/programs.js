(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [
    ...root.querySelectorAll(selector),
  ];
  const get = (key, fallback = []) => DG.getData(key, fallback);
  const save = (key, value) => DG.saveData(key, value);
  const setText = (selector, value) => {
    const element = $(selector);
    if (element) element.textContent = value ?? "";
  };
  const fullName = (user) =>
    `${user?.firstName || ""} ${user?.lastName || ""}`.trim() ||
    user?.id ||
    "Admin";

  let admin;
  let settings;

  const qualificationName = (row) =>
    typeof row === "string" ? row.trim() : (row?.name || "").trim();

  const PROGRAM_CATEGORIES = ["academics", "techpro"];
  const CATEGORY_LABELS = { academics: "Academics", techpro: "TechPro" };
  const normalizeCategory = (value) =>
    PROGRAM_CATEGORIES.includes(String(value || "").toLowerCase())
      ? String(value).toLowerCase()
      : PROGRAM_CATEGORIES[0];

  const parseLevels = (value) =>
    [
      ...new Set(
        String(value || "")
          .split(",")
          .map((item) => item.trim())
          .filter(Boolean),
      ),
    ];

  function addAudit(action, notes) {
    const logs = get("auditLogs");
    logs.push({
      id: DG.generateId("AUD"),
      entity: "system",
      recordId: "programs",
      action,
      actorId: admin.id,
      notes: notes || "",
      date: new Date().toISOString(),
    });
    save("auditLogs", logs);
  }

  function setTab(tab) {
    $$("[data-tab]").forEach((button) => {
      const active = button.dataset.tab === tab;
      button.className = `programs-tab rounded-xl px-4 py-2.5 text-sm font-semibold ${
        active
          ? "bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900"
          : "text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
      }`;
    });
    $$("[data-pane]").forEach((pane) => {
      pane.classList.toggle("hidden", pane.dataset.pane !== tab);
    });
  }

  function normalizePrograms() {
    if (!Array.isArray(settings.programs)) settings.programs = [];
    settings.programs = settings.programs.map((row) =>
      typeof row === "string"
        ? { name: row, description: "", category: "academics" }
        : {
            name: String(row?.name || ""),
            description: String(row?.description || ""),
            category: normalizeCategory(row?.category),
          },
    );
  }

  function normalizeQualifications() {
    if (!Array.isArray(settings.tvetQualifications)) settings.tvetQualifications = [];
    settings.tvetQualifications = settings.tvetQualifications.map((row) =>
      typeof row === "string"
        ? { name: row, description: "", levels: [] }
        : {
            name: (row?.name || "").toString(),
            description: (row?.description || "").toString(),
            levels: Array.isArray(row?.levels) ? row.levels : [],
          },
    );
  }

  function renderPrograms() {
    const body = $("#programBody");
    if (!body) return;
    body.replaceChildren();
    settings.programs.forEach((row, index) => {
      const tr = document.createElement("tr");

      const nameTd = document.createElement("td");
      nameTd.className = "p-3 align-top";
      const nameInput = document.createElement("input");
      nameInput.value = row.name;
      nameInput.placeholder = "Strand or track name";
      nameInput.className = "input w-full rounded-xl border px-3 py-2";
      nameInput.addEventListener("input", () => {
        row.name = nameInput.value;
      });
      nameTd.append(nameInput);

      const catTd = document.createElement("td");
      catTd.className = "p-3 align-top";
      const catSelect = document.createElement("select");
      catSelect.className = "input w-full rounded-xl border px-3 py-2";
      PROGRAM_CATEGORIES.forEach((category) => {
        const option = document.createElement("option");
        option.value = category;
        option.textContent = CATEGORY_LABELS[category] || category;
        option.selected = row.category === category;
        catSelect.append(option);
      });
      catSelect.addEventListener("change", () => {
        row.category = catSelect.value;
      });
      catTd.append(catSelect);

      const descTd = document.createElement("td");
      descTd.className = "p-3 align-top";
      const descInput = document.createElement("input");
      descInput.value = row.description;
      descInput.placeholder = "Short description";
      descInput.className = "input w-full rounded-xl border px-3 py-2";
      descInput.addEventListener("input", () => {
        row.description = descInput.value;
      });
      descTd.append(descInput);

      const actionsTd = document.createElement("td");
      actionsTd.className = "p-3 align-top text-right";
      const remove = document.createElement("button");
      remove.type = "button";
      remove.title = "Remove entry";
      remove.className =
        "rounded-lg p-2 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400";
      remove.innerHTML = '<i data-lucide="trash-2" class="h-4 w-4"></i>';
      remove.addEventListener("click", () => {
        settings.programs.splice(index, 1);
        renderPrograms();
        renderStats();
        lucide.createIcons();
      });
      actionsTd.append(remove);

      tr.append(nameTd, catTd, descTd, actionsTd);
      body.append(tr);
    });
    const academics = settings.programs.filter((entry) => entry.category === "academics").length;
    setText(
      "#programHint",
      settings.programs.length === 0
        ? "No entries yet — add one above and hit Save. An empty list leaves the college on its built-in strand/track defaults."
        : `${settings.programs.length} entry(ies) listed — Academics ${academics}, TechPro ${settings.programs.length - academics}.`,
    );
  }

  function renderQualifications() {
    const body = $("#qualificationBody");
    body.replaceChildren();
    settings.tvetQualifications.forEach((row, index) => {
      const tr = document.createElement("tr");

      const nameTd = document.createElement("td");
      nameTd.className = "p-3 align-top";
      const nameInput = document.createElement("input");
      nameInput.value = row.name;
      nameInput.placeholder = "Qualification name";
      nameInput.className = "input w-full rounded-xl border px-3 py-2";
      nameInput.addEventListener("input", () => {
        row.name = nameInput.value;
      });
      nameTd.append(nameInput);

      const levelsTd = document.createElement("td");
      levelsTd.className = "p-3 align-top";
      const levelsInput = document.createElement("input");
      levelsInput.value = (row.levels || []).join(", ");
      levelsInput.placeholder = "e.g. NC I, NC II";
      levelsInput.className = "input w-full rounded-xl border px-3 py-2";
      levelsInput.addEventListener("input", () => {
        row.levels = parseLevels(levelsInput.value);
      });
      levelsTd.append(levelsInput);

      const descTd = document.createElement("td");
      descTd.className = "p-3 align-top";
      const descInput = document.createElement("input");
      descInput.value = row.description;
      descInput.placeholder = "Short description";
      descInput.className = "input w-full rounded-xl border px-3 py-2";
      descInput.addEventListener("input", () => {
        row.description = descInput.value;
      });
      descTd.append(descInput);

      const actionsTd = document.createElement("td");
      actionsTd.className = "p-3 align-top text-right";
      const remove = document.createElement("button");
      remove.type = "button";
      remove.title = "Remove qualification";
      remove.className =
        "rounded-lg p-2 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400";
      remove.innerHTML = '<i data-lucide="trash-2" class="h-4 w-4"></i>';
      remove.addEventListener("click", () => {
        settings.tvetQualifications.splice(index, 1);
        renderQualifications();
        renderStats();
        lucide.createIcons();
      });
      actionsTd.append(remove);

      tr.append(nameTd, levelsTd, descTd, actionsTd);
      body.append(tr);
    });
    setText(
      "#qualificationHint",
      settings.tvetQualifications.length === 0
        ? "No qualifications yet — enter one above and hit Save."
        : `${settings.tvetQualifications.length} qualification(s) listed.`,
    );
  }

  function renderStats() {
    const programs = Array.isArray(settings.programs) ? settings.programs : [];
    setText("#programCount", String(programs.length));
    setText("#fallbackActive", programs.length === 0 ? "Yes" : "No");
  }

  function currentCatalogue() {
    const programs = (Array.isArray(settings.programs) ? settings.programs : [])
      .map((row) => ({
        name: (typeof row === "string" ? row : String(row?.name || "")).trim(),
        description: String(row?.description || "").trim(),
        category: normalizeCategory(row?.category),
      }))
      .filter((row) => row.name !== "");
    const qualifications = Array.isArray(settings.tvetQualifications)
      ? settings.tvetQualifications
          .map((row) => ({
            name: qualificationName(row),
            description: (row?.description || "").trim(),
            levels: Array.isArray(row?.levels) ? row.levels : [],
          }))
          .filter((row) => row.name !== "")
      : [];
    const levels = [];
    qualifications.forEach((row) => {
      (row.levels || []).forEach((level) => {
        if (!levels.includes(level)) levels.push(level);
      });
    });
    return {
      programs,
      tvetQualifications: qualifications,
      tvetLevels: levels,
    };
  }

  async function saveCatalogue() {
    const buttons = $$("[data-save-catalogue]");
    buttons.forEach((button) => {
      button.disabled = true;
    });

    const activePane = $$("[data-pane]").find(
      (pane) => !pane.classList.contains("hidden"),
    );
    const onPrograms = activePane?.dataset.pane === "programs";

    if (onPrograms) {
      const entryName = $("#programEntryInput")?.value.trim() || "";
      if (entryName) {
        const duplicate = settings.programs.some(
          (row) => row.name.toLowerCase() === entryName.toLowerCase(),
        );
        if (duplicate) {
          APP.toast("That entry already exists", "error");
          buttons.forEach((button) => {
            button.disabled = false;
          });
          return;
        }
        settings.programs.push({
          name: entryName,
          description: $("#programDescInput")?.value.trim() || "",
          category: normalizeCategory($("#programCatInput")?.value),
        });
        $("#programEntryInput").value = "";
        $("#programDescInput").value = "";
        renderPrograms();
        renderStats();
        lucide.createIcons();
      }
    } else {
      const entryName = $("#qualificationInput")?.value.trim() || "";
      if (entryName) {
        const duplicate = settings.tvetQualifications.some(
          (row) => qualificationName(row).toLowerCase() === entryName.toLowerCase(),
        );
        if (duplicate) {
          APP.toast("That qualification already exists", "error");
          buttons.forEach((button) => {
            button.disabled = false;
          });
          return;
        }
        settings.tvetQualifications.push({
          name: entryName,
          description: $("#descriptionEntryInput")?.value.trim() || "",
          levels: parseLevels($("#levelEntryInput")?.value || ""),
        });
        $("#qualificationInput").value = "";
        $("#levelEntryInput").value = "";
        $("#descriptionEntryInput").value = "";
        renderQualifications();
        renderStats();
        lucide.createIcons();
      }
    }

    setText("#programFeedback", "Saving...");
    setText("#tvetFeedback", "Saving...");

    settings = {
      ...settings,
      ...currentCatalogue(),
      updatedAt: new Date().toISOString(),
      updatedBy: admin.id,
    };
    save("settings", settings);

    const sync = await DG.flushSync();
    if (sync.ok) {
      addAudit(
        "Program catalogue updated",
        `Programs (${settings.programs.length}), TVET qualifications (${settings.tvetQualifications.length}), TVET levels (${settings.tvetLevels.length}).`,
      );
      const message = "Catalogue saved successfully.";
      setText("#programFeedback", message);
      setText("#tvetFeedback", message);
      APP.toast("Catalogue saved");
    } else {
      const message = `Saved locally, but server sync failed (${sync.failed.join(", ")}). Retrying in the background.`;
      setText("#programFeedback", message);
      setText("#tvetFeedback", message);
      APP.toast("Save failed to reach server", "error");
    }
    buttons.forEach((button) => {
      button.disabled = false;
    });
  }

  function init() {
    admin = AUTH.requireRole("admin");
    if (!admin) return;
    settings = get("settings", {});
    normalizePrograms();
    normalizeQualifications();

    const avatar = $("#avatar");
    if (avatar) {
      avatar.src = getProfilePhoto(admin);
      avatar.alt = `${fullName(admin)} profile photo`;
    }

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

    $$("[data-tab]").forEach((button) =>
      button.addEventListener("click", () => setTab(button.dataset.tab)),
    );
    $$("[data-save-catalogue]").forEach((button) =>
      button.addEventListener("click", saveCatalogue),
    );

    setTab("programs");
    renderPrograms();
    renderQualifications();
    renderStats();
    lucide.createIcons();
  }

  window.ADMIN_PROGRAMS = { init };
})();

ADMIN_PROGRAMS.init();