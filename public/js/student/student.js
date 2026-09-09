(() => {
  const U = AUTH.requireRole("student");
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
    document.body.dataset.studentPage || location.pathname.split("/").pop();
  const fullName = (user = U) =>
    `${user?.firstName || ""} ${user?.middleName || ""} ${user?.lastName || ""}`.trim() || user?.id || "Student";

  const get = (key, fallback = []) => DG.getData(key, fallback);
  const save = (key, value) => DG.saveData(key, value);
  const mine = (key) =>
    get(key, []).filter((row) => row.studentId === U.id || row.userId === U.id);

  // Cache for API responses
  const cache = {
    enrollments: null,
    grades: null,
    documentRequests: null,
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

  const average = (grades) => {
    const nums = grades
      .map((g) => Number(g.grade))
      .filter((n) => Number.isFinite(n));
    if (!nums.length) return null;
    return nums.reduce((a, b) => a + b, 0) / nums.length;
  };

  const showError = (message) => {
    APP?.toast?.(message);
    console.error(message);
  };

  const showLoading = (element) => {
    if (element) {
      element.classList.add('opacity-50', 'pointer-events-none');
    }
  };

  const hideLoading = (element) => {
    if (element) {
      element.classList.remove('opacity-50', 'pointer-events-none');
    }
  };

  async function renderDashboard() {
    text("#name", U.firstName || fullName());
    
    try {
      // Load enrollments from API
      const enrollmentsResponse = await API.student.enrollments.list();
      cache.enrollments = enrollmentsResponse.enrollments || [];
      
      const enr = cache.enrollments[0]; // Get latest enrollment
      text("#enrollmentstatus", enr?.status || "Not started");
      
      const enrollBox = $("#enroll");
      if (enrollBox) {
        enrollBox.innerHTML = enr
          ? `<p class="text-sm text-slate-600 dark:text-slate-300">Status: <b>${esc(enr.status)}</b></p>
             <p class="mt-2 text-xs text-slate-400">${esc(enr.strand || "")} · ${esc(enr.schoolYear || "")}</p>`
          : `<p class="text-sm text-slate-500">You have not started enrollment yet.</p>`;
      }
    } catch (error) {
      showError("Failed to load enrollment data");
      text("#enrollmentstatus", "Error loading data");
    }

    try {
      // Load document requests from API
      const docsResponse = await API.student.documentRequests.list();
      cache.documentRequests = docsResponse.documentRequests || [];
      
      const docs = cache.documentRequests;
      text("#documentrequests", docs.length);
      text(
        "#documentrequestsdetail",
        docs.length
          ? `${docs.filter((d) => !["Released", "Rejected"].includes(d.status)).length} open`
          : "No requests yet",
      );
    } catch (error) {
      showError("Failed to load document requests");
      text("#documentrequests", "Error");
    }

    try {
      // Load grades from API
      const gradesResponse = await API.student.grades.list();
      cache.grades = gradesResponse.grades || [];
      
      const grades = cache.grades;
      const activeSemester = currentSemester();
      const avg = semesterAverage(
        grades.filter((g) => (g.semester || "1st Semester") === activeSemester),
      );
      text("#currentgpa", avg == null ? "—" : avg.toFixed(2));
      text(
        "#currentgpadetail",
        grades.length ? `${grades.length} published subject(s)` : "No published grades yet",
      );
    } catch (error) {
      showError("Failed to load grades");
      text("#currentgpa", "Error");
    }

    // Competencies load from the MySQL-backed boot collection
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

    // Notifications load from the MySQL-backed boot collection
    const activity = $("#activity");
    if (activity) {
      const notes = DG.getData("notifications", [])
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

    // Announcements load from the MySQL-backed boot collection
    const announce = DG.getData("announcements", [])
      .filter((a) => !a.audience || /^(all|students?)$/i.test(String(a.audience)))
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

  async function renderEnrollment() {
    text("#fullName", fullName());
    const sid = $("#studentId");
    if (sid) sid.value = U.user_id || U.id || "";
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

    try {
      // Load enrollments from API if not cached
      if (!cache.enrollments) {
        const enrollmentsResponse = await API.student.enrollments.list();
        cache.enrollments = enrollmentsResponse.enrollments || [];
      }

      const enr = cache.enrollments[0]; // Get latest enrollment
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
    } catch (error) {
      showError("Failed to load enrollment data");
    }
  }

  window.saveEnrollment = async function saveEnrollment(status) {
    if (!U) return;
    
    const buttonId = status === "Draft" ? "#saveDraftBtn" : "#submitEnrollmentBtn";
    const saveButton = $(buttonId);
    showLoading(saveButton);

    try {
      const payload = {
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
      };

      if (!payload.gradeLevel || !payload.strand || !payload.guardianName) {
        APP?.toast?.("Please complete required enrollment fields");
        hideLoading(saveButton);
        return;
      }

      let response;
      const enr = cache.enrollments?.[0]; // Get latest enrollment
      
      if (enr) {
        // Update existing enrollment
        response = await API.student.enrollments.update(enr.id, payload);
      } else {
        // Create new enrollment
        response = await API.student.enrollments.create(payload);
      }

      if (response.ok) {
        // Refresh cache
        const enrollmentsResponse = await API.student.enrollments.list();
        cache.enrollments = enrollmentsResponse.enrollments || [];

        // Update local user object
        Object.assign(U, {
          contact: payload.contact,
          birthDate: payload.birthDate,
          address: payload.address,
          guardianName: payload.guardianName,
          guardianContact: payload.guardianContact,
          strand: payload.strand,
        });
        DG.setCurrentUser(U);

        const statusEl = $("#status");
        if (statusEl) statusEl.innerHTML = badge(status);
        APP?.toast?.(status === "Draft" ? "Draft saved" : "Enrollment submitted");
        lucide.createIcons();
      } else {
        showError(response.error || "Failed to save enrollment");
      }
    } catch (error) {
      showError("Failed to save enrollment: " + error.message);
    } finally {
      hideLoading(saveButton);
    }
  };

  const currentSemester = () => "1st Semester";
  const gradeTerm = (g, term) => {
    const v = g && g[term];
    return v === null || v === undefined || v === "" ? null : Number(v);
  };
  const weightedFinal = (g) => {
    const terms = ["prelim", "midterm", "finals"];
    const values = terms.map((t) => gradeTerm(g, t));
    if (values.every((v) => v === null)) {
      const f = g?.finalGrade;
      return f === null || f === undefined || f === "" ? null : Number(f);
    }
    return Math.round((values[0] * 0.2 + values[1] * 0.3 + values[2] * 0.5) * 100) / 100;
  };
  const semesterAverage = (list) => {
    const eligible = list.filter((g) => weightedFinal(g) !== null && Number(g?.units || 0) > 0);
    if (!eligible.length) return null;
    const totalUnits = eligible.reduce((sum, g) => sum + Number(g.units), 0);
    return Math.round(eligible.reduce((sum, g) => sum + weightedFinal(g) * Number(g.units), 0) / totalUnits * 100) / 100;
  };
  const chedGwa = (avg) => {
    if (avg === null || avg === undefined) return null;
    if (avg >= 97) return 1.0;
    if (avg >= 94) return 1.25;
    if (avg >= 91) return 1.5;
    if (avg >= 88) return 1.75;
    if (avg >= 85) return 2.0;
    if (avg >= 82) return 2.25;
    if (avg >= 79) return 2.5;
    if (avg >= 76) return 2.75;
    if (avg >= 75) return 3.0;
    return 5.0;
  };
  const gwaLabel = (value) => (value === null || value === undefined ? "—" : value.toFixed(2));
  const gradeCell = (g, term) => {
    const v = gradeTerm(g, term);
    return v === null ? '<span class="text-slate-300 dark:text-slate-600">—</span>' : v.toFixed(2);
  };

  async function renderGrades() {
    try {
      const summaryResponse = await API.student.grades.summary();
      const allGrades = summaryResponse.grades || [];
      cache.grades = allGrades;
      let active = currentSemester();
      const tabs = $$("#semesterTabs [data-tab]");
      const reload = () => {
        const grades = allGrades.filter((g) => (g.semester || "1st Semester") === active);
        const avg = semesterAverage(grades);
        const gwa = chedGwa(avg);
        const year = summaryResponse.schoolYear || grades[0]?.schoolYear || allGrades[0]?.schoolYear || DG.getData("settings", {})?.schoolYear || "—";
        text("#gradeTotal", grades.length);
        text("#gradeAverage", avg == null ? "—" : avg.toFixed(2));
        text("#gradeGwa", gwaLabel(gwa));
        tabs.forEach((tab) => {
          const isActive = tab.dataset.tab === active;
          tab.classList.toggle("bg-blue-600", isActive);
          tab.classList.toggle("text-white", isActive);
          tab.classList.toggle("border-blue-600", isActive);
          tab.classList.toggle("border-slate-200", !isActive);
          tab.classList.toggle("dark:border-slate-700", !isActive);
        });
        const body = $("#rows");
        if (!body) return;
        body.innerHTML = grades.length
          ? grades
              .map((g) => {
                const final = weightedFinal(g);
                const remark = g.remarks || (final === null ? "Pending" : final >= 75 ? "Passed" : "Failed");
                return `<tr class="border-t border-slate-200 dark:border-slate-800">
                <td class="p-4 font-semibold">${esc(g.subject || "—")}</td>
                <td class="p-4 text-center">${esc(g.units ?? 1)}</td>
                <td class="p-4 text-center">${gradeCell(g, "prelim")}</td>
                <td class="p-4 text-center">${gradeCell(g, "midterm")}</td>
                <td class="p-4 text-center">${gradeCell(g, "finals")}</td>
                <td class="p-4 text-center font-bold">${final === null ? "—" : final.toFixed(2)}</td>
                <td class="p-4">${badge(remark)}</td>
              </tr>`;
              })
              .join("")
          : `<tr><td colspan="7" class="p-8 text-center text-slate-500">No published grades for this semester yet.</td></tr>`;
        const annual = summaryResponse.annual || {};
        const annualReady = annual.annualGeneralAverage !== null && annual.firstSemester?.generalAverage !== null;
        $("#annualSection")?.classList.toggle("hidden", !annualReady);
        const fmt = (value) => (value === null || value === undefined ? "—" : Number(value).toFixed(2));
        text("#annualFirstGwa", fmt(annual.firstSemester?.gwa));
        text("#annualSecondGwa", fmt(annual.secondSemester?.gwa));
        text("#annualAverage", fmt(annual.annualGeneralAverage));
        text("#annualGwa", fmt(annual.annualGwa));
        lucide.createIcons();
      };
      tabs.forEach((tab) => tab.addEventListener("click", () => {
        active = tab.dataset.tab;
        reload();
      }));
      reload();
    } catch (error) {
      showError("Failed to load grades");
      const body = $("#rows");
      if (body) {
        body.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-slate-500">Error loading grades.</td></tr>`;
      }
    }
  }

  async function renderDocuments() {
    try {
      // Load document requests from API if not cached
      if (!cache.documentRequests) {
        const docsResponse = await API.student.documentRequests.list();
        cache.documentRequests = docsResponse.documentRequests || [];
      }

      const docs = cache.documentRequests.sort((a, b) =>
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
    } catch (error) {
      showError("Failed to load document requests");
      const body = $("#rows");
      if (body) {
        body.innerHTML = `<tr><td colspan="4" class="p-8 text-center text-slate-500">Error loading document requests.</td></tr>`;
      }
    }

    $("#docForm")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const submitButton = event.target.querySelector('button[type="submit"]');
      showLoading(submitButton);

      try {
        const payload = {
          documentType: $("#type")?.value || "Form 137",
          purpose: $("#purpose")?.value || "",
          copies: Number($("#copies")?.value || 1),
          notes: $("#notes")?.value || "",
        };

        if (!payload.purpose) {
          APP?.toast?.("Purpose is required");
          hideLoading(submitButton);
          return;
        }

        const response = await API.student.documentRequests.create(payload);

        if (response.ok) {
          // Refresh cache
          const docsResponse = await API.student.documentRequests.list();
          cache.documentRequests = docsResponse.documentRequests || [];

          $("#formBox")?.classList.add("hidden");
          event.target.reset();
          APP?.toast?.("Document request submitted");
          renderDocuments();
        } else {
          showError(response.error || "Failed to submit document request");
        }
      } catch (error) {
        showError("Failed to submit document request: " + error.message);
      } finally {
        hideLoading(submitButton);
      }
    });
  }

  function renderCompetencies() {
    const rows = mine("competencies");
    const competent = rows.filter((c) => c.status === "Competent").length;
    const remaining = rows.length - competent;
    const pct = rows.length ? Math.round((competent / rows.length) * 100) : 0;
    text("#pct", `${pct}%`);
    text("#competentCount", competent);
    text("#remainingCount", remaining);
    text("#progressLabel", `${competent} of ${rows.length}`);
    const bar = $("#bar");
    if (bar) bar.style.width = `${pct}%`;
    const cards = $("#cards");
    if (!cards) return;
    cards.innerHTML = rows.length
      ? rows
          .map((c) => {
            const status = c.status || "Not Started";
            const color =
              status === "Competent"
                ? "bg-emerald-500"
                : status === "In Progress"
                  ? "bg-amber-500"
                  : status === "Not Yet Competent"
                    ? "bg-rose-500"
                    : "bg-slate-300";
            const mini = (label, value) =>
              `<div class="teacher-mini-detail"><span>${esc(label)}</span><b>${esc(value)}</b></div>`;
            return `<article class="card overflow-hidden"><div class="h-1.5 ${color}"></div><div class="p-5"><div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div class="min-w-0"><div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-400"><span>${esc(c.qualification || "TVET")}</span><span class="h-1 w-1 rounded-full bg-slate-300"></span><span>${esc(c.assessor || "Pending assessor")}</span></div><h3 class="mt-2 text-lg font-extrabold">${esc(c.competency || "Competency")}</h3></div><div class="shrink-0">${badge(status)}</div></div><div class="mt-5 grid gap-3 sm:grid-cols-3">${mini("Assessment date", APP?.formatDate?.(c.assessmentDate) ?? (c.assessmentDate || "—"))}${mini("Assessor", c.assessor || "Not assigned")}${mini("Outcome", status)}</div><p class="mt-4 rounded-xl bg-slate-50 p-3 text-sm text-slate-500 dark:bg-slate-800 dark:text-slate-400">${esc(c.remarks || "No assessment notes recorded by your teacher yet.")}</p></div></article>`;
          })
          .join("")
      : `<div class="lg:col-span-2"><div class="teacher-empty-state"><i data-lucide="award"></i><b>No competencies recorded yet</b><p>Your teacher's assessments will appear here.</p></div></div>`;
    lucide.createIcons();
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

  async function renderProfile() {
    try {
      // Load current profile from API
      const profileResponse = await API.student.profile.get();
      if (profileResponse.ok && profileResponse.user) {
        // Update local user object with latest data
        Object.assign(U, profileResponse.user);
        DG.setCurrentUser(U);
      }
    } catch (error) {
      showError("Failed to load profile data");
    }

    text("#profileName", fullName());
    text("#profileId", U.user_id || U.id);
    text("#profileRole", "Student");
    const email = $("#email");
    if (email) email.value = U.email || "";
    ["contact", "guardianName", "guardianContact", "address"].forEach((id) => {
      const el = $(`#${id}`);
      if (el) el.value = U[id] || "";
    });

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

    $("#profileForm")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const submitButton = event.target.querySelector('button[type="submit"]');
      showLoading(submitButton);

      try {
        const payload = {
          firstName: $("#firstName")?.value || U.firstName,
          lastName: $("#lastName")?.value || U.lastName,
          middleName: $("#middleName")?.value || U.middleName,
          contact: $("#contact")?.value || U.contact,
          email: $("#email")?.value || U.email,
          address: $("#address")?.value || U.address,
          guardianName: $("#guardianName")?.value || U.guardianName,
          guardianContact: $("#guardianContact")?.value || U.guardianContact,
        };

        const response = await API.student.profile.update(payload);

        if (response.ok) {
          // Update local user object
          Object.assign(U, response.user);
          DG.setCurrentUser(U);
          APP?.toast?.("Profile saved");
          text("#profileName", fullName());
          DG.loadProfileElements();
        } else {
          showError(response.error || "Failed to save profile");
        }
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
    button.addEventListener("click", () => APP?.toggleTheme?.());
  });

  // Initialize the page
  async function init() {
    const current = page();
    if (current === "dashboard") await renderDashboard();
    if (current === "enrollment") await renderEnrollment();
    if (current === "grades") await renderGrades();
    if (current === "documents") await renderDocuments();
    if (current === "competencies") renderCompetencies();
    if (current === "requirements") renderRequirements();
    if (current === "profile") await renderProfile();
    lucide.createIcons();
  }

  init();
})();
