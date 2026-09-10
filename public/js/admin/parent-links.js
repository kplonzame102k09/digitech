(() => {
  const admin = AUTH.requireRole("admin");
  if (!admin) return;

  const $ = (sel, root = document) => root.querySelector(sel);
  const get = (key, fallback = []) => DG.getData(key, fallback);
  const save = (key, value) => DG.saveData(key, value);
  const esc = (v) => (window.APP ? APP.esc(v ?? "") : String(v ?? ""));
  const nameOf = (user) =>
    user
      ? `${user.firstName || ""} ${user.lastName || ""}`.trim() || user.id
      : "Unknown";

  function render() {
    const rows = get("parentLinkRequests", []).sort((a, b) =>
      String(b.createdAt || "").localeCompare(String(a.createdAt || "")),
    );
    const body = $("#rows");
    if (!body) return;

    body.innerHTML = rows.length
      ? rows
          .map((req) => {
            const parent = get("users").find((u) => u.id === req.parentId);
            const student = get("users").find((u) => u.id === req.studentId);
            const actions =
              req.status === "Pending"
                ? `<button data-approve="${esc(req.id)}" class="mr-2 font-semibold text-emerald-700">Approve</button>
                   <button data-reject="${esc(req.id)}" class="font-semibold text-rose-600">Reject</button>`
                : "—";
            return `<tr class="border-t border-slate-200 dark:border-slate-800">
              <td class="p-4">
                <b class="block">${esc(nameOf(parent))}</b>
                <span class="text-xs text-slate-400">${esc(req.parentId)}</span>
              </td>
              <td class="p-4">
                <b class="block">${esc(nameOf(student))}</b>
                <span class="text-xs text-slate-400">${esc(req.studentId)}</span>
              </td>
              <td class="p-4">${esc((req.createdAt || "").slice(0, 10) || "—")}</td>
              <td class="p-4"><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold">${esc(req.status)}</span></td>
              <td class="p-4">${actions}</td>
            </tr>`;
          })
          .join("")
      : `<tr><td colspan="5" class="p-8 text-center text-slate-500">No parent link requests yet.</td></tr>`;

    body.querySelectorAll("[data-approve]").forEach((btn) => {
      btn.addEventListener("click", () => decide(btn.dataset.approve, "Approved"));
    });
    body.querySelectorAll("[data-reject]").forEach((btn) => {
      btn.addEventListener("click", () => decide(btn.dataset.reject, "Rejected"));
    });
  }

  function decide(id, status) {
    const requests = get("parentLinkRequests", []);
    const idx = requests.findIndex((r) => r.id === id);
    if (idx < 0) return;
    const users = get("users", []);
    const parent = users.find((u) => u.id === requests[idx].parentId);
    const student = users.find((u) => u.id === requests[idx].studentId);
    const roleIssues = [
      parent?.role && parent.role !== "parent" ? `${parent.id} is not a parent account` : "",
      student?.role && student.role !== "student" ? `${student.id} is not a student account` : "",
    ].filter(Boolean);
    if (roleIssues.length && status === "Approved") {
      APP?.toast?.(`Cannot approve: ${roleIssues.join(" · ")}`, "error");
      return;
    }
    const req = { ...requests[idx], status, reviewedAt: new Date().toISOString(), reviewedBy: admin.id };
    requests[idx] = req;
    save("parentLinkRequests", requests);

    if (status === "Approved") {
      const pIdx = users.findIndex((u) => u.id === req.parentId);
      if (pIdx >= 0) {
        const parentUser = { ...users[pIdx] };
        const childIds = Array.isArray(parentUser.childIds) ? [...parentUser.childIds] : [];
        if (!childIds.includes(req.studentId)) childIds.push(req.studentId);
        parentUser.childId = parentUser.childId || req.studentId;
        parentUser.childIds = childIds;
        users[pIdx] = parentUser;
        save("users", users);
      }
    }

    APP?.notifyUsers?.(
      [req.parentId],
      `Child link ${status.toLowerCase()}`,
      status === "Approved"
        ? `Your link request to ${req.studentId} was approved.`
        : `Your link request to ${req.studentId} was rejected.`,
      "parentLinkRequest",
      req.id,
    );

    APP?.toast?.(`Request ${status.toLowerCase()}`);
    render();
    APP?.updateNotif?.();
  }

  APP?.applyTheme?.();
  APP?.updateNotif?.();
  render();
  lucide.createIcons();
})();
