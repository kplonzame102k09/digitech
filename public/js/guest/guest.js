(() => {
  const U = AUTH.requireRole("guest");
  if (!U) return;

  const $ = (sel, root = document) =>
    typeof sel === "string" ? root.querySelector(sel) : sel;
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
  const text = (sel, value, root = document) => {
    const el = $(sel, root);
    if (el) el.textContent = value ?? "";
  };
  const esc = (value) => (window.APP ? APP.esc(value ?? "") : String(value ?? ""));
  const page = () =>
    document.body.dataset.guestPage || location.pathname.split("/").pop();
  const fullName = (user = U) =>
    `${user?.firstName || ""} ${user?.middleName || ""} ${user?.lastName || ""}`.trim() || user?.id || "Guest";

  const get = (key, fallback = []) => DG.getData(key, fallback);
  const save = (key, value) => DG.saveData(key, value);
  const mine = (key) =>
    get(key, []).filter((row) => row.studentId === U.id || row.userId === U.id || row.createdBy === U.id);

  const badge = (status) => {
    const ok = ["Approved", "Enrolled", "Released", "Ready for Release", "Present", "Competent"].includes(status);
    const warn = ["Pending", "Submitted", "Under Review", "Processing", "Draft", "Late", "In Progress"].includes(status);
    const bad = ["Rejected", "Absent", "Failed", "Not Yet Competent"].includes(status);
    const cls = ok
      ? "bg-emerald-50 text-emerald-700"
      : warn
        ? "bg-amber-50 text-amber-700"
        : bad
          ? "bg-rose-50 text-rose-700"
          : "bg-slate-100 text-slate-600";
    return `<span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ${cls}">${esc(status || "—")}</span>`;
  };

  const showError = (message) => {
    APP?.toast?.(message);
    console.error(message);
  };

  const showLoading = (element) => {
    if (element) element.classList.add("opacity-50", "pointer-events-none");
  };

  const hideLoading = (element) => {
    if (element) element.classList.remove("opacity-50", "pointer-events-none");
  };

  function renderDocuments() {
    const docs = mine("documentRequests")
      .sort((a, b) =>
        String(b.requestDate || b.createdAt || "").localeCompare(
          String(a.requestDate || a.createdAt || ""),
        ),
      );
    text("#docTotal", docs.length);
    text(
      "#docPending",
      docs.filter((d) => ["Pending", "Processing", "Submitted"].includes(d.status)).length,
    );
    text(
      "#docReady",
      docs.filter((d) => ["Ready for Release", "Released"].includes(d.status)).length,
    );
    const body = $("#rows");
    if (body) {
      body.innerHTML = docs.length
        ? docs
            .map(
              (d) => `<tr class="border-t border-slate-200 dark:border-slate-800">
                  <td class="p-4 font-semibold">${esc(d.documentType || d.type)}</td>
                  <td class="p-4">${esc(d.purpose || "—")}</td>
                  <td class="p-4">${esc((d.requestDate || d.createdAt || "").slice(0, 10))}</td>
                  <td class="p-4">${badge(d.status)}</td>
                </tr>`,
            )
            .join("")
        : `<tr><td colspan="4" class="p-8 text-center text-slate-500">No document requests yet.</td></tr>`;
    }

    $("#docForm")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const submitButton = event.target.querySelector('button[type="submit"]');
      showLoading(submitButton);

      try {
        const purpose = $("#purpose")?.value.trim() || "";
        if (!purpose) {
          APP?.toast?.("Purpose is required");
          hideLoading(submitButton);
          return;
        }

        const record = {
          id: DG.generateId("DOC"),
          studentId: U.id,
          documentType: $("#type")?.value || "Form 137",
          purpose,
          copies: Number($("#copies")?.value || 1),
          notes: $("#notes")?.value.trim() || "",
          status: "Pending",
          requestDate: new Date().toISOString(),
          createdBy: U.id,
        };

        const all = get("documentRequests", []);
        all.push(record);
        save("documentRequests", all);

        APP?.toast?.("Document request submitted");
        $("#formBox")?.classList.add("hidden");
        $("#docForm")?.reset();
        lucide.createIcons();
        renderDocuments();
      } catch (error) {
        showError(error.message || "Failed to submit request");
      } finally {
        hideLoading(submitButton);
      }
    });
  }

  function renderProfile() {
    text("#profileName", fullName());
    text("#profileId", U.user_id || U.id);
    text("#profileRole", "Guest");
    const email = $("#email");
    if (email) email.value = U.email || "";
    const contact = $("#contact");
    if (contact) contact.value = U.contact || "";
    const address = $("#address");
    if (address) address.value = U.address || "";

    $("#profilePhotoInput")?.addEventListener("change", async (event) => {
      const file = event.target.files?.[0];
      if (!file) return;
      if (file.size > 2 * 1024 * 1024) {
        APP?.toast?.("Photo must be 2 MB or smaller");
        return;
      }
      try {
        await DG.uploadProfilePhoto(file);
        DG.loadProfileElements();
        APP?.toast?.("Profile photo updated");
      } catch (error) {
        showError("Failed to upload photo: " + error.message);
      } finally {
        event.target.value = "";
      }
    });

    $("#profileForm")?.addEventListener("submit", (event) => {
      event.preventDefault();
      const submitButton = event.target.querySelector('button[type="submit"]');
      showLoading(submitButton);
      try {
        const users = get("users", []);
        const user = users.find((item) => item.id === U.id);
        if (!user) {
          APP?.toast?.("Profile not found");
          return;
        }
        user.contact = $("#contact")?.value.trim() || user.contact;
        user.address = $("#address")?.value.trim() || user.address;
        user.updatedAt = new Date().toISOString();
        save("users", users);
        Object.assign(U, user);
        DG.setCurrentUser(U);
        text("#profileName", fullName());
        DG.loadProfileElements();
        APP?.toast?.("Profile saved");
      } catch (error) {
        showError("Failed to save profile: " + error.message);
      } finally {
        hideLoading(submitButton);
      }
    });
  }

  APP?.applyTheme?.();
  APP?.updateNotif?.();
  DG.loadProfileElements();
  $$("[data-theme-toggle]").forEach((button) => {
    if (button.dataset.themeBound) return;
    button.dataset.themeBound = "1";
    button.addEventListener("click", () => APP.toggleTheme());
  });

  (async function init() {
    if (page() === "documents") renderDocuments();
    if (page() === "profile") renderProfile();
    lucide.createIcons();
  })();
})();