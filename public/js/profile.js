(function () {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) =>
    Array.from(root.querySelectorAll(selector));
  const text = (selector, value, root = document) => {
    const element =
      typeof selector === "string" ? $(selector, root) : selector;
    if (element) element.textContent = value ?? "";
    return element;
  };

  const fullName = (user) =>
    `${user?.firstName || ""} ${user?.middleName || ""} ${user?.lastName || ""}`
      .split(/\s+/)
      .filter(Boolean)
      .join(" ") || user?.id ||
    "Member";
  const roleLabel = (role) =>
    ({
      admin: "Administrator",
      teacher: "Faculty",
      student: "Student",
      parent: "Parent",
      guest: "Guest",
    })[role] || "Member";
  const toast = (message, type = "success") => {
    if (APP?.toast) APP.toast(message, type);
    else alert(message);
  };

  const U = DG.getCurrentUser();
  if (!U) {
    window.location.href = "/login";
    return;
  }

  function freshUser() {
    const record = DG.getData("users", []).find((user) => user?.id === U.id);
    return record ? { ...record, ...U } : U;
  }

  function renderIdentity(user) {
    const email = user.email || "";
    const contact = user.contact || "";
    text("#profileName", fullName(user));
    text("#profileId", user.id || "");
    text("#profileRole", `${roleLabel(user.role)} (${user.id || ""})`);
    text("#profileStatus", user.status === "inactive" ? "Inactive" : "Active");
    if (user.mustChangePassword) {
      text("#profileStatus", "Password change required");
    }
    text("#memberSince", APP?.formatDate?.(user.createdAt) || "—");
    text("#profileEmailQuick", email || "—");
    text("#profilePhoneQuick", contact || "—");
  }

  function populateFields(user) {
    $$("[data-profile-field]").forEach((input) => {
      const key = input.dataset.profileField;
      const value = user[key];
      if (input.type === "date") {
        input.value = (value || "").toString().slice(0, 10);
      } else {
        input.value = value === null || value === undefined ? "" : value;
      }
    });
  }

  async function saveDetails(event) {
    event.preventDefault();
    const user = freshUser();
    const submitButton = $("[data-profile-save]");
    const setLoading = (busy) => {
      if (!submitButton) return;
      submitButton.disabled = busy;
      submitButton.classList.toggle("opacity-60", busy);
      submitButton.classList.toggle("pointer-events-none", busy);
    };

    const changed = {};
    const needsTrim = new Set(["firstName", "lastName", "middleName", "email"]);
    $$("[data-profile-field]").forEach((input) => {
      const key = input.dataset.profileField;
      const raw = input.value ?? "";
      const value = needsTrim.has(key) ? raw.trim() : raw;
      const current = user[key] === null || user[key] === undefined ? "" : String(user[key]);
      if (value !== current) changed[key] = value;
    });

    if (Object.keys(changed).length === 0) {
      toast("No changes to save");
      return;
    }

    setLoading(true);
    try {
      if (user.role === "admin" || user.role === "student") {
        const response = user.role === "admin"
          ? await API.admin.profile.update(changed)
          : await API.student.profile.update(changed);
        if (!response.ok) throw new Error(response.error || "Failed to save profile");
        Object.assign(U, response.user);
        DG.setCurrentUser(U);
      } else {
        const users = DG.getData("users", []);
        const record = users.find((item) => item?.id === U.id);
        if (!record) throw new Error("Profile record not found");
        Object.assign(record, changed);
        DG.saveData("users", users);
        Object.assign(U, changed);
        DG.setCurrentUser(U);
        if (DG.flushSync) DG.flushSync();
      }
      renderIdentity(freshUser());
      populateFields(freshUser());
      DG.loadProfileElements();
      toast("Profile saved");
    } catch (error) {
      toast(error.message || "Failed to save profile", "error");
    } finally {
      setLoading(false);
    }
  }

  function wirePhotoUpload() {
    const input = $("#profilePhotoInput");
    if (!input) return;
    input.addEventListener("change", async () => {
      const file = input.files?.[0];
      if (!file) return;
      if (file.size > 2 * 1024 * 1024) {
        toast("Photo must be 2 MB or smaller", "error");
        input.value = "";
        return;
      }
      try {
        await DG.uploadProfilePhoto(file);
        DG.loadProfileElements();
        const photo = new URL(DG.getProfilePhoto(freshUser()), location.origin).href;
        $$("[data-profile-photo]").forEach((img) => (img.src = photo));
        toast("Profile photo updated");
      } catch (error) {
        toast("Failed to upload photo: " + error.message, "error");
      } finally {
        input.value = "";
      }
    });
  }

  async function changePassword(event) {
    event.preventDefault();
    const current = $("#currentPassword")?.value || "";
    const next = $("#newPassword")?.value || "";
    const confirm = $("#newPasswordConfirm")?.value || "";
    const errorBox = $("#passwordError");
    const setError = (message) => {
      if (errorBox) {
        errorBox.textContent = message || "";
        errorBox.classList.toggle("hidden", !message);
      }
    };

    setError("");
    if (!current) {
      setError("Enter your current password.");
      return;
    }
    if (next.length < 12) {
      setError("New password must be at least 12 characters.");
      return;
    }
    if (next === current) {
      setError("New password must be different from your current password.");
      return;
    }
    if (next !== confirm) {
      setError("New password and confirmation do not match.");
      return;
    }

    const button = $('#changePasswordBtn');
    if (button) {
      button.disabled = true;
      button.classList.add("opacity-60", "pointer-events-none");
    }

    try {
      const response = await fetch("/api/portal/account/password", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || "",
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
        body: JSON.stringify({
          current_password: current,
          password: next,
          password_confirmation: confirm,
        }),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data.ok) {
        throw new Error(data.error || "Failed to change password");
      }
      Object.assign(U, data.user || {});
      DG.setCurrentUser(U);
      $("#currentPassword").value = "";
      $("#newPassword").value = "";
      $("#newPasswordConfirm").value = "";
      renderIdentity(freshUser());
      APP.updateNotif();
      toast("Password updated");
    } catch (error) {
      setError(error.message || "Failed to change password");
    } finally {
      if (button) {
        button.disabled = false;
        button.classList.remove("opacity-60", "pointer-events-none");
      }
    }
  }

  const activityStyle = {
    login: { icon: "log-in", label: "Signed in", cls: "text-blue-600 bg-blue-50" },
    logout: { icon: "log-out", label: "Signed out", cls: "text-slate-600 bg-slate-100" },
    "password.changed": { icon: "key-round", label: "Password changed", cls: "text-purple-600 bg-purple-50" },
    "profile.updated": { icon: "user-round-check", label: "Profile updated", cls: "text-emerald-600 bg-emerald-50" },
  };
  const defaultActivity = { icon: "activity", label: "Account activity", cls: "text-slate-600 bg-slate-100" };

  async function renderActivity() {
    const list = $("#activityList");
    const empty = $("#activityEmpty");
    if (!list) return;

    let entries = [];
    try {
      const response = await fetch("/api/portal/account/activity", {
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin",
      });
      const data = await response.json().catch(() => ({}));
      entries = Array.isArray(data.activity) ? data.activity : [];
    } catch (_) {
      entries = [];
    }

    list.replaceChildren();
    if (entries.length === 0) {
      setHidden(empty, false);
      return;
    }
    setHidden(empty, true);

    entries.forEach((entry) => {
      const style = activityStyle[entry.action] || defaultActivity;
      const when = entry.date ? new Date(entry.date) : null;
      const date = when ? APP.formatDate?.(when) || "—" : "—";
      const time = when && !Number.isNaN(when.getTime())
        ? when.toLocaleTimeString(undefined, { hour: "numeric", minute: "2-digit" })
        : "";

      const row = document.createElement("div");
      row.className = "flex items-start gap-4 rounded-2xl border border-slate-200 p-4 dark:border-slate-800";
      row.innerHTML = `
        <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${style.cls}">
          <i data-lucide="${style.icon}" class="h-4 w-4"></i>
        </span>
        <div class="min-w-0 flex-1">
          <p class="font-semibold">${escHtml(style.label)}</p>
          <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">${escHtml(entry.notes || entry.action || "Portal activity")}</p>
        </div>
        <div class="shrink-0 text-right">
          <p class="text-xs font-medium text-slate-500 dark:text-slate-400">${escHtml(date)}</p>
          ${time ? `<p class="mt-0.5 text-[11px] text-slate-400">${escHtml(time)}</p>` : ""}
        </div>`;
      list.append(row);
    });

    if (window.lucide) lucide.createIcons();
  }

  function escHtml(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function setHidden(element, hidden) {
    element?.classList.toggle("hidden", hidden);
  }

  function wireTabs() {
    $$("[data-profile-tab]").forEach((button) => {
      button.addEventListener("click", () => {
        const target = button.dataset.profileTab;
        $$("[data-profile-tab]").forEach((tab) => {
          const active = tab.dataset.profileTab === target;
          tab.classList.toggle("font-semibold", active);
          tab.classList.toggle("text-slate-900", active);
          tab.classList.toggle("border-slate-900", active);
          tab.classList.toggle("dark:text-white", active);
          tab.classList.toggle("dark:border-white", active);
          tab.classList.toggle("text-slate-500", !active);
          tab.classList.toggle("border-transparent", !active);
          tab.setAttribute("aria-selected", active ? "true" : "false");
        });
        $$("[data-profile-panel]").forEach((panel) => {
          const active = panel.dataset.profilePanel === target;
          setHidden(panel, !active);
          if (active && panel.dataset.profilePanel === "activity" && !panel.dataset.rendered) {
            panel.dataset.rendered = "1";
            renderActivity();
          }
        });
      });
    });
  }

  function wireShell() {
    $("#open")?.addEventListener("click", () => $("#side")?.classList.toggle("-translate-x-full"));
  }

  function init() {
    const user = freshUser();
    renderIdentity(user);
    populateFields(user);
    wirePhotoUpload();
    wireTabs();
    wireShell();
$("#profileForm")?.addEventListener("submit", saveDetails);
      $("#passwordForm")?.addEventListener("submit", changePassword);
      APP?.applyTheme?.();
      APP?.updateNotif?.();
      DG.loadProfileElements();
      window.lucide?.createIcons?.();
  }

  function boot() {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", init);
    } else {
      init();
    }
  }
  boot();
})();