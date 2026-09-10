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
  let currentPage = 1;
  const itemsPerPage = 10;

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
      admin: "Administrator",
      teacher: "Teacher",
      student: "Student",
      parent: "Parent",
      guest: "Guest",
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
    const totalPages = Math.ceil(visible.length / itemsPerPage);
    
    // Reset to page 1 if current page exceeds total pages
    if (currentPage > totalPages && totalPages > 0) {
      currentPage = 1;
    }
    
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const paginatedUsers = visible.slice(startIndex, endIndex);
    
    setText("#resultCount", `${visible.length} of ${users.length} accounts`);
    setText(
      "#selectionCount",
      selected.size ? `${selected.size} selected` : "",
    );
    $("#emptyState")?.classList.toggle("hidden", visible.length > 0);
    paginatedUsers.forEach((user) => {
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
        photo.src = user.photo || "/images/16432.png";
        photo.alt = `${fullName(user)} profile photo`;
        initialsElement?.classList.add("hidden");
        photo.addEventListener("error", () => {
          if (photo.dataset.fallbackApplied !== "true") {
            photo.dataset.fallbackApplied = "true";
            photo.src = "/images/16432.png";
            return;
          }
          photo.classList.add("hidden");
          initialsElement?.classList.remove("hidden");
        });
      }
      setText("[data-row-user-name]", fullName(user), row);
      setText(
        "[data-user-username]",
        user.username ? `@${user.username}` : user.email || "No username",
        row,
      );
      setText("[data-row-user-id]", user.id, row);
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
    const visibleIds = new Set(paginatedUsers.map((user) => user.id));
    const allVisibleSelected =
      paginatedUsers.length > 0 && paginatedUsers.every((user) => selected.has(user.id));
    const selectAll = $("#selectAll");
    if (selectAll) {
      selectAll.checked = allVisibleSelected;
      selectAll.indeterminate =
        !allVisibleSelected && paginatedUsers.some((user) => selected.has(user.id));
    }
    $("#bulkActions")?.classList.toggle("hidden", selected.size === 0);
    
    // Update delete button state
    if (selected.size > 0) {
      updateBulkDeleteButton();
    }
    
    // Render pagination controls
    renderPagination(totalPages, visible.length);
    
    lucide.createIcons();
  }

  function renderPagination(totalPages, totalItems) {
    const paginationContainer = $("#paginationContainer");
    if (!paginationContainer) return;
    
    paginationContainer.replaceChildren();
    
    if (totalPages <= 1) {
      paginationContainer.classList.add("hidden");
      return;
    }
    
    paginationContainer.classList.remove("hidden");
    
    const startItem = (currentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(currentPage * itemsPerPage, totalItems);
    
    // Info text
    const info = document.createElement("span");
    info.className = "text-xs text-slate-500";
    info.textContent = `Showing ${startItem}-${endItem} of ${totalItems}`;
    paginationContainer.appendChild(info);
    
    // Previous button
    const prevButton = document.createElement("button");
    prevButton.className = "ml-2 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800";
    prevButton.disabled = currentPage === 1;
    prevButton.innerHTML = '<i data-lucide="chevron-left" class="h-3 w-3"></i>';
    prevButton.addEventListener("click", () => {
      if (currentPage > 1) {
        currentPage--;
        render();
      }
    });
    paginationContainer.appendChild(prevButton);
    
    // Page numbers
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    
    if (endPage - startPage + 1 < maxVisiblePages) {
      startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    for (let i = startPage; i <= endPage; i++) {
      const pageButton = document.createElement("button");
      pageButton.className = `mx-1 rounded-lg px-3 py-1.5 text-xs font-medium ${
        i === currentPage 
          ? "bg-green-600 text-white" 
          : "border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800"
      }`;
      pageButton.textContent = i;
      pageButton.addEventListener("click", () => {
        currentPage = i;
        render();
      });
      paginationContainer.appendChild(pageButton);
    }
    
    // Next button
    const nextButton = document.createElement("button");
    nextButton.className = "ml-2 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800";
    nextButton.disabled = currentPage === totalPages;
    nextButton.innerHTML = '<i data-lucide="chevron-right" class="h-3 w-3"></i>';
    nextButton.addEventListener("click", () => {
      if (currentPage < totalPages) {
        currentPage++;
        render();
      }
    });
    paginationContainer.appendChild(nextButton);
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

  function resetAddressSelect(select, placeholder) {
    if (!select) return;
    select.replaceChildren(new Option(placeholder, ""));
    select.disabled = true;
  }

  function setAddressOptions(select, placeholder, records, valueKey, selected = "") {
    if (!select) return;
    select.replaceChildren(new Option(placeholder, ""));
    records.forEach((record) => {
      select.append(new Option(record.name, record[valueKey]));
    });
    select.disabled = records.length === 0;
    select.value = selected;
  }

  async function getAddressRecords(path) {
    const response = await fetch(path);

    if (!response.ok) {
      throw new Error(`Address API error: ${response.status}`);
    }

    return response.json();
  }

  async function loadProvinces(regionCode, selected = "") {
    const province = $("#province");
    resetAddressSelect($("#city"), "Select City");
    resetAddressSelect($("#barangay"), "Select Barangay");

    if (!regionCode) {
      resetAddressSelect(province, "Select Province");
      return;
    }

    const provinces = await getAddressRecords(`/api/provinces/${regionCode}`);
    setAddressOptions(province, "Select Province", provinces, "province_code", selected);
  }

  async function loadCities(provinceCode, selected = "") {
    const city = $("#city");
    resetAddressSelect($("#barangay"), "Select Barangay");

    if (!provinceCode) {
      resetAddressSelect(city, "Select City");
      return;
    }

    const cities = await getAddressRecords(`/api/cities/${provinceCode}`);
    setAddressOptions(city, "Select City", cities, "city_code", selected);
  }

  async function loadBarangays(cityCode, selected = "") {
    const barangay = $("#barangay");

    if (!cityCode) {
      resetAddressSelect(barangay, "Select Barangay");
      return;
    }

    const barangays = await getAddressRecords(`/api/barangays/${cityCode}`);
    setAddressOptions(barangay, "Select Barangay", barangays, "barangay_code", selected);
  }

  async function populateAddressFields(user = null) {
    const region = $("#region");
    resetAddressSelect($("#province"), "Select Province");
    resetAddressSelect($("#city"), "Select City");
    resetAddressSelect($("#barangay"), "Select Barangay");

    try {
      const regions = await getAddressRecords("/api/regions");
      setAddressOptions(region, "Select Region", regions, "region_code", user?.region || "");

      if (!user?.region) return;

      await loadProvinces(user.region, user.province || "");
      if (!user?.province) return;

      await loadCities(user.province, user.city || "");
      if (!user?.city) return;

      await loadBarangays(user.city, user.barangay || "");
    } catch (error) {
      console.error("Failed to load address options:", error);
      setText("#formFeedback", "Unable to load address options. Please try again.");
    }
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
      middleName: user?.middleName || "",
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
    };
    Object.entries(fields).forEach(([id, value]) => {
      const element = document.getElementById(id);
      if (element) element.value = value;
    });
    syncParentField();
    void populateAddressFields(user);
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
      "middleName",
      "lastName",
      "email",
      "username",
      "role",
      "accountStatus",
      "password",
      "contact",
      "strand",
      "childId",
      "region",
      "province",
      "city",
      "barangay",
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
      middleName: values.middleName,
      lastName: values.lastName,
      email: values.email,
      username: values.username,
      role: values.role,
      status: values.accountStatus,
      contact: values.contact,
      strand: values.strand,
      region: values.region,
      province: values.province,
      city: values.city,
      barangay: values.barangay,
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
    currentPage = 1;
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
    currentPage = 1;
    render();
  }
  function handleAction(action, user) {
    if (action === "details") showDetails(user);
    if (action === "edit") openEditor(user);
    if (action === "toggle") toggleStatus(user);
    if (action === "reset") resetPassword(user);
    if (action === "delete") deleteUser(user);
  }

  function updateBulkDeleteButton() {
    const selectedUsers = users.filter(user => selected.has(user.id) && user.id !== currentUser.id);
    const usersWithRecords = selectedUsers.filter(user => relatedCount(user) > 0);
    const deleteButton = $("[data-bulk-delete]");
    
    if (deleteButton) {
      if (usersWithRecords.length > 0) {
        deleteButton.disabled = true;
        deleteButton.classList.add("opacity-50", "cursor-not-allowed");
        deleteButton.title = `${usersWithRecords.length} account${usersWithRecords.length !== 1 ? 's' : ''} have related records`;
      } else {
        deleteButton.disabled = false;
        deleteButton.classList.remove("opacity-50", "cursor-not-allowed");
        deleteButton.title = "";
      }
    }
  }

  function bulkActivate() {
    users.forEach((user) => {
      if (selected.has(user.id) && user.id !== currentUser.id)
        user.status = "active";
    });
    save(usersKey, users);
    selected.clear();
    APP.toast("Selected accounts activated");
    currentPage = 1;
    render();
  }

  function bulkDeactivate() {
    users.forEach((user) => {
      if (selected.has(user.id) && user.id !== currentUser.id)
        user.status = "inactive";
    });
    save(usersKey, users);
    selected.clear();
    APP.toast("Selected accounts deactivated");
    currentPage = 1;
    render();
  }

  function bulkDelete() {
    const selectedUsers = users.filter(user => selected.has(user.id) && user.id !== currentUser.id);
    
    // Check for users with related records
    const usersWithRecords = selectedUsers.filter(user => relatedCount(user) > 0);
    
    if (usersWithRecords.length > 0) {
      APP.toast(
        `${usersWithRecords.length} account${usersWithRecords.length !== 1 ? 's' : ''} cannot be deleted due to related records. Please deactivate them instead.`,
        "error"
      );
      return;
    }
    
    if (selectedUsers.length === 0) {
      APP.toast("No eligible accounts to delete", "error");
      return;
    }
    
    if (!window.confirm(
      `Delete ${selectedUsers.length} account${selectedUsers.length !== 1 ? 's' : ''}? This action cannot be undone.`
    )) {
      return;
    }
    
    users = users.filter(user => !selected.has(user.id) || user.id === currentUser.id);
    save(usersKey, users);
    selected.clear();
    APP.toast(`${selectedUsers.length} account${selectedUsers.length !== 1 ? 's' : ''} deleted`);
    currentPage = 1;
    render();
  }
  function exportUsers() {
    const visible = visibleUsers();
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
      ...visible.map((user) => [
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
    APP.toast(`CSV export downloaded (${visible.length} users)`);
  }
function generateUserId(role, existingUsers) {
  const prefix =
    role.toLowerCase() === "student"
      ? "STU-2026"
      : role.toLowerCase() === "teacher"
        ? "TCH-2026"
        : role.toLowerCase() === "admin"
          ? "ADM-2026"
          : role.toLowerCase() === "guest"
            ? "GST-2026"
            : "PRT-2026";

  let id;

  do {
    id =
      `${prefix}-` +
      Math.random().toString(36).substring(2, 8).toUpperCase();
  } while (existingUsers.some((user) => user.id === id));

  return id;
}

  async function queueUserImport(importedUsers) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrfToken) {
      throw new Error("CSRF token not found");
    }

    const response = await fetch("/api/portal/users/import", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-CSRF-TOKEN": csrfToken,
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
      body: JSON.stringify({ users: importedUsers }),
    });

    if (!response.ok) {
      throw new Error(`Import failed with status ${response.status}`);
    }

    return response.json();
  }

  function watchImport(batchId, importedCount) {
    const timer = window.setInterval(async () => {
      try {
        const response = await fetch(`/api/portal/users/import/${batchId}`, {
          headers: { Accept: "application/json" },
          credentials: "same-origin",
        });

        if (!response.ok) {
          throw new Error(`Import status failed with status ${response.status}`);
        }

        const batch = await response.json();
        if (batch.failedJobs > 0 || batch.cancelled) {
          window.clearInterval(timer);
          APP.toast("Some users could not be imported. Check the server logs.", "error");
          return;
        }

        if (batch.finished) {
          window.clearInterval(timer);
          APP.toast(`${importedCount} users imported`);
          window.setTimeout(() => location.reload(), 700);
        }
      } catch (error) {
        window.clearInterval(timer);
        console.error("Failed to check import status:", error);
        APP.toast("Unable to check import progress. Refresh the page shortly.", "error");
      }
    }, 1000);
  }

function importUsers() {
  const input = document.createElement("input");

  input.type = "file";
  input.accept = ".csv,text/csv";

  const FIELD_ALIASES = {
    // id: ["id", "userid", "user_id", "user-id", "userId"],
    name: ["name", "fullname", "full_name", "names", "fullnames", "fullName", "FullName",],
    firstname: ["firstname", "first_name", "first", "givenname", "given_name", "firstName"],
    middlename: ["middlename", "middle_name", "middle", "middleName"],
    lastname: ["lastname", "last_name", "last", "familyname", "surname", "lastName"],
    role: ["role", "type", "userrole", "account_type"],
    email: ["email", "emailaddress", "email_address"],
    status: ["status", "accountstatus", "account_status"],
    password: ["password", "pass", "pin", "pincode"],
    contact: ["contact", "phone", "mobilenumber", "mobile_number", "cellphone"],
    username: ["username", "user_name", "login"],
    strand: ["strand", "program", "track", "programstrand", "strandprogram"],
    address: ["address", "homeaddress", "home_address"],
    createdAt: ["created_at", "createdAt"],
    updatedAt: ["updatedAt", "updated_at"],
  };

  const normalizeRole = (value) => {
    const map = {
      administrator: "admin",
      admin: "admin",
      teacher: "teacher",
      faculty: "teacher",
      staff: "teacher",
      instructor: "teacher",
      professor: "teacher",
      student: "student",
      learner: "student",
      students: "student",
      parent: "parent",
      guardian: "parent",
      guest: "guest",
      visitor: "guest",
      viewer: "guest",
    };
    return map[String(value).toLowerCase().replace(/[\s_-]+/g, "")] || "student";
  };

  const normalizeStatus = (value) => {
    const k = String(value).toLowerCase().trim();
    if (["inactive", "0", "no", "false", "disabled"].includes(k)) return "inactive";
    return "active";
  };

  const validEmail = (value) =>
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value).trim());

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

      const parseCsvLine = (line) => {
        const values = [];
        let current = "";
        let inQuotes = false;
        for (let i = 0; i < line.length; i++) {
          const ch = line[i];
          if (inQuotes) {
            if (ch === '"') {
              if (line[i + 1] === '"') {
                current += '"';
                i++;
              } else {
                inQuotes = false;
              }
            } else {
              current += ch;
            }
          } else if (ch === '"') {
            inQuotes = true;
          } else if (ch === ",") {
            values.push(current);
            current = "";
          } else {
            current += ch;
          }
        }
        values.push(current);
        return values.map((value) => value.trim());
      };
      const rows = lines.map(parseCsvLine);

      const colIndex = {};
      rows[0].forEach((header, index) => {
        const key = header
          .trim()
          .toLowerCase()
          .replace(/[^a-z0-9]+/g, "");
        for (const [canon, aliases] of Object.entries(FIELD_ALIASES)) {
          if (aliases.includes(key)) colIndex[canon] = index;
        }
      });

      const cell = (row, canon) => {
        const index = colIndex[canon];
        if (index === undefined || index >= row.length) return "";
        return (row[index] ?? "").trim();
      };

      const importedUsers = [];

      let importedCount = 0;
      let generatedCount = 0;
      let skippedCount = 0;

      rows.slice(1).forEach((row) => {
        const role = normalizeRole(cell(row, "role") || "student");
        let firstName = cell(row, "firstname");
        let lastName = cell(row, "lastname");
        let middleName = cell(row, "middlename");
        const email = validEmail(cell(row, "email"))
          ? cell(row, "email").toLowerCase()
          : "";
        const csvId = cell(row, "id");
        const password = cell(row, "password");

        if ((!firstName || !lastName) && cell(row, "name")) {
          const parts = cell(row, "name").trim().split(/\s+/);
          if (parts.length) {
            firstName = firstName || parts[0];
            if (!lastName && parts.length >= 2) {
              if (parts.length === 2) lastName = parts[1];
              else if (parts.length >= 3) {
                middleName = middleName || parts[1];
                lastName = parts.slice(2).join(" ");
              }
            }
          }
        }
        if (
          !firstName &&
          !lastName &&
          !email &&
          !csvId &&
          !cell(row, "contact") &&
          !cell(row, "username")
        ) {
          skippedCount++;
          return;
        }

        let userId = csvId;
        if (!userId) {
          userId = generateUserId(role, users);
          generatedCount++;
        }

        const newUser = {
          id: userId,
          role: role,
          status: normalizeStatus(cell(row, "status")),
          createdAt: new Date().toISOString(),
          updatedAt: new Date().toISOString(),
        };
        if (firstName) newUser.firstName = firstName;
        if (lastName) newUser.lastName = lastName;
        if (middleName) newUser.middleName = middleName;
        if (email) newUser.email = email;
        if (password && password.length >= 6) newUser.password = password;
        if (cell(row, "contact")) newUser.contact = cell(row, "contact");
        if (cell(row, "username")) newUser.username = cell(row, "username");
        if (cell(row, "strand")) newUser.strand = cell(row, "strand");
        if (cell(row, "address")) newUser.address = cell(row, "address");

        importedUsers.push(newUser);
        importedCount++;
      });

      if (importedUsers.length === 0) {
        APP.toast("CSV file contains no usable users");
        return;
      }

      const nameSource =
        colIndex.name !== undefined &&
        colIndex.firstname === undefined &&
        colIndex.lastname === undefined
          ? "single Name column"
          : colIndex.firstname !== undefined || colIndex.lastname !== undefined
            ? "first/last name columns"
            : "no name column";

      console.info("[User Import]", {
        headers: rows[0],
        colIndex,
        nameSource,
        sample: importedUsers.slice(0, 3),
        skipped: skippedCount,
      });

      queueUserImport(importedUsers)
        .then((result) => {
          if (!result || !result.ok || !result.batchId) {
            throw new Error("Invalid server response");
          }
          APP.toast(
            `${result.users} users queued for import` +
              ` (names from ${nameSource})` +
              (generatedCount > 0 ? `, ${generatedCount} User IDs generated` : "") +
              (skippedCount > 0 ? `, ${skippedCount} empty rows skipped` : ""),
          );
          watchImport(result.batchId, result.users);
        })
        .catch((error) => {
          console.error("Failed to queue user import:", error);
          APP.toast("Unable to queue the user import. Please try again.", "error");
        });
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
    $$("[data-theme-toggle]").forEach((button) =>
      button.addEventListener("click", () => APP.toggleTheme()),
    );
    $$("[data-logout]").forEach((button) =>
      button.addEventListener("click", () => AUTH.logout()),
    );
    $("#q")?.addEventListener("input", () => { currentPage = 1; render(); });
    $("#filter")?.addEventListener("change", () => { currentPage = 1; render(); });
    $("#statusFilter")?.addEventListener("change", () => { currentPage = 1; render(); });
    $("#sortBy")?.addEventListener("change", () => { currentPage = 1; render(); });
    $("#selectAll")?.addEventListener("change", (event) => {
      const visible = visibleUsers();
      const startIndex = (currentPage - 1) * itemsPerPage;
      const endIndex = startIndex + itemsPerPage;
      const paginatedUsers = visible.slice(startIndex, endIndex);
      
      paginatedUsers.forEach((user) => {
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
    
    // Bulk action icon button listeners
    $("[data-bulk-activate]")?.addEventListener("click", bulkActivate);
    $("[data-bulk-deactivate]")?.addEventListener("click", bulkDeactivate);
    $("[data-bulk-delete]")?.addEventListener("click", bulkDelete);
    $("#userForm")?.addEventListener("submit", saveUser);
    $("#role")?.addEventListener("change", syncParentField);
    $("#region")?.addEventListener("change", async (event) => {
      try {
        await loadProvinces(event.target.value);
      } catch (error) {
        console.error("Failed to load provinces:", error);
      }
    });
    $("#province")?.addEventListener("change", async (event) => {
      try {
        await loadCities(event.target.value);
      } catch (error) {
        console.error("Failed to load cities:", error);
      }
    });
    $("#city")?.addEventListener("change", async (event) => {
      try {
        await loadBarangays(event.target.value);
      } catch (error) {
        console.error("Failed to load barangays:", error);
      }
    });
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
