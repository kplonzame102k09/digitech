(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [
    ...root.querySelectorAll(selector),
  ];
  const usersKey = "users";
  let currentUser;
  let users = [];
  let selected = new Set();
  let editingId = null;

  const get = (key, fallback = []) => DG.getData(key, fallback);
  const save = (key, value) => DG.saveData(key, value);
  const fullName = (user) =>
    `${user?.firstName || ""} ${user?.lastName || ""}`.trim() ||
    user?.id ||
    "Unknown user";
  const initials = (user) =>
    `${user?.firstName?.[0] || ""}${user?.lastName?.[0] || ""}`.toUpperCase() ||
    "DG";
  const relatedCount = (user) => {
    const records = [
      "enrollments",
      "documentRequests",
      "grades",
      "competencies",
      "attendance",
      "notifications",
    ];
    return records.reduce(
      (total, key) =>
        total +
        get(key).filter((record) =>
          [
            record.userId,
            record.studentId,
            record.teacherId,
            record.childId,
          ].includes(user.id),
        ).length,
      0,
    );
  };
  const setText = (selector, value, root = document) => {
    const element = typeof selector === "string" ? $(selector, root) : selector;
    if (element) element.textContent = value ?? "";
  };
  const roleLabel = (role) =>
    ({
      admin: "Admin",
      teacher: "Teacher",
      student: "Student",
      parent: "Parent",
    })[role] ||
    role ||
    "Unknown";
  const statusLabel = (user) =>
    user.status === "inactive" ? "Inactive" : "Active";
  const isNotEnrolledStudent = (user) => {
    if (user.role !== "student") return false;
    const hasEnrollment = get("enrollments").some(
      (record) => record.studentId === user.id,
    );
    const hasSubmittedRequirement = [
      ...get("requirements"),
      ...get("documentRequests"),
    ].some((record) => record.studentId === user.id);
    return !hasEnrollment && !hasSubmittedRequirement;
  };
  const clone = (id) => {
    const template = document.getElementById(id);
    return template
      ? document.importNode(template.content, true).firstElementChild
      : null;
  };

  function setStatusBadge(element, value, kind = "status") {
    if (!element) return;
    const positive = kind === "status" && value === "Active";
    element.className = `inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ${positive ? "bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300" : "bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300"}`;
    element.textContent = value;
  }

  function visibleUsers() {
    const query = ($("#q")?.value || "").trim().toLowerCase();
    const role = $("#filter")?.value || "";
    const accountStatus = $("#statusFilter")?.value || "";
    const sortBy = $("#sortBy")?.value || "name";
    return users
      .filter((user) => {
        const haystack =
          `${fullName(user)} ${user.id || ""} ${user.email || ""} ${user.username || ""}`.toLowerCase();
        return (
          (!query || haystack.includes(query)) &&
          (!role || user.role === role) &&
          (!accountStatus || statusLabel(user).toLowerCase() === accountStatus)
        );
      })
      .sort((a, b) =>
        String(
          sortBy === "name"
            ? fullName(a)
            : sortBy === "role"
              ? roleLabel(a.role)
              : sortBy === "status"
                ? statusLabel(a)
                : a.id,
        ).localeCompare(
          String(
            sortBy === "name"
              ? fullName(b)
              : sortBy === "role"
                ? roleLabel(b.role)
                : sortBy === "status"
                  ? statusLabel(b)
                  : b.id,
          ),
        ),
      );
  }

  function render() {
    const container = $("#rows");
    container?.replaceChildren();
    const visible = visibleUsers();
    setText("#resultCount", `${visible.length} of ${users.length} accounts`);
    setText(
      "#selectionCount",
      selected.size ? `${selected.size} selected` : "",
    );
    $("#emptyState")?.classList.toggle("hidden", visible.length > 0);
    visible.forEach((user) => {
      const row = clone("userRowTemplate");
      if (!row) return;
      const checkbox = $("[data-select-user]", row);
      checkbox.checked = selected.has(user.id);
      checkbox.disabled = user.id === currentUser.id;
      checkbox.addEventListener("change", () => {
        if (checkbox.checked) selected.add(user.id);
        else selected.delete(user.id);
        render();
      });
      const initialsElement = $("[data-user-initials]", row);
      const photo = $("[data-user-photo]", row);
      setText(initialsElement, initials(user));
      if (photo) {
        photo.src = user.photo || "../assets/images/16432.png";
        photo.alt = `${fullName(user)} profile photo`;
        initialsElement?.classList.add("hidden");
        photo.addEventListener("error", () => {
          if (photo.dataset.fallbackApplied !== "true") {
            photo.dataset.fallbackApplied = "true";
            photo.src = "../assets/images/16432.png";
            return;
          }
          photo.classList.add("hidden");
          initialsElement?.classList.remove("hidden");
        });
      }
      setText("[data-user-name]", fullName(user), row);
      setText(
        "[data-user-username]",
        user.username ? `@${user.username}` : user.email || "No username",
        row,
      );
      setText("[data-user-id]", user.id, row);
      setText("[data-user-email]", user.email || "—", row);
      setStatusBadge($("[data-user-role]", row), roleLabel(user.role), "role");
      const statusElement = $("[data-user-status]", row);
      setStatusBadge(statusElement, statusLabel(user));
      if (isNotEnrolledStudent(user)) {
        const marker = document.createElement("span");
        marker.className = "mt-1 inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-300";
        marker.textContent = "Not enrolled";
        statusElement?.parentElement?.append(marker);
      }
      $$("[data-action]", row).forEach((button) =>
        button.addEventListener("click", () =>
          handleAction(button.dataset.action, user),
        ),
      );
      container?.append(row);
    });
    const visibleIds = new Set(visible.map((user) => user.id));
    const allVisibleSelected =
      visible.length > 0 && visible.every((user) => selected.has(user.id));
    const selectAll = $("#selectAll");
    if (selectAll) {
      selectAll.checked = allVisibleSelected;
      selectAll.indeterminate =
        !allVisibleSelected && visible.some((user) => selected.has(user.id));
    }
    $("[data-bulk-toggle]")?.classList.toggle("hidden", selected.size === 0);
    lucide.createIcons();
  }

  function fillParentLinks() {
    const select = $("#childId");
    if (!select) return;
    select.replaceChildren();
    const empty = document.createElement("option");
    empty.value = "";
    empty.textContent = "No linked student";
    select.append(empty);
    users
      .filter((user) => user.role === "student")
      .forEach((student) => {
        const option = document.createElement("option");
        option.value = student.id;
        option.textContent = `${fullName(student)} · ${student.id}`;
        select.append(option);
      });
  }

  function openEditor(user = null) {
    editingId = user?.id || null;
    const dialog = $("#userDialog");
    if (!dialog) return;
    setText("#dialogEyebrow", user ? "Edit account" : "New account");
    setText("#dialogTitle", user ? `Edit ${fullName(user)}` : "Add user");
    fillParentLinks();
    const fields = {
      userId: user?.id || "",
      firstName: user?.firstName || "",
      lastName: user?.lastName || "",
      email: user?.email || "",
      username: user?.username || "",
      role: user?.role || "student",
      accountStatus: user?.status === "inactive" ? "inactive" : "active",
      password: "",
      contact: user?.contact || "",
      strand: user?.strand || "",
      childId:
        user?.childId || user?.childIds?.[0] || user?.children?.[0] || "",
      address: user?.address || "",
    };
    Object.entries(fields).forEach(([id, value]) => {
      const element = document.getElementById(id);
      if (element) element.value = value;
    });
    syncParentField();
    $("#formFeedback").textContent = "";
    dialog.showModal();
  }

  function syncParentField() {
    $("#childLinkField")?.classList.toggle(
      "hidden",
      $("#role")?.value !== "parent",
    );
  }

  function saveUser(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const fields = [
      "firstName",
      "lastName",
      "email",
      "username",
      "role",
      "accountStatus",
      "password",
      "contact",
      "strand",
      "childId",
      "address",
    ];
    const values = Object.fromEntries(
      fields.map((id) => [id, document.getElementById(id)?.value.trim() || ""]),
    );
    const duplicate = users.find(
      (user) =>
        user.id !== editingId &&
        ((values.email &&
          user.email?.toLowerCase() === values.email.toLowerCase()) ||
          (values.username &&
            user.username?.toLowerCase() === values.username.toLowerCase())),
    );
    if (duplicate) {
      setText("#formFeedback", "Email or username is already in use.");
      return;
    }
    if (!editingId && values.password.length < 6) {
      setText(
        "#formFeedback",
        "New accounts require a password with at least 6 characters.",
      );
      return;
    }
    let user = users.find((item) => item.id === editingId);
    if (!user) {
      user = {
        id: DG.generateUserId(values.role),
        createdAt: new Date().toISOString(),
      };
      users.push(user);
    }
    Object.assign(user, {
      firstName: values.firstName,
      lastName: values.lastName,
      email: values.email,
      username: values.username,
      role: values.role,
      status: values.accountStatus,
      contact: values.contact,
      strand: values.strand,
      address: values.address,
      updatedAt: new Date().toISOString(),
    });
    if (values.password) user.password = values.password;
    if (user.role === "parent") {
      user.childId = values.childId || "";
      user.childIds = values.childId ? [values.childId] : [];
      user.children = user.childIds;
    } else {
      delete user.childId;
      delete user.childIds;
      delete user.children;
    }
    save(usersKey, users);
    $("#userDialog")?.close();
    APP.toast(editingId ? "User updated" : "User created");
    render();
  }

  function showDetails(user) {
    setText("#detailName", fullName(user));
    const body = $("#detailBody");
    body?.replaceChildren();
    const details = [
      ["User ID", user.id],
      ["Role", roleLabel(user.role)],
      ["Status", statusLabel(user)],
      ["Email", user.email || "—"],
      ["Username", user.username ? `@${user.username}` : "—"],
      ["Contact", user.contact || "—"],
      ["Program / strand", user.strand || "—"],
      ["Related records", relatedCount(user)],
      [
        "Created",
        user.createdAt
          ? new Date(user.createdAt).toLocaleString()
          : "Not recorded",
      ],
    ];
    details.forEach(([label, value]) => {
      const wrapper = document.createElement("div");
      wrapper.className = "rounded-xl bg-slate-50 p-3 dark:bg-slate-800";
      const key = document.createElement("p");
      key.className = "text-xs text-slate-400";
      key.textContent = label;
      const val = document.createElement("p");
      val.className = "mt-1 text-sm font-semibold";
      val.textContent = value;
      wrapper.append(key, val);
      body?.append(wrapper);
    });
    $("#detailDialog")?.showModal();
  }

  function toggleStatus(user) {
    if (user.id === currentUser.id) return;
    user.status = statusLabel(user) === "Active" ? "inactive" : "active";
    user.updatedAt = new Date().toISOString();
    save(usersKey, users);
    APP.toast(`${fullName(user)} is now ${statusLabel(user).toLowerCase()}`);
    render();
  }
  function resetPassword(user) {
    if (user.id === currentUser.id) return;
    const password = window.prompt(
      `Enter a new temporary password for ${fullName(user)}:`,
    );
    if (!password) return;
    if (password.length < 6) {
      APP.toast("Password must be at least 6 characters", "error");
      return;
    }
    user.password = password;
    user.mustChangePassword = true;
    save(usersKey, users);
    APP.toast("Temporary password saved");
  }
  function deleteUser(user) {
    if (user.id === currentUser.id) {
      APP.toast("You cannot delete your own admin account", "error");
      return;
    }
    const relationships = relatedCount(user);
    if (relationships) {
      const archive = window.confirm(
        `${fullName(user)} has ${relationships} related records. Deactivate this account instead of deleting it?`,
      );
      if (archive) toggleStatus(user);
      return;
    }
    if (
      !window.confirm(
        `Delete ${fullName(user)}? This account has no related records.`,
      )
    )
      return;
    users = users.filter((item) => item.id !== user.id);
    save(usersKey, users);
    selected.delete(user.id);
    APP.toast("User deleted");
    render();
  }
  function handleAction(action, user) {
    if (action === "details") showDetails(user);
    if (action === "edit") openEditor(user);
    if (action === "toggle") toggleStatus(user);
    if (action === "reset") resetPassword(user);
    if (action === "delete") deleteUser(user);
  }
  function bulkAction() {
    if (!selected.size) return;
    const action = window
      .prompt(
        "Type activate or deactivate to update selected accounts:",
        "deactivate",
      )
      ?.toLowerCase();
    if (!["activate", "deactivate"].includes(action)) return;
    users.forEach((user) => {
      if (selected.has(user.id) && user.id !== currentUser.id)
        user.status = action === "activate" ? "active" : "inactive";
    });
    save(usersKey, users);
    selected.clear();
    APP.toast(`Selected accounts ${action}d`);
    render();
  }
  function exportUsers() {
    const rows = [
      [
        "Name",
        "User ID",
        "Role",
        "Email",
        "Username",
        "Status",
        "Contact",
        "Program / Strand",
      ],
      ...visibleUsers().map((user) => [
        fullName(user),
        user.id,
        roleLabel(user.role),
        user.email || "",
        user.username || "",
        statusLabel(user),
        user.contact || "",
        user.strand || "",
      ]),
    ];
    const csv = rows
      .map((row) =>
        row
          .map((value) => `"${String(value).replaceAll('"', '""')}"`)
          .join(","),
      )
      .join("\n");
    const link = document.createElement("a");
    link.href = URL.createObjectURL(
      new Blob([csv], { type: "text/csv;charset=utf-8" }),
    );
    link.download = "digitech-users.csv";
    link.click();
    URL.revokeObjectURL(link.href);
    APP.toast("CSV export downloaded");
  }
function generateUserId(role, existingUsers) {
  const prefix =
    role.toLowerCase() === "student"
      ? "STU-2026"
      : role.toLowerCase() === "teacher"
        ? "TCH-2026"
        : role.toLowerCase() === "admin"
          ? "ADM-2026"
          : "PRT-2026";

  let id;

  do {
    id =
      `${prefix}-` +
      Math.random().toString(36).substring(2, 8).toUpperCase();
  } while (existingUsers.some((user) => user.id === id));

  return id;
}

function importUsers() {
  const input = document.createElement("input");

  input.type = "file";
  input.accept = ".csv,text/csv";

  input.onchange = (event) => {
    const file = event.target.files[0];

    if (!file) return;

    const reader = new FileReader();

    reader.onload = (e) => {
      const text = e.target.result;

      const lines = text
        .split(/\r?\n/)
        .filter((line) => line.trim() !== "");

      if (lines.length < 2) {
        APP.toast("CSV file contains no users");
        return;
      }

      // Parse CSV
      const rows = lines.map((line) => {
        const values = [];
        const regex = /("([^"]|"")*"|[^,]+)/g;
        let match;

        while ((match = regex.exec(line)) !== null) {
          let value = match[0].trim();

          if (value.startsWith('"') && value.endsWith('"')) {
            value = value.slice(1, -1).replaceAll('""', '"');
          }

          values.push(value);
        }

        return values;
      });

      // CSV headers
      const headers = rows[0].map((header) =>
        header.trim().toLowerCase()
      );

      const users = DG.getData("users") || [];

      let importedCount = 0;
      let generatedCount = 0;

      rows.slice(1).forEach((row) => {
        const data = {};

        headers.forEach((header, index) => {
          data[header] = row[index] || "";
        });

        const role = (data["role"] || "student").toLowerCase();

        // Use CSV User ID if provided
        let userId = data["user id"];

        // Generate one if missing
        if (!userId || userId.trim() === "") {
          userId = generateUserId(role, users);
          generatedCount++;
        }

        // Create user object
        const newUser = {
          id: userId,
          firstName: data["firstname"] || "",
          middleName: data["middlename"] || "",
          lastName: data["lastname"] || "",
          createdAt: new Date().toISOString(),
          updatedAt: new Date().toISOString(),
          role: role,
          email: data["email"] || "",
          status: data["status"] || "active",
          password: data["password"] || "",
          contact: data["contact"] || "",
        };

        users.push(newUser);
        importedCount++;
      });

      // Save users
      DG.saveData("users", users);

      APP.toast(
        `${importedCount} users imported` +
        (generatedCount > 0
          ? `, ${generatedCount} User IDs generated`
          : "")
      );

      setTimeout(() => {
        location.reload();
      }, 700);
    };
    reader.readAsText(file);
  };
  input.click();
}
  function init() {
    currentUser = AUTH.requireRole("admin");
    if (!currentUser) return;
    users = get(usersKey);
    const avatar = $("#avatar");
    if (avatar) {
      avatar.src = getProfilePhoto(currentUser);
      avatar.alt = `${fullName(currentUser)} profile photo`;
    }
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
    $("#q")?.addEventListener("input", render);
    $("#filter")?.addEventListener("change", render);
    $("#statusFilter")?.addEventListener("change", render);
    $("#sortBy")?.addEventListener("change", render);
    $("#selectAll")?.addEventListener("change", (event) => {
      visibleUsers().forEach((user) => {
        if (user.id !== currentUser.id) {
          if (event.target.checked) selected.add(user.id);
          else selected.delete(user.id);
        }
      });
      render();
    });
    $("[data-create-user]")?.addEventListener("click", () => openEditor());
    $("[data-import-users]")?.addEventListener("click", importUsers);
    $("[data-export-users]")?.addEventListener("click", exportUsers);
    $("[data-bulk-toggle]")?.addEventListener("click", bulkAction);
    $("#userForm")?.addEventListener("submit", saveUser);
    $("#role")?.addEventListener("change", syncParentField);
    $$("[data-close-dialog]").forEach((button) =>
      button.addEventListener("click", () => $("#userDialog")?.close()),
    );
    $$("[data-close-detail]").forEach((button) =>
      button.addEventListener("click", () => $("#detailDialog")?.close()),
    );
    lucide.createIcons();
    render();
  }
  window.ADMIN_USERS = { init };
})();

ADMIN_USERS.init();
