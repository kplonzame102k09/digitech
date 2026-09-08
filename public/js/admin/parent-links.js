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
              <td class="p-4"><span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold">${esc(req.status)}</span></td>
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
    const req = { ...requests[idx], status, reviewedAt: new Date().toISOString(), reviewedBy: admin.id };
    requests[idx] = req;
    save("parentLinkRequests", requests);

    if (status === "Approved") {
      const users = get("users", []);
      const pIdx = users.findIndex((u) => u.id === req.parentId);
      if (pIdx >= 0) {
        const parent = { ...users[pIdx] };
        const childIds = Array.isArray(parent.childIds) ? [...parent.childIds] : [];
        if (!childIds.includes(req.studentId)) childIds.push(req.studentId);
        parent.childId = parent.childId || req.studentId;
        parent.childIds = childIds;
        users[pIdx] = parent;
        save("users", users);
      }
    }

    APP?.toast?.(`Request ${status.toLowerCase()}`);
    render();
  }

  APP?.applyTheme?.();
  APP?.updateNotif?.();
  render();
  lucide.createIcons();
})();
