(() => {
  const U = AUTH.requireRole("student");
  if (!U) return;

  const $ = (sel, root = document) =>
    typeof sel === "string" ? root.querySelector(sel) : sel;
  const text = (sel, value, root = document) => {
    const el = $(sel, root);
    if (el) el.textContent = value ?? "";
  };
  const get = (key, fallback = []) => DG.getData(key, fallback);
  const save = (key, value) => DG.saveData(key, value);
  const esc = (value) => (window.APP ? APP.esc(value ?? "") : String(value ?? ""));
  const page = () =>
    document.body.dataset.studentPage || location.pathname.split("/").pop();
  const fullName = (user = U) =>
    `${user?.firstName || ""} ${user?.lastName || ""}`.trim() || user?.id || "Student";

  const mine = (key) =>
    get(key, []).filter(
      (row) => row.studentId === U.id || row.userId === U.id,
    );

  const latestEnrollment = () =>
    mine("enrollments").sort((a, b) =>
      String(b.updatedAt || b.createdAt || "").localeCompare(
        String(a.updatedAt || a.createdAt || ""),
      ),
    )[0];

  const publishedGrades = () =>
    mine("grades").filter(
      (g) => g.published === true || g.published === "true" || g.status === "Published",
    );

  const average = (grades) => {
    const nums = grades
      .map((g) => Number(g.grade))
      .filter((n) => Number.isFinite(n));
    if (!nums.length) return null;
    return nums.reduce((a, b) => a + b, 0) / nums.length;
  };

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

  const upsertUserFields = (fields) => {
    Object.assign(U, fields);
    DG.setCurrentUser(U);
    const users = get("users", []);
    const idx = users.findIndex((u) => u.id === U.id);
    if (idx >= 0) {
      users[idx] = { ...users[idx], ...fields };
      save("users", users);
    }
  };

  function renderDashboard() {
    text("#name", U.firstName || fullName());
    const enr = latestEnrollment();
    text("#enrollmentstatus", enr?.status || "Not started");
    const docs = mine("documentRequests");
    text("#documentrequests", docs.length);
    text(
      "#documentrequestsdetail",
      docs.length
        ? `${docs.filter((d) => !["Released", "Rejected"].includes(d.status)).length} open`
        : "No requests yet",
    );
    const grades = publishedGrades();
    const avg = average(grades);
    text("#currentgpa", avg == null ? "—" : avg.toFixed(2));
    text(
      "#currentgpadetail",
      grades.length ? `${grades.length} published subject(s)` : "No published grades yet",
    );
    const comps = mine("competencies");
    const competent = comps.filter((c) => c.status === "Competent").length;
    text(
      "#competencyprogress",
      comps.length ? `${Math.round((competent / comps.length) * 100)}%` : "0%",
    );
    text(
      "#competencydetail",
      comps.length ? `${competent} of ${comps.length} competent` : "No competency records yet",
    );

    const activity = $("#activity");
    if (activity) {
      const notes = get("notifications", [])
        .filter((n) => n.userId === U.id)
        .slice(0, 5);
      activity.innerHTML = notes.length
        ? notes
            .map(
              (n) => `<div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                <b class="text-sm">${esc(n.title)}</b>
                <p class="mt-1 text-xs text-slate-500">${esc(n.message || "")}</p>
              </div>`,
            )
            .join("")
        : `<p class="text-sm text-slate-500">No recent activity yet.</p>`;
    }

    const enrollBox = $("#enroll");
    if (enrollBox) {
      enrollBox.innerHTML = enr
        ? `<p class="text-sm text-slate-600 dark:text-slate-300">Status: <b>${esc(enr.status)}</b></p>
           <p class="mt-2 text-xs text-slate-400">${esc(enr.strand || "")} · ${esc(enr.schoolYear || "")}</p>`
        : `<p class="text-sm text-slate-500">You have not started enrollment yet.</p>`;
    }

    const announce = get("announcements", [])
      .filter((a) => !a.audience || a.audience === "all" || a.audience === "student")
      .slice(0, 4);
    const list = $("#announcementList") || $("#announcements") || $("#recentAnnouncements");
    if (list) {
      list.innerHTML = announce.length
        ? announce
            .map(
              (a) => `<div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                <b class="text-sm">${esc(a.title)}</b>
                <p class="mt-1 text-xs text-slate-500">${esc(a.message || "")}</p>
              </div>`,
            )
            .join("")
        : `<p class="text-sm text-slate-500">No announcements yet.</p>`;
    }
  }

  function renderEnrollment() {
    text("#fullName", fullName());
    const sid = $("#studentId");
    if (sid) sid.value = U.id;
    const contact = $("#contact");
    if (contact && !contact.value) contact.value = U.contact || "";
    const birth = $("#birthDate");
    if (birth && !birth.value) birth.value = (U.birthDate || "").slice(0, 10);
    const address = $("#address");
    if (address && !address.value) {
      address.value =
        U.address ||
        [U.barangay, U.city, U.province].filter(Boolean).join(", ");
    }
    const gName = $("#guardianName");
    if (gName && !gName.value) gName.value = U.guardianName || "";
    const gContact = $("#guardianContact");
    if (gContact && !gContact.value) gContact.value = U.guardianContact || "";

    const enr = latestEnrollment();
    if (enr) {
      ["programType", "gradeLevel", "strand", "track", "schoolYear"].forEach((id) => {
        const el = $(`#${id}`);
        if (el && enr[id]) el.value = enr[id];
      });
      const training = $("#training");
      if (training && enr.trainingLevel) training.value = enr.trainingLevel;
      const status = $("#status");
      if (status) status.innerHTML = badge(enr.status);
    }
  }

  window.saveEnrollment = function saveEnrollment(status) {
    if (!U) return;
    const payload = {
      id: latestEnrollment()?.id || DG.generateId("ENR"),
      studentId: U.id,
      status,
      programType: $("#programType")?.value || "Senior High",
      gradeLevel: $("#gradeLevel")?.value || "",
      strand: $("#strand")?.value || "",
      track: $("#track")?.value || "",
      trainingLevel: $("#training")?.value || "",
      schoolYear: $("#schoolYear")?.value || "2026-2027",
      contact: $("#contact")?.value || U.contact,
      birthDate: $("#birthDate")?.value || U.birthDate,
      address: $("#address")?.value || U.address,
      guardianName: $("#guardianName")?.value || "",
      guardianContact: $("#guardianContact")?.value || "",
      updatedAt: new Date().toISOString(),
      createdAt: latestEnrollment()?.createdAt || new Date().toISOString(),
    };

    if (!payload.gradeLevel || !payload.strand || !payload.guardianName) {
      APP?.toast?.("Please complete required enrollment fields");
      return;
    }

    const rows = get("enrollments", []);
    const idx = rows.findIndex((r) => r.id === payload.id);
    if (idx >= 0) rows[idx] = { ...rows[idx], ...payload };
    else rows.push(payload);
    save("enrollments", rows);

    upsertUserFields({
      contact: payload.contact,
      birthDate: payload.birthDate,
      address: payload.address,
      guardianName: payload.guardianName,
      guardianContact: payload.guardianContact,
      strand: payload.strand,
    });

    const statusEl = $("#status");
    if (statusEl) statusEl.innerHTML = badge(status);
    APP?.toast?.(status === "Draft" ? "Draft saved" : "Enrollment submitted");
    lucide.createIcons();
  };

  function renderGrades() {
    const grades = publishedGrades();
    const avg = average(grades);
    text("#gradeTotal", grades.length);
    text("#gradeAverage", avg == null ? "—" : avg.toFixed(2));
    text("#gradeYear", get("settings", {})?.schoolYear || grades[0]?.schoolYear || "—");
    const body = $("#rows");
    if (!body) return;
    body.innerHTML = grades.length
      ? grades
          .map(
            (g) => `<tr class="border-t border-slate-200 dark:border-slate-800">
              <td class="p-4 font-semibold">${esc(g.subject || "—")}</td>
              <td class="p-4">${esc(g.code || "—")}</td>
              <td class="p-4">${esc(g.teacher || g.teacherId || "—")}</td>
              <td class="p-4">${esc(g.term || g.period || "—")}</td>
              <td class="p-4">${esc(g.schoolYear || "—")}</td>
              <td class="p-4 font-bold">${esc(g.grade ?? "—")}</td>
              <td class="p-4">${badge(g.remarks || (Number(g.grade) >= 75 ? "Passed" : "Failed"))}</td>
            </tr>`,
          )
          .join("")
      : `<tr><td colspan="7" class="p-8 text-center text-slate-500">No published grades yet.</td></tr>`;
  }

  function renderDocuments() {
    const docs = mine("documentRequests").sort((a, b) =>
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

    $("#docForm")?.addEventListener("submit", (event) => {
      event.preventDefault();
      const row = {
        id: DG.generateId("DOC"),
        studentId: U.id,
        documentType: $("#type")?.value || "Form 137",
        purpose: $("#purpose")?.value || "",
        copies: Number($("#copies")?.value || 1),
        notes: $("#notes")?.value || "",
        status: "Pending",
        requestDate: new Date().toISOString(),
        createdBy: U.id,
      };
      if (!row.purpose) {
        APP?.toast?.("Purpose is required");
        return;
      }
      const all = get("documentRequests", []);
      all.push(row);
      save("documentRequests", all);
      $("#formBox")?.classList.add("hidden");
      event.target.reset();
      APP?.toast?.("Document request submitted");
      renderDocuments();
    });
  }

  function renderCompetencies() {
    const rows = mine("competencies");
    const body = $("#rows");
    if (!body) return;
    body.innerHTML = rows.length
      ? rows
          .map(
            (c) => `<tr class="border-t border-slate-200 dark:border-slate-800">
              <td class="p-4 font-semibold">${esc(c.competency)}</td>
              <td class="p-4">${esc(c.qualification || "—")}</td>
              <td class="p-4">${badge(c.status)}</td>
              <td class="p-4">${esc(c.assessor || "—")}</td>
              <td class="p-4">${esc((c.assessmentDate || "").toString().slice(0, 10) || "—")}</td>
            </tr>`,
          )
          .join("")
      : `<tr><td colspan="5" class="p-8 text-center text-slate-500">No competencies recorded yet.</td></tr>`;
  }

  function renderRequirements() {
    const rows = mine("requirements");
    text("#reqTotal", rows.length);
    text("#reqPending", rows.filter((r) => r.status === "Pending").length);
    text("#reqSubmitted", rows.filter((r) => ["Submitted", "Approved"].includes(r.status)).length);
    const body = $("#rows");
    if (!body) return;
    body.innerHTML = rows.length
      ? rows
          .map(
            (r) => `<tr class="border-t border-slate-200 dark:border-slate-800">
              <td class="p-4 font-semibold">${esc(r.name)}${r.type ? ` <span class="text-xs text-slate-400">(${esc(r.type)})</span>` : ""}</td>
              <td class="p-4">${badge(r.status)}</td>
              <td class="p-4">${esc((r.submittedAt || "").toString().slice(0, 10) || "—")}</td>
              <td class="p-4 text-right">
                ${
                  r.status === "Pending"
                    ? `<button data-submit-req="${esc(r.id)}" class="text-emerald-700 font-semibold">Mark submitted</button>`
                    : "—"
                }
              </td>
            </tr>`,
          )
          .join("")
      : `<tr><td colspan="4" class="p-8 text-center text-slate-500">No requirements assigned yet.</td></tr>`;

    body.querySelectorAll("[data-submit-req]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const all = get("requirements", []);
        const idx = all.findIndex((r) => r.id === btn.dataset.submitReq);
        if (idx < 0) return;
        all[idx] = {
          ...all[idx],
          status: "Submitted",
          submittedAt: new Date().toISOString(),
        };
        save("requirements", all);
        APP?.toast?.("Requirement marked as submitted");
        renderRequirements();
      });
    });
  }

  function renderProfile() {
    text("#profileName", fullName());
    text("#profileId", U.id);
    text("#profileRole", "Student");
    const email = $("#email");
    if (email) email.value = U.email || "";
    ["contact", "guardianName", "guardianContact", "address"].forEach((id) => {
      const el = $(`#${id}`);
      if (el) el.value = U[id] || "";
    });

    $("#profilePhotoInput")?.addEventListener("change", (event) => {
      const file = event.target.files?.[0];
      if (!file) return;
      if (file.size > 2 * 1024 * 1024) {
        APP?.toast?.("Photo must be 2 MB or smaller");
        return;
      }
      const reader = new FileReader();
      reader.onload = () => {
        DG.setProfilePhoto(reader.result, U);
        APP?.toast?.("Profile photo updated");
        DG.loadProfileElements();
      };
      reader.readAsDataURL(file);
    });

    $("#profileForm")?.addEventListener("submit", (event) => {
      event.preventDefault();
      upsertUserFields({
        contact: $("#contact")?.value || "",
        guardianName: $("#guardianName")?.value || "",
        guardianContact: $("#guardianContact")?.value || "",
        address: $("#address")?.value || "",
      });
      APP?.toast?.("Profile saved");
      text("#profileName", fullName());
      DG.loadProfileElements();
    });
  }

  APP?.applyTheme?.();
  APP?.updateNotif?.();
  DG.loadProfileElements();

  const current = page();
  if (current === "dashboard") renderDashboard();
  if (current === "enrollment") renderEnrollment();
  if (current === "grades") renderGrades();
  if (current === "documents") renderDocuments();
  if (current === "competencies") renderCompetencies();
  if (current === "requirements") renderRequirements();
  if (current === "profile") renderProfile();
  lucide.createIcons();
})();
