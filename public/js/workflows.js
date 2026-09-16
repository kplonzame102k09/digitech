(function () {
  const F = window.FEATURES;
  const $ = (s) => document.querySelector(s);
  const page = document.body.dataset.feature;
  const U = AUTH.requireRole(document.body.dataset.role);
  if (!U) return;
  const shell = () => {
    APP.applyTheme();
    APP.updateNotif();
    document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
      if (button.dataset.themeBound) return;
      button.dataset.themeBound = "1";
      button.addEventListener("click", () => APP.toggleTheme());
    });
  };
  const users = F.users();
  const renderRows = (rows, empty = "No records yet") => {
    const body = $("#rows");
    if (!body) return;
    body.innerHTML = rows.length
      ? rows.join("")
      : `<tr><td colspan="8" class="p-8 text-center text-slate-500 dark:text-slate-400">${F.esc(empty)}</td></tr>`;
    lucide.createIcons();
  };
  const round = (num, decimals) => {
    const factor = Math.pow(10, decimals);
    return Math.round(num * factor) / factor;
  };
  function attendance() {
    const isTeacher = U.role === "teacher",
      isAdmin = U.role === "admin";
    const computeScope = () => isTeacher
      ? F.teacherStudents(U).map((s) => s.id)
      : isAdmin
        ? F.students().map((s) => s.id)
        : [U.id];
    let scope = computeScope();

    // --- Per-classroom mode (teacher attendance page only; admin untouched) ---
    // No backend change: uses existing GET /teacher/api/classrooms + detail.
    // Classrooms deduped by id; a student may appear in many classrooms.
    const classroomSection = $("#classroomSection");
    const classroomGrid = $("#classroomGrid");
    const classroomEmpty = $("#classroomEmpty");
    const classroomError = $("#classroomError");
    const classroomDetail = $("#classroomDetail");
    const useClassrooms = isTeacher && !!classroomSection && !!classroomGrid && !!classroomDetail;
    let classrooms = [];
    let activeClassroom = null; // {id,name,subject,status,studentsCount,...}
    let classroomRoster = []; // [{id,firstName,lastName,...}] union of members + legacy subject matches
    const classroomApi = async (url, options = {}) => {
      const token = document.querySelector('meta[name="csrf-token"]')?.content || "";
      const response = await fetch(url, {
        headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": token, "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin",
        ...options,
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.ok === false) throw new Error(data.error || `Request failed (${response.status})`);
      return data;
    };
    const dedupeById = (list) => {
      const seen = new Map();
      (list || []).forEach((c) => { if (c && c.id && !seen.has(String(c.id))) seen.set(String(c.id), c); });
      return [...seen.values()];
    };
    const rosterDisplayName = (s) => F.userName(s) || s.id;
    const classroomSearch = () => ($("#classroomStudentSearch")?.value || "").trim().toLowerCase();
    // --- Daily sessions model: each classroom expects N marks per student per
    // day (sessionsPerDay, teacher-set). Daily % = present slots ÷ N.
    // Present-like statuses count as attended (matches analytics logic).
    const sessionsPerDay = () => Math.max(1, Math.min(20, Number(activeClassroom?.sessionsPerDay) || 1));
    const sheetDate = () => $("#sheetDate")?.value || new Date().toISOString().slice(0, 10);
    const isAttended = (status) => status === "Present" || status === "Late" || status === "Excused";
    const recordSession = (r) => Math.max(1, Number(r.session) || 1);
    const belongsToClassroom = (r, classroom) => {
      if (!classroom) return true;
      if (String(r.classroomId || "") === String(classroom.id)) return true;
      // Legacy rows predate classroomId: match by fixed subject instead.
      return !r.classroomId && String(r.subject || "") === String(classroom.subject || "");
    };
    const dayRecords = (studentId, date) => {
      const all = F.get("attendance", []);
      return all.filter((r) =>
        String(r.studentId) === String(studentId) &&
        String(r.date || "") === String(date) &&
        (!useClassrooms || !activeClassroom || belongsToClassroom(r, activeClassroom)),
      );
    };
    const studentDayPct = (studentId, date) => {
      const n = sessionsPerDay();
      const marks = dayRecords(studentId, date);
      let present = 0;
      for (let k = 1; k <= n; k++) {
        // Strict: only an explicit attended mark counts (missing = not present).
        const rec = marks.find((r) => recordSession(r) === k);
        if (rec && isAttended(rec.status)) present++;
      }
      return Math.round((present / n) * 100);
    };
    let sheetMarks = {}; // studentId -> [bool per session], rebuilt per renderSheet
    let attendanceChart = null;
    const setText = (id, value) => {
      const el = document.getElementById(id);
      if (el) el.textContent = value;
    };
    const isPresentLike = (status) =>
      status === "Present" || status === "Late" || status === "Excused";

    const fetchServerAnalytics = async () => {
      const response = await fetch("/api/portal/analytics/attendance", {
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin",
      });
      if (!response.ok) throw new Error(`Analytics ${response.status}`);
      const data = await response.json().catch(() => ({}));
      if (!data || data.ok !== true) throw new Error("Bad analytics payload");
      return data;
    };

    const drawTrendChart = (labels, attendanceRateData, absentData) => {
      const ctx = document.getElementById("attendanceChart");
      if (!ctx) return;
      if (typeof Chart === "undefined") {
        const c = ctx.getContext ? ctx.getContext("2d") : null;
        if (c) {
          c.clearRect(0, 0, ctx.width || 300, ctx.height || 150);
          c.fillStyle = "#94a3b8";
          c.font = "13px sans-serif";
          c.textAlign = "center";
          c.fillText("Chart library failed to load", (ctx.width || 300) / 2, (ctx.height || 150) / 2);
        }
        return;
      }
      if (attendanceChart) attendanceChart.destroy();
      attendanceChart = new Chart(ctx, {
        type: "line",
        data: {
          labels: labels,
          datasets: [
            {
              label: "Attendance Rate %",
              data: attendanceRateData,
              borderColor: "rgb(16, 185, 129)",
              backgroundColor: "rgba(16, 185, 129, 0.1)",
              tension: 0.3,
              fill: true,
              pointRadius: 4,
              pointHoverRadius: 6
            },
            {
              label: "Absent Rate %",
              data: absentData,
              borderColor: "rgb(244, 63, 94)",
              backgroundColor: "rgba(244, 63, 94, 0.1)",
              tension: 0.3,
              fill: true,
              pointRadius: 4,
              pointHoverRadius: 6
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          plugins: {
            legend: { position: "top", labels: { usePointStyle: true, padding: 20 } },
            tooltip: { mode: "index", intersect: false }
          },
          scales: {
            y: { beginAtZero: true, max: 100, ticks: { callback: function(value) { return value + "%"; } } },
            x: { grid: { display: false } }
          },
          interaction: { mode: "nearest", axis: "x", intersect: false }
        }
      });
    };

    const renderOverallAnalyticsLocal = () => {
      if (!isAdmin) return;
      
      const attendanceData = F.get("attendance", []);
      const totalRecords = attendanceData.length;
      
      if (totalRecords === 0) {
        setText("totalRecords", "0");
        setText("overallRate", "0%");
        setText("totalPresent", "0");
        setText("totalAbsent", "0");
        
        if (attendanceChart) {
          attendanceChart.destroy();
          attendanceChart = null;
        }
        const ctx = document.getElementById("attendanceChart");
        if (ctx) {
          const c = ctx.getContext ? ctx.getContext("2d") : null;
          if (c) {
            c.clearRect(0, 0, ctx.width || 300, ctx.height || 150);
            c.fillStyle = "#94a3b8";
            c.font = "13px sans-serif";
            c.textAlign = "center";
            c.fillText("No attendance data yet", (ctx.width || 300) / 2, (ctx.height || 150) / 2);
          }
        }
        return;
      }
      
      const present = attendanceData.filter((r) => r.status === "Present").length;
      const absent = attendanceData.filter((r) => r.status === "Absent").length;
      const late = attendanceData.filter((r) => r.status === "Late").length;
      const excused = attendanceData.filter((r) => r.status === "Excused").length;
      
      const overallRate = totalRecords > 0 
        ? round(((present + late + excused) / totalRecords) * 100, 1) 
        : 0;
      
      setText("totalRecords", String(totalRecords));
      setText("overallRate", overallRate + "%");
      setText("totalPresent", String(present + late + excused));
      setText("totalAbsent", String(absent));
      
      // Prepare data for line chart - group by date (valid YYYY-MM-DD only, last 30 days)
      const dateGroups = {};
      attendanceData.forEach((record) => {
        const date = record.date || "";
        if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return;
        if (!dateGroups[date]) {
          dateGroups[date] = { present: 0, absent: 0, total: 0 };
        }
        dateGroups[date].total++;
        if (isPresentLike(record.status)) dateGroups[date].present++;
        else if (record.status === "Absent") dateGroups[date].absent++;
      });
      
      // Sort dates and keep the most recent 30
      const sortedDates = Object.keys(dateGroups).sort().slice(-30);
      const attendanceRateData = sortedDates.map((date) => {
        const dayData = dateGroups[date];
        return dayData.total > 0 ? round((dayData.present / dayData.total) * 100, 1) : 0;
      });
      const absentData = sortedDates.map((date) => {
        const dayData = dateGroups[date];
        return dayData.total > 0 ? round((dayData.absent / dayData.total) * 100, 1) : 0;
      });
      
      // Format dates for display
      const formattedDates = sortedDates.map((date) => {
        const [y, m, d] = date.split("-");
        if (y && m && d) {
          return new Date(Number(y), Number(m) - 1, Number(d)).toLocaleDateString("en-PH", {
            month: "short",
            day: "numeric"
          });
        }
        return date;
      });

      drawTrendChart(formattedDates, attendanceRateData, absentData);
    };

    const fetchRecorderStudents = async (recorderId) => {
      const response = await fetch(
        `/api/portal/analytics/attendance/by-recorder/${encodeURIComponent(recorderId)}`,
        {
          headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
          credentials: "same-origin",
        },
      );
      if (!response.ok) throw new Error(`Analytics ${response.status}`);
      const data = await response.json().catch(() => ({}));
      if (!data || data.ok !== true) throw new Error("Bad analytics payload");
      return data.students || [];
    };

    const recorderStudentsLocal = (recorderId) => {
      const all = F.get("attendance", []).filter(
        (r) => String(r.recordedBy) === String(recorderId),
      );
      const byId = {};
      all.forEach((r) => {
        const bucket = (byId[r.studentId] ||= {});
        bucket.total = (bucket.total || 0) + 1;
        bucket[r.status] = (bucket[r.status] || 0) + 1;
      });
      const liveUsers = F.users();
      return Object.keys(byId)
        .map((sid) => {
          const b = byId[sid];
          const s = liveUsers.find((u) => String(u.id) === String(sid));
          const present = b.Present || 0;
          const late = b.Late || 0;
          const excused = b.Excused || 0;
          const absent = b.Absent || 0;
          const total = present + late + excused + absent;
          return {
            id: sid,
            name: s ? F.userName(s) : sid,
            photo: s ? F.photoUrl(s) : "/images/16432.png",
            total,
            present,
            late,
            excused,
            absent,
            rate: total ? round(((present + late + excused) / total) * 100, 1) : 0,
          };
        })
        .sort((a, b) => b.total - a.total);
    };

    const renderRecorderStudentsModal = (list, teacherName) => {
      const root = document.getElementById("modalRoot");
      if (!root) return;
      APP.closeModal();
      const backdrop = document.createElement("div");
      backdrop.className =
        "modal-backdrop fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/50 p-4";
      const panel = document.createElement("div");
      panel.className =
        "w-full max-w-4xl rounded-2xl bg-white p-5 shadow-2xl dark:bg-slate-900";
      const close = () => APP.closeModal();
      const initialsOf = (name) => (
        String(name).split(/\s+/).map((w) => w[0]).slice(0, 2).join("") || "?"
      ).toUpperCase();
      const rows = list.length
        ? list.map((st) => {
            const rateColor = st.rate >= 80
              ? "text-emerald-600 dark:text-emerald-400"
              : st.rate >= 60
                ? "text-amber-600 dark:text-amber-400"
                : "text-rose-600 dark:text-rose-400";
            return `<tr class="border-t border-slate-100 transition-colors hover:bg-slate-50/70 dark:border-slate-800 dark:hover:bg-slate-800/40">
              <td class="p-3">
                <div class="flex items-center gap-3">
                  <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-950">
                    <span class="text-xs font-bold text-blue-700 dark:text-blue-300">${F.esc(initialsOf(st.name))}</span>
                    <img src="${F.esc(st.photo || "/images/16432.png")}" alt="${F.esc(st.name)}" loading="lazy" class="absolute inset-0 h-9 w-9 rounded-full object-cover" onerror="this.onerror=null;this.src='/images/16432.png'" />
                  </span>
                  <div>
                    <b class="block text-sm">${F.esc(st.name)}</b>
                    <small class="text-xs text-slate-400">${F.esc(st.id)}</small>
                  </div>
                </div>
              </td>
              <td class="p-3 font-semibold text-slate-700 dark:text-slate-200">${st.total}</td>
              <td class="p-3 text-emerald-600 dark:text-emerald-400">${st.present ?? 0}</td>
              <td class="p-3 text-amber-600 dark:text-amber-400">${st.late ?? 0}</td>
              <td class="p-3 text-blue-600 dark:text-blue-400">${st.excused ?? 0}</td>
              <td class="p-3 text-rose-600 dark:text-rose-400">${st.absent ?? 0}</td>
              <td class="p-3 text-right font-bold ${rateColor}">${st.rate}%</td>
            </tr>`;
          }).join("")
        : `<tr><td colspan="7" class="p-8 text-center text-slate-500 dark:text-slate-400">No finalized attendance for this recorder yet.</td></tr>`;
      panel.innerHTML = `
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-600 dark:text-blue-400">Finalized attendance</p>
            <h2 class="mt-1 truncate text-lg font-extrabold">${F.esc(teacherName || "Teacher")}</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Students with admin-finalized attendance recorded by this teacher.</p>
          </div>
          <button type="button" data-recorder-close class="shrink-0 rounded p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <div class="mt-4 max-h-[70vh] overflow-auto rounded border border-slate-200 dark:border-slate-700">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-800 dark:text-slate-400">
              <tr>
                <th class="px-4 py-3">Student</th>
                <th class="px-4 py-3">Records</th>
                <th class="px-4 py-3">Present</th>
                <th class="px-4 py-3">Late</th>
                <th class="px-4 py-3">Excused</th>
                <th class="px-4 py-3">Absent</th>
                <th class="px-4 py-3 text-right">Rate</th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
        <div class="mt-4 flex justify-end">
          <button type="button" data-recorder-close class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Close</button>
        </div>`;
      panel.querySelectorAll("[data-recorder-close]").forEach((b) => b.addEventListener("click", close));
      backdrop.append(panel);
      root.append(backdrop);
      lucide.createIcons();
    };

    const bindTeacherCard = (container) => {
      if (!container) return;
      container.querySelectorAll("[data-recorder]").forEach((card) => {
        if (card.dataset.bound) return;
        card.dataset.bound = "1";
        const recorderId = card.dataset.recorder;
        const recorderName = card.dataset.recorderName || "Teacher";
        const open = () => {
          (async () => {
            let list = [];
            try {
              list = await fetchRecorderStudents(recorderId);
            } catch (_) {
              list = recorderStudentsLocal(recorderId);
            }
            renderRecorderStudentsModal(list, recorderName);
          })();
        };
        card.addEventListener("click", open);
        card.addEventListener("keydown", (e) => {
          if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            open();
          }
        });
      });
    };

    const renderTeacherCards = (list) => {
      const container = $("#teacherAnalytics");
      if (!container) return;
      container.innerHTML = list.length
        ? list.map((stat) => {
            const teacherName = stat.name || stat.teacher?.id || "Unknown";
            const teacherId = stat.id || stat.teacher?.id || "";
            const teacherRole = stat.role || stat.teacher?.role || "teacher";
            const photoUrl = stat.photoUrl || (stat.teacher ? F.photoUrl(stat.teacher) : "/images/16432.png");
            const initials = (
              teacherName === teacherId
                ? String(teacherName).slice(0, 2)
                : String(teacherName).split(/\s+/).map((w) => w[0]).slice(0, 2).join("")
            ).toUpperCase();
            const rate = stat.rate ?? stat.attendanceRate ?? 0;
            const total = stat.total ?? stat.totalRecords ?? 0;
            const rateColor = rate >= 80
              ? "text-emerald-600"
              : rate >= 60
                ? "text-amber-600"
                : "text-rose-600";
            return `<div data-recorder="${F.esc(teacherId)}" data-recorder-name="${F.esc(teacherName)}" role="button" tabindex="0" class="cursor-pointer rounded border border-slate-200 bg-slate-50 p-4 transition hover:shadow-md dark:border-slate-700 dark:bg-slate-800">
              <div class="flex items-center gap-3 mb-3">
                <span class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-violet-100 dark:bg-violet-950">
                  <span class="text-sm font-bold text-violet-700 dark:text-violet-300">${F.esc(initials)}</span>
                  <img src="${F.esc(photoUrl)}" alt="${F.esc(teacherName)}" loading="lazy" class="absolute inset-0 h-10 w-10 rounded-full object-cover" onerror="this.onerror=null;this.src='/images/16432.png'" />
                </span>
                <div class="min-w-0 flex-1">
                  <b class="block truncate text-sm">${F.esc(teacherName)}</b>
                  <small class="text-xs text-slate-400">${F.esc(teacherId)} · ${F.esc(teacherRole)}</small>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3 text-center">
                <div class="rounded bg-white p-2 dark:bg-slate-900">
                  <p class="text-[10px] uppercase tracking-wider text-slate-400">Total Records</p>
                  <p class="text-lg font-bold text-slate-700 dark:text-slate-200">${total}</p>
                </div>
                <div class="rounded bg-white p-2 dark:bg-slate-900">
                  <p class="text-[10px] uppercase tracking-wider text-slate-400">Attendance Rate</p>
                  <p class="text-lg font-bold ${rateColor}">${rate}%</p>
                </div>
              </div>
              <div class="mt-3 grid grid-cols-4 gap-2 text-center">
                <div><p class="text-[10px] text-slate-400">Present</p><p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">${stat.present ?? 0}</p></div>
                <div><p class="text-[10px] text-slate-400">Absent</p><p class="text-sm font-semibold text-rose-600 dark:text-rose-400">${stat.absent ?? 0}</p></div>
                <div><p class="text-[10px] text-slate-400">Late</p><p class="text-sm font-semibold text-amber-600 dark:text-amber-400">${stat.late ?? 0}</p></div>
                <div><p class="text-[10px] text-slate-400">Excused</p><p class="text-sm font-semibold text-blue-600 dark:text-blue-400">${stat.excused ?? 0}</p></div>
              </div>
              <div class="mt-3 flex items-center gap-1.5 border-t border-slate-200 pt-3 text-xs font-semibold text-blue-600 dark:border-slate-700 dark:text-blue-400">
                <i data-lucide="users" class="h-3.5 w-3.5"></i>View student attendance
              </div>
            </div>`;
          }).join("")
        : `<div class="col-span-full text-center p-8 text-slate-500 dark:text-slate-400">No teacher attendance data available</div>`;
      lucide.createIcons();
      bindTeacherCard(container);
    };

    const renderOverallAnalytics = async () => {
      if (!isAdmin) return;
      try {
        const server = await fetchServerAnalytics();
        const o = server.overall || {};
        setText("totalRecords", String(o.totalRecords ?? 0));
        setText("overallRate", (o.overallRate ?? 0) + "%");
        setText("totalPresent", String(o.present ?? 0));
        setText("totalAbsent", String(o.absent ?? 0));
        const t = server.trends || {};
        drawTrendChart(t.labels || [], t.attendanceRate || [], t.absentRate || []);
        if (Array.isArray(server.byRecorder)) renderTeacherCards(server.byRecorder);
        else renderTeacherAnalyticsLocal();
        return;
      } catch (_) {
        renderOverallAnalyticsLocal();
      }
    };
    
    const renderTeacherAnalyticsLocal = () => {
      if (!isAdmin) return;

      const attendanceData = F.get("attendance", []);
      const liveUsers = F.users();
      const teachers = liveUsers.filter((u) => u.role === "teacher" || u.role === "admin");
      
      const analytics = teachers.map((teacher) => {
        const teacherRecords = attendanceData.filter((r) => r.recordedBy === teacher.id);
        const totalRecords = teacherRecords.length;
        
        if (totalRecords === 0) {
          return {
            teacher,
            totalRecords: 0,
            present: 0,
            absent: 0,
            late: 0,
            excused: 0,
            attendanceRate: 0
          };
        }
        
        const present = teacherRecords.filter((r) => r.status === "Present").length;
        const absent = teacherRecords.filter((r) => r.status === "Absent").length;
        const late = teacherRecords.filter((r) => r.status === "Late").length;
        const excused = teacherRecords.filter((r) => r.status === "Excused").length;
        
        const attendanceRate = totalRecords > 0 
          ? round(((present + late + excused) / totalRecords) * 100, 1) 
          : 0;
        
        return {
          teacher,
          totalRecords,
          present,
          absent,
          late,
          excused,
          attendanceRate
        };
      }).sort((a, b) => b.totalRecords - a.totalRecords);
      
      const container = $("#teacherAnalytics");
      if (!container) return;
      
      container.innerHTML = analytics.length
        ? analytics.map((stat) => {
            const teacherName = F.userName(stat.teacher) || stat.teacher.id;
            const photoUrl = F.photoUrl(stat.teacher);
            const initials = (
              teacherName === stat.teacher.id
                ? teacherName.slice(0, 2)
                : teacherName.split(/\s+/).map((w) => w[0]).slice(0, 2).join("")
            ).toUpperCase();
            
            const rateColor = stat.attendanceRate >= 80 
              ? "text-emerald-600" 
              : stat.attendanceRate >= 60 
                ? "text-amber-600" 
                : "text-rose-600";
            
            return `<div data-recorder="${F.esc(stat.teacher.id)}" data-recorder-name="${F.esc(teacherName)}" role="button" tabindex="0" class="cursor-pointer rounded border border-slate-200 bg-slate-50 p-4 transition hover:shadow-md dark:border-slate-700 dark:bg-slate-800">
              <div class="flex items-center gap-3 mb-3">
                <span class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-violet-100 dark:bg-violet-950">
                  <span class="text-sm font-bold text-violet-700 dark:text-violet-300">${F.esc(initials)}</span>
                  <img src="${F.esc(photoUrl)}" alt="${F.esc(teacherName)}" loading="lazy" class="absolute inset-0 h-10 w-10 rounded-full object-cover" onerror="this.onerror=null;this.src='/images/16432.png'" />
                </span>
                <div class="min-w-0 flex-1">
                  <b class="block truncate text-sm">${F.esc(teacherName)}</b>
                  <small class="text-xs text-slate-400">${F.esc(stat.teacher.id)} · ${F.esc(stat.teacher.role || "teacher")}</small>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3 text-center">
                <div class="rounded bg-white p-2 dark:bg-slate-900">
                  <p class="text-[10px] uppercase tracking-wider text-slate-400">Total Records</p>
                  <p class="text-lg font-bold text-slate-700 dark:text-slate-200">${stat.totalRecords}</p>
                </div>
                <div class="rounded bg-white p-2 dark:bg-slate-900">
                  <p class="text-[10px] uppercase tracking-wider text-slate-400">Attendance Rate</p>
                  <p class="text-lg font-bold ${rateColor}">${stat.attendanceRate}%</p>
                </div>
              </div>
              <div class="mt-3 grid grid-cols-4 gap-2 text-center">
                <div>
                  <p class="text-[10px] text-slate-400">Present</p>
                  <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">${stat.present}</p>
                </div>
                <div>
                  <p class="text-[10px] text-slate-400">Absent</p>
                  <p class="text-sm font-semibold text-rose-600 dark:text-rose-400">${stat.absent}</p>
                </div>
                <div>
                  <p class="text-[10px] text-slate-400">Late</p>
                  <p class="text-sm font-semibold text-amber-600 dark:text-amber-400">${stat.late}</p>
                </div>
                <div>
                  <p class="text-[10px] text-slate-400">Excused</p>
                  <p class="text-sm font-semibold text-blue-600 dark:text-blue-400">${stat.excused}</p>
                </div>
              </div>
              <div class="mt-3 flex items-center gap-1.5 border-t border-slate-200 pt-3 text-xs font-semibold text-blue-600 dark:border-slate-700 dark:text-blue-400">
                <i data-lucide="users" class="h-3.5 w-3.5"></i>View student attendance
              </div>
            </div>`;
          }).join("")
        : `<div class="col-span-full text-center p-8 text-slate-500 dark:text-slate-400">No teacher attendance data available</div>`;
      
      lucide.createIcons();
      bindTeacherCard(container);
    };

    const renderTeacherAnalytics = async () => {
      if (!isAdmin) return;
      try {
        const server = await fetchServerAnalytics();
        if (Array.isArray(server.byRecorder)) {
          renderTeacherCards(server.byRecorder);
          return;
        }
      } catch (_) {
        // fall through to local computation
      }
      renderTeacherAnalyticsLocal();
    };

    const render = () => {
      if (useClassrooms && activeClassroom) {
        scope = classroomRoster.map((s) => s.id);
      } else if (!useClassrooms) {
        scope = computeScope();
      }
      const liveUsers = F.users();
      const q = useClassrooms ? classroomSearch() : "";
      const nameOf = (id) => {
        const fromRoster = classroomRoster.find((s) => s.id === id);
        const s = fromRoster || liveUsers.find((u) => u.id === id);
        return { s, nm: rosterDisplayName(s) || id };
      };
      const data = F.get("attendance", []).filter((r) => {
        if (!scope.includes(r.studentId)) return false;
        if (useClassrooms && activeClassroom && !belongsToClassroom(r, activeClassroom)) return false;
        if (q) {
          const { nm } = nameOf(r.studentId);
          if (!(`${nm} ${r.studentId}`.toLowerCase().includes(q))) return false;
        }
        return true;
      });
      renderRows(
        data
          .sort((a, b) => String(b.date).localeCompare(String(a.date)))
          .map((r) => {
            const s = classroomRoster.find((x) => x.id === r.studentId) || liveUsers.find((u) => u.id === r.studentId);
            const nm = rosterDisplayName(s) || r.studentId;
            const initials = (
              nm === r.studentId
                ? nm.slice(0, 2)
                : nm.split(/\s+/).map((w) => w[0]).slice(0, 2).join("")
            ).toUpperCase();
            const [y, m, d] = String(r.date || "").split("-");
            const when =
              y && m && d
                ? new Date(Number(y), Number(m) - 1, Number(d)).toLocaleDateString("en-PH", {
                    year: "numeric",
                    month: "short",
                    day: "numeric",
                  })
                : "—";
            const st = String(r.status || "").toLowerCase();
            const badge =
              {
                present: "bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300",
                late: "bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300",
                absent: "bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300",
              }[st] || "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300";
            return `<tr class="border-t border-slate-100 transition-colors hover:bg-slate-50/70 dark:border-slate-800 dark:hover:bg-slate-800/40">
              <td class="p-4">
                <div class="flex items-center gap-3">
                  <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-950">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold text-blue-700 dark:text-blue-300">${F.esc(initials)}</span>
                    <img src="${F.esc(F.photoUrl(s))}" alt="${F.esc(nm)}" loading="lazy" class="absolute inset-0 h-9 w-9 rounded-full object-cover" onerror="this.onerror=null;this.src='/images/16432.png'" />
                  </span>
                  <div>
                    <b class="block">${F.esc(nm)}</b>
                    <small class="text-xs text-slate-400">${F.esc(r.studentId)}</small>
                  </div>
                </div>
              </td>
              <td class="whitespace-nowrap p-4 text-xs font-medium text-slate-600 dark:text-slate-400">${F.esc(when)}</td>
              <td class="p-4 text-slate-700 dark:text-slate-300">${F.esc(r.subject || "—")}</td>
              <td class="p-4"><span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">S${recordSession(r)}</span></td>
              <td class="p-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${badge}">${F.esc(r.status || "—")}</span></td>
              <td class="max-w-[16rem] truncate p-4 text-slate-500 dark:text-slate-400" title="${F.esc(r.remarks || "")}">${F.esc(r.remarks || "—")}</td>
              ${isTeacher || isAdmin ? `<td class="p-4 text-right"><button class="rounded p-2 text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 dark:text-emerald-300" data-edit="${F.esc(r.id)}" title="Edit record"><i data-lucide="pencil" class="h-4 w-4"></i></button></td>` : `<td class="p-4"></td>`}
            </tr>`;
          }),
      );
      $("#rows")
        ?.querySelectorAll("[data-edit]")
        .forEach((b) => (b.onclick = () => editAttendance(b.dataset.edit)));
      
      if (isAdmin) {
        renderOverallAnalytics();
        renderTeacherAnalytics();
      }
    };
    const editAttendance = (id) => {
      const all = F.get("attendance", []);
      const isNew = !id || !all.some((x) => x.id === id);
      const r = all.find((x) => x.id === id) || {
        id: DG.generateId("ATT"),
        studentId: isTeacher ? scope[0] : $("#student")?.value,
        date: new Date().toISOString().slice(0, 10),
      };
      const student = $("#student");
      if (student) {
        const options = useClassrooms && activeClassroom
          ? classroomRoster
          : (isTeacher ? F.teacherStudents(U) : F.students());
        student.innerHTML = options
          .map(
            (s) => `<option value="${F.esc(s.id)}">${F.esc(F.userName(s))}</option>`,
          )
          .join("");
        student.value = r.studentId;
      }
      ["recordId", "date", "subject", "remarks"].forEach((k) => {
        const el = $("#" + k);
        if (!el) return;
        if (k === "recordId") el.value = r.id;
        else if (k === "subject" && useClassrooms && activeClassroom && isNew) el.value = activeClassroom.subject || r[k] || "";
        else el.value = r[k] || "";
      });
      const subjectEl = $("#subject");
      if (subjectEl && useClassrooms && activeClassroom) {
        // Fixed per classroom: lock for new records; keep readable for edits.
        subjectEl.value = isNew ? (activeClassroom.subject || subjectEl.value) : (r.subject || activeClassroom.subject || "");
        subjectEl.readOnly = true;
      } else if (subjectEl) {
        subjectEl.readOnly = false;
      }
      if (isNew && useClassrooms && activeClassroom) {
        const dateEl = $("#date");
        const sheetDateEl = $("#sheetDate");
        if (dateEl && sheetDateEl?.value) dateEl.value = sheetDateEl.value;
      }
      $("#status").value = r.status || "Present";
      $("#attendanceDialog")?.classList.remove("hidden");
    };
    $("#newAttendance")?.addEventListener("click", () => editAttendance(""));
    $("#closeAttendance")?.addEventListener("click", () =>
      $("#attendanceDialog")?.classList.add("hidden"),
    );
    $("#attendanceForm")?.addEventListener("submit", (e) => {
      e.preventDefault();
      const all = F.get("attendance", []);
      const recordId = $("#recordId").value;
      const isNewSubmit = !recordId || !all.some((x) => x.id === recordId);
      let subject = $("#subject").value.trim();
      if (useClassrooms && activeClassroom && isNewSubmit) {
        subject = activeClassroom.subject || subject; // fixed per classroom
      }
      const r = {
        id: recordId || DG.generateId("ATT"),
        studentId: $("#student").value,
        date: $("#date").value,
        subject,
        session: 1,
        status: $("#status").value,
        remarks: $("#remarks").value.trim(),
        recordedBy: U.id,
        updatedAt: new Date().toISOString(),
      };
      if (useClassrooms && activeClassroom) {
        r.classroomId = activeClassroom.id;
        const prior = all.find((x) => x.id === r.id);
        r.session = Math.max(1, Number(prior?.session) || 1);
      }
      const i = all.findIndex((x) => x.id === r.id);
      i >= 0 ? (all[i] = r) : all.push(r);
      F.save("attendance", all);
      F.notify(
        [r.studentId, ...F.parentIdsFor([r.studentId])],
        "Attendance updated",
        `${r.date}: ${r.status}`,
        "attendance",
      );
      $("#attendanceDialog").classList.add("hidden");
      render();
      if (isAdmin) {
        renderOverallAnalytics();
        renderTeacherAnalytics();
      }
      APP.toast("Attendance saved");
    });
    $("#export")?.addEventListener("click", () => {
      const data = F.get("attendance", []).filter((r) =>
        scope.includes(r.studentId),
      );
      const fileName = useClassrooms && activeClassroom
        ? `attendance-${String(activeClassroom.name || activeClassroom.id).toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "") || "classroom"}.csv`
        : "attendance.csv";
      F.download(
        fileName,
        F.csv(
          ["Student ID", "Date", "Subject", "Session", "Classroom", "Status", "Remarks"],
          data.map((r) => [
            r.studentId,
            r.date,
            r.subject,
            recordSession(r),
            r.classroomId || "",
            r.status,
            r.remarks,
          ]),
        ),
        "text/csv",
      );
    });
    const pctColor = (pct) => pct >= 100
      ? "bg-emerald-600 text-white"
      : pct >= 80
        ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
        : pct >= 50
          ? "bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
          : "bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300";

    const paintSheetButton = (btn, present) => {
      btn.textContent = present ? "✓" : "✕";
      btn.title = `Session ${Number(btn.dataset.k) + 1} — ${present ? "Present" : "Absent"} (click to flip)`;
      btn.className = `h-9 min-w-9 rounded-lg px-2 text-sm font-extrabold transition ${present
        ? "bg-emerald-600 text-white hover:bg-emerald-500"
        : "bg-rose-100 text-rose-700 hover:bg-rose-200 dark:bg-rose-950/50 dark:text-rose-300"}`;
    };

    const paintSheetPct = (sid) => {
      const row = document.querySelector(`[data-sheet-row="${CSS.escape(String(sid))}"]`);
      const badge = row?.querySelector("[data-sheet-pct]");
      if (!badge) return 0;
      const state = sheetMarks[sid] || [];
      const n = sessionsPerDay();
      const pct = n ? Math.round((state.filter(Boolean).length / n) * 100) : 0;
      badge.textContent = `${pct}%`;
      badge.className = `rounded-full px-3 py-1 text-xs font-extrabold ${pctColor(pct)}`;
      return pct;
    };

    const paintSheetSummary = () => {
      const el = $("#sheetSummary");
      if (!el) return;
      const ids = Object.keys(sheetMarks);
      if (!ids.length) {
        el.textContent = "";
        return;
      }
      const avg = Math.round(ids.reduce((sum, sid) => sum + paintSheetPct(sid), 0) / ids.length);
      el.textContent = `${sheetDate()} · class ${avg}%`;
    };

    const renderSheet = () => {
      const box = $("#sheetRows");
      if (!box || !useClassrooms || !activeClassroom) return;
      const n = sessionsPerDay();
      const date = sheetDate();
      const q = classroomSearch();
      const students = classroomRoster.filter((s) => {
        if (!q) return true;
        return `${rosterDisplayName(s)} ${s.id}`.toLowerCase().includes(q);
      });
      sheetMarks = {};
      if (!students.length) {
        box.innerHTML = `<div class="p-8 text-center text-sm text-slate-500 dark:text-slate-400">No students match.</div>`;
        paintSheetSummary();
        return;
      }
      box.innerHTML = students.map((s) => {
        const nm = rosterDisplayName(s);
        const initials = (nm === s.id ? nm.slice(0, 2) : nm.split(/\s+/).map((w) => w[0]).slice(0, 2).join("")).toUpperCase();
        const marks = dayRecords(s.id, date);
        const state = [];
        for (let k = 1; k <= n; k++) {
          const rec = marks.find((r) => recordSession(r) === k);
          state.push(!rec || isAttended(rec.status)); // default ✓ for fast marking
        }
        sheetMarks[s.id] = state;
        const pct = Math.round((state.filter(Boolean).length / n) * 100);
        return `<div class="flex flex-wrap items-center gap-3 p-4" data-sheet-row="${F.esc(s.id)}">
          <div class="flex min-w-0 flex-1 items-center gap-3">
            <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">${F.esc(initials)}
              <img src="${F.esc(F.photoUrl(s))}" alt="${F.esc(nm)}" loading="lazy" class="absolute inset-0 h-9 w-9 rounded-full object-cover" onerror="this.onerror=null;this.src='/images/16432.png'" />
            </span>
            <div class="min-w-0">
              <b class="block truncate">${F.esc(nm)}</b>
              <small class="text-xs text-slate-400">${F.esc(s.id)}</small>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-1.5">
            ${state.map((present, idx) => `<button type="button" data-sheet-toggle data-s="${F.esc(s.id)}" data-k="${idx}" class="h-9 min-w-9 rounded-lg px-2 text-sm font-extrabold transition ${present ? "bg-emerald-600 text-white hover:bg-emerald-500" : "bg-rose-100 text-rose-700 hover:bg-rose-200 dark:bg-rose-950/50 dark:text-rose-300"}" title="Session ${idx + 1} — ${present ? "Present" : "Absent"} (click to flip)">${present ? "✓" : "✕"}</button>`).join("")}
          </div>
          <span data-sheet-pct class="rounded-full px-3 py-1 text-xs font-extrabold ${pctColor(pct)}">${pct}%</span>
        </div>`;
      }).join("");
      paintSheetSummary();
    };

    const findSlot = (all, sid, date, k) => all.find((r) =>
      String(r.studentId) === String(sid) &&
      String(r.date || "") === String(date) &&
      String(r.subject || "") === String(activeClassroom.subject || "") &&
      recordSession(r) === k &&
      (!r.classroomId || String(r.classroomId) === String(activeClassroom.id)));

    const saveSheet = () => {
      if (!useClassrooms || !activeClassroom) return;
      const n = sessionsPerDay();
      const date = sheetDate();
      const all = F.get("attendance", []);
      const touched = [];
      Object.entries(sheetMarks).forEach(([sid, state]) => {
        if (!scope.includes(sid)) return;
        let present = 0;
        state.slice(0, n).forEach((isPresent, idx) => {
          const k = idx + 1;
          if (isPresent) present++;
          const found = findSlot(all, sid, date, k);
          const status = isPresent ? "Present" : "Absent";
          if (found) {
            Object.assign(found, {
              status, classroomId: activeClassroom.id, session: k,
              subject: activeClassroom.subject || found.subject,
              recordedBy: U.id, updatedAt: new Date().toISOString(),
            });
          } else {
            all.push({
              id: DG.generateId("ATT"),
              studentId: sid, date,
              subject: activeClassroom.subject || "",
              classroomId: activeClassroom.id, session: k,
              status, remarks: "",
              recordedBy: U.id, updatedAt: new Date().toISOString(),
            });
          }
        });
        touched.push({ sid, pct: Math.round((present / n) * 100) });
      });
      F.save("attendance", all);
      // Best-effort notices (same hardened pattern as the single form).
      try {
        touched.forEach(({ sid, pct }) => F.notify(
          [sid, ...(F.parentIdsFor ? F.parentIdsFor([sid]) : [])],
          "Attendance updated",
          `${date}: ${pct}% present`,
          "attendance",
        ));
      } catch (_) { /* notification skipped */ }
      render();
      renderSheet();
      APP.toast(`Day saved — ${touched.length} student${touched.length === 1 ? "" : "s"}`);
    };

    const setClassroomError = (message) => {
      if (!classroomError) return;
      classroomError.textContent = message || "";
      classroomError.classList.toggle("hidden", !message);
    };
    const renderClassroomCards = () => {
      if (!useClassrooms) return;
      renderClassroomStatsFallback();
      if (!classrooms.length) {
        classroomGrid.innerHTML = "";
        classroomEmpty?.classList.remove("hidden");
        return;
      }
      classroomEmpty?.classList.add("hidden");
      classroomGrid.innerHTML = classrooms.map((c) => {
        const count = Number(c.studentsCount || 0);
        return `<button type="button" data-classroom="${F.esc(c.id)}" class="rounded-2xl border border-slate-200 p-4 text-left transition hover:border-emerald-300 dark:border-slate-700 dark:hover:border-emerald-700">
          <div class="flex items-center justify-between gap-2">
            <b class="truncate">${F.esc(c.name || "Classroom")}</b>
            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold ${c.status === "active" ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300" : "bg-slate-100 text-slate-500 dark:bg-slate-800"}">${F.esc(c.status || "active")}</span>
          </div>
          <p class="mt-1 truncate text-xs text-slate-400">${F.esc(c.subject || "No subject")} · ${count} student${count === 1 ? "" : "s"} · ${Number(c.sessionsPerDay) || 1} session${(Number(c.sessionsPerDay) || 1) === 1 ? "" : "s"}/day</p>
          <p class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300">Open attendance <span aria-hidden="true">→</span></p>
        </button>`;
      }).join("");
      classroomGrid.querySelectorAll("[data-classroom]").forEach((btn) =>
        btn.addEventListener("click", () => openClassroom(btn.dataset.classroom)),
      );
      if (window.lucide) lucide.createIcons();
    };
    const renderClassroomStatsFallback = () => {
      // Counts are informational only; studentsCount comes from the API.
    };
    const showList = () => {
      activeClassroom = null;
      classroomRoster = [];
      scope = [];
      classroomDetail?.classList.add("hidden");
      classroomSection?.classList.remove("hidden");
      $("#attendanceDialog")?.classList.add("hidden");
      renderClassroomCards();
      renderRows([]);
      try {
        const url = new URL(window.location.href);
        if (url.searchParams.has("classroom")) {
          url.searchParams.delete("classroom");
          window.history.replaceState({}, "", url.pathname + url.search);
        }
      } catch (_) { /* ignore */ }
    };
    const openClassroom = async (id) => {
      const found = classrooms.find((c) => String(c.id) === String(id));
      if (!found) return;
      setClassroomError("");
      activeClassroom = found;
      classroomSection?.classList.add("hidden");
      classroomDetail?.classList.remove("hidden");
      const nameEl = $("#detailName");
      if (nameEl) nameEl.textContent = found.name || "Classroom";
      const metaEl = $("#detailMeta");
      if (metaEl) metaEl.textContent = `${found.subject || "No subject"} · ${found.status || "active"}`;
      try {
        const data = await classroomApi(`/teacher/api/classrooms/${encodeURIComponent(found.id)}`);
        const members = (data.classroom?.students || []).map((s) => ({
          id: s.id, firstName: s.firstName, lastName: s.lastName,
          email: s.email, photo: s.photo,
        }));
        // Union: members + legacy assigned students already having this subject's
        // records (covers pre-classroom data). Same student may be in many
        // classrooms — allowed. No classroom is ever duplicated (dedupeById).
        const memberIds = new Set(members.map((s) => String(s.id)));
        const legacyMatches = F.teacherStudents(U).filter((s) => {
          if (memberIds.has(String(s.id))) return false;
          return F.get("attendance", []).some((r) =>
            String(r.studentId) === String(s.id) && String(r.subject || "") === String(found.subject || ""));
        });
        classroomRoster = [...members, ...legacyMatches].sort((a, b) =>
          rosterDisplayName(a).localeCompare(rosterDisplayName(b)));
        const countEl = $("#detailCount");
        if (countEl) countEl.textContent = `${classroomRoster.length} student${classroomRoster.length === 1 ? "" : "s"}`;
      } catch (error) {
        // Non-breaking fallback: legacy assigned students so the form still works.
        classroomRoster = F.teacherStudents(U);
        const countEl = $("#detailCount");
        if (countEl) countEl.textContent = `${classroomRoster.length} student${classroomRoster.length === 1 ? "" : "s"}`;
        setClassroomError(error.message || "Could not load roster; showing assigned learners.");
      }
      scope = classroomRoster.map((s) => s.id);
      const sessInput = $("#sessionsPerDay");
      if (sessInput) sessInput.value = Math.max(1, Number(found.sessionsPerDay) || 1);
      const sheetDateEl = $("#sheetDate");
      if (sheetDateEl && !sheetDateEl.value) sheetDateEl.value = new Date().toISOString().slice(0, 10);
      render();
      renderSheet();
      try {
        const url = new URL(window.location.href);
        url.searchParams.set("classroom", String(found.id));
        window.history.replaceState({}, "", url.pathname + url.search);
      } catch (_) { /* ignore */ }
      if (window.lucide) lucide.createIcons();
    };
    const loadClassrooms = async () => {
      setClassroomError("");
      try {
        const data = await classroomApi("/teacher/api/classrooms");
        classrooms = dedupeById(data.classrooms || []);
        // Active first, then archived; stable by name.
        classrooms.sort((a, b) =>
          (a.status === "active" ? 0 : 1) - (b.status === "active" ? 0 : 1) ||
          String(a.name || "").localeCompare(String(b.name || "")));
        renderClassroomCards();
        const params = new URLSearchParams(window.location.search || "");
        const deep = params.get("classroom");
        if (deep && classrooms.some((c) => String(c.id) === String(deep))) {
          openClassroom(deep);
        } else {
          renderRows([]);
        }
      } catch (error) {
        // Non-breaking fallback: hide classroom shell, show legacy flat list.
        setClassroomError(error.message || "Failed to load classrooms.");
        if (!classrooms.length) {
          classroomGrid.innerHTML = "";
          classroomEmpty?.classList.remove("hidden");
        }
        renderRows([]);
      }
    };
    $("#backToClassrooms")?.addEventListener("click", showList);
    $("#classroomStudentSearch")?.addEventListener("input", () => { render(); renderSheet(); });
    $("#sheetDate")?.addEventListener("change", () => renderSheet());
    $("#saveSheet")?.addEventListener("click", saveSheet);
    $("#sheetRows")?.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-sheet-toggle]");
      if (!btn) return;
      const arr = sheetMarks[btn.dataset.s];
      const k = Number(btn.dataset.k);
      if (!arr || !(k >= 0) || k >= arr.length) return;
      arr[k] = !arr[k];
      paintSheetButton(btn, arr[k]);
      paintSheetPct(btn.dataset.s);
      paintSheetSummary();
    });
    $("#saveSessions")?.addEventListener("click", async () => {
      if (!useClassrooms || !activeClassroom) return;
      const v = Math.max(1, Math.min(20, Number($("#sessionsPerDay")?.value) || 1));
      try {
        const data = await classroomApi(`/teacher/api/classrooms/${encodeURIComponent(activeClassroom.id)}`, {
          method: "PUT",
          body: JSON.stringify({ sessionsPerDay: v }),
        });
        activeClassroom.sessionsPerDay = data.classroom?.sessionsPerDay ?? v;
        const c = classrooms.find((x) => String(x.id) === String(activeClassroom.id));
        if (c) c.sessionsPerDay = activeClassroom.sessionsPerDay;
        if ($("#sessionsPerDay")) $("#sessionsPerDay").value = activeClassroom.sessionsPerDay;
        renderSheet();
        APP.toast("Sessions per day updated");
      } catch (error) {
        setClassroomError(error.message || "Could not save sessions per day.");
      }
    });
    if (useClassrooms) {
      loadClassrooms();
    } else {
      render();
    }
    if (isAdmin) {
      renderOverallAnalytics();
      renderTeacherAnalytics();
    }
    // Live updates: re-render when other users change attendance or user data.
    document.addEventListener("digitech:sync", () => {
      if (!window.DG_SYNC?.idle()) return;
      render();
      renderSheet();
    });
  }
  function announcements() {
    const canCreate = U.role === "admin" || U.role === "teacher";
    const AUDIENCE_BADGE = {
      All: "bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300",
      Students: "bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300",
      Parents: "bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300",
      Teachers: "bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300",
      Guests: "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300",
    };
    const renderFeed = (cards, empty = "No announcements yet") => {
      const body = $("#rows");
      if (!body) return;
      body.innerHTML = cards.length
        ? cards.join("")
        : `<div class="card p-10 text-center text-sm text-slate-500 dark:text-slate-400">${F.esc(empty)}</div>`;
      lucide.createIcons();
    };
    const render = () => {
      const audience =
        U.role === "student"
          ? "Students"
          : U.role === "parent"
            ? "Parents"
            : U.role === "teacher"
              ? "Teachers"
              : "All";
      const rows = F.get("announcements", [])
        .filter(
          (r) =>
            r.authorId === U.id ||
            r.createdBy === U.id ||
            r.audience === "All" ||
            r.audience === audience,
        )
        .sort(
          (a, b) =>
            new Date(b.createdAt || b.date) - new Date(a.createdAt || a.date),
        );
      renderFeed(
        rows.map((r) => {
          const author = users.find(
            (u) => u.id === (r.createdBy || r.authorId),
          );
          const name = F.userName(author) || "College Office";
          const initials = author
            ? `${(author.firstName || "")[0] || ""}${(author.lastName || "")[0] || ""}`.toUpperCase() || "DG"
            : "DT";
          const aud = r.audience || "All";
          const badge =
            AUDIENCE_BADGE[aud] ||
            "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300";
          const authorPhoto = F.photoUrl(author);
          const avatar = `<img src="${F.esc(authorPhoto)}" alt="${F.esc(name)}" class="h-11 w-11 shrink-0 rounded-full object-cover" onerror="this.onerror=null;this.src='/images/16432.png'" />`;
          return `<article class="card p-5">
              <div class="flex items-start gap-3">
                ${avatar}
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
                    <div class="flex items-center gap-2">
                      <span class="font-bold">${F.esc(name)}</span>
                      <span class="text-slate-400">·</span>
                      <time class="text-xs text-slate-400">${F.esc(APP.formatDate(r.createdAt || r.date))}</time>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold ${badge}">${F.esc(aud)}</span>
                  </div>
                  <span class="mt-1 inline-block rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">${F.esc(r.category || "General")}</span>
                </div>
              </div>
              <h3 class="mt-3 text-lg font-bold tracking-tight">${F.esc(r.title)}</h3>
              <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">${F.esc(r.message)}</p>
              ${r.image ? `<img src="${F.esc(r.image)}" alt="${F.esc(r.title)}" class="mt-3 max-h-80 w-full rounded border border-slate-200 object-cover dark:border-slate-700" onerror="this.style.display='none'" />` : ""}
            </article>`;
        }),
      );
    };
    $("#announcementForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const imageInput = $("#announcementImage");
      let image = null;
      if (imageInput?.files?.length) {
        imageInput.disabled = true;
        try {
          const uploaded = await DG.uploadImage(imageInput.files[0]);
          image = uploaded.photo || null;
        } catch (error) {
          console.error("Failed to upload announcement photo:", error);
          APP.toast("Photo upload failed. Try a smaller image.", "error");
          imageInput.disabled = false;
          return;
        }
        imageInput.disabled = false;
      }
      const audience = $("#audience").value;
      const assignedIds =
        U.role === "teacher"
          ? F.teacherStudents(U).map((s) => s.id)
          : users.filter((u) => u.role !== "admin").map((u) => u.id);
      const teacherParentIds =
        U.role === "teacher" ? F.parentIdsFor(assignedIds) : [];
      const audienceRoles = {
        All: null,
        Students: ["student"],
        Parents: ["parent"],
        Teachers: ["teacher"],
        Guests: ["guest"],
      };
      const ids = users
        .filter((u) =>
          U.role === "teacher"
            ? u.id === U.id ||
              assignedIds.includes(u.id) ||
              teacherParentIds.includes(u.id)
            : audience === "All" ||
              (audienceRoles[audience] || []).includes(u.role),
        )
        .map((u) => u.id);
      const a = {
        id: DG.generateId("ANN"),
        title: $("#title").value.trim(),
        message: $("#message").value.trim(),
        category: $("#category").value.trim() || "General",
        audience,
        createdBy: U.id,
        authorId: U.id,
        image,
        createdAt: new Date().toISOString(),
      };
      const all = F.get("announcements", []);
      all.push(a);
      F.save("announcements", all);
      F.notify(
        ids.filter((id) => id !== U.id),
        a.title,
        a.message,
        "announcement",
      );
      e.target.reset();
      resetPhotoUI();
      render();
      APP.toast("Announcement published");
    });
    const resetPhotoUI = () => {
      const imageInput = $("#announcementImage");
      if (imageInput) imageInput.value = "";
      const nameEl = $("#announcementImageName");
      if (nameEl) nameEl.textContent = "";
      const preview = $("#announcementImagePreview");
      if (preview) preview.classList.add("hidden");
    };
    const imageInput = $("#announcementImage");
    imageInput?.addEventListener("change", () => {
      const file = imageInput.files?.[0];
      const nameEl = $("#announcementImageName");
      const preview = $("#announcementImagePreview");
      const previewImg = $("#announcementImagePreviewImg");
      if (!file) {
        resetPhotoUI();
        return;
      }
      if (nameEl) nameEl.textContent = file.name;
      const reader = new FileReader();
      reader.onload = () => {
        if (previewImg) previewImg.src = reader.result;
        if (preview) preview.classList.remove("hidden");
        lucide.createIcons();
      };
      reader.readAsDataURL(file);
    });
    $("#announcementImageRemove")?.addEventListener("click", resetPhotoUI);
    render();
    // Live updates: re-render when announcements or user data change elsewhere.
    document.addEventListener("digitech:sync", () => {
      if (!window.DG_SYNC?.idle()) return;
      render();
    });
  }
  function safePreviewUrl(url) {
    const value = String(url || "").trim();
    if (!value) return null;
    if (value.startsWith("/api/portal/requirements/files/")) return value;
    if (/^https?:\/\//i.test(value)) return value;
    if (value.startsWith("//")) return "https:" + value;
    return null;
  }

  function openRequirementPreview(r) {
    const root = document.getElementById("modalRoot");
    if (!root) return;
    APP.closeModal();
    const url = safePreviewUrl(r.fileUrl || "");
    const hasPreview = url !== null;
    const ext = (url || "").split("?")[0].toLowerCase();
    const isImage = hasPreview && /\.(jpe?g|png|webp|gif)$/.test(ext);
    const isPdf = hasPreview && ext.endsWith(".pdf");
    const student = F.users().find((u) => u.id === r.studentId);
    const backdrop = document.createElement("div");
    backdrop.className =
      "modal-backdrop fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/50 p-4";
    const panel = document.createElement("div");
    panel.className =
      "w-full max-w-2xl rounded-2xl bg-white p-5 shadow-2xl dark:bg-slate-900";
    const close = () => APP.closeModal();
    panel.innerHTML = `
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-600 dark:text-blue-400">Requirement submission</p>
          <h2 class="mt-1 truncate text-lg font-extrabold">${F.esc(r.name)}</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">${F.esc(student ? F.userName(student) : r.studentId)} · ${F.esc(r.status)}</p>
        </div>
        <button type="button" data-preview-close class="shrink-0 rounded p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close"><i data-lucide="x" class="h-5 w-5"></i></button>
      </div>
      <div class="mt-4 max-h-[65vh] overflow-auto rounded border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
        ${!hasPreview ? `<div class="flex flex-col items-center gap-2 p-10 text-center"><i data-lucide="file-x" class="h-8 w-8 text-slate-400"></i><p class="text-sm text-slate-500 dark:text-slate-400">No file attached or the attached file cannot be previewed.</p></div>` : isImage ? `<img src="${F.esc(url)}" alt="${F.esc(r.name)}" class="mx-auto max-h-[58vh] object-contain">` : isPdf ? `<iframe src="${F.esc(url)}" title="${F.esc(r.name)}" class="h-[58vh] w-full"></iframe>` : `<div class="flex flex-col items-center gap-2 p-10 text-center"><i data-lucide="file-text" class="h-8 w-8 text-slate-400"></i><p class="text-sm text-slate-500 dark:text-slate-400">No inline preview for this file type.</p></div>`}
      </div>
      <div class="mt-4 flex justify-end gap-3">
        ${hasPreview ? `<a href="${F.esc(url)}" target="_blank" rel="noopener" class="rounded border border-slate-200 px-4 py-2 text-sm font-semibold dark:border-slate-700">Open original</a>` : ""}
        <button type="button" data-preview-close class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Close</button>
      </div>`;
    panel.querySelectorAll("[data-preview-close]").forEach((b) => b.addEventListener("click", close));
    backdrop.append(panel);
    root.append(backdrop);
    lucide.createIcons();
  }

  function requirements() {
    const studentSelect = $("#student");
    if (studentSelect) {
      studentSelect.innerHTML =
        (U.role === "admin" ? F.students() : F.teacherStudents(U))
          .map(
            (s) =>
              `<option value="${F.esc(s.id)}">${F.esc(F.userName(s))}</option>`,
          )
          .join("");
    }
    const render = () => {
      const all = F.get("requirements", []);
      renderRows(
        all.map((r) => {
          const actions = [];
          if (r.fileUrl) {
            actions.push(
              `<button class="inline-flex items-center gap-1 rounded bg-blue-600 px-2.5 py-1.5 text-[11px] font-semibold text-white hover:bg-blue-700" data-preview="${F.esc(r.id)}"><i data-lucide="eye" class="h-3.5 w-3.5"></i>Preview</button>`,
            );
          }
          if (r.status === "Submitted") {
            actions.push(
              `<button class="inline-flex items-center gap-1 rounded bg-emerald-600 px-2.5 py-1.5 text-[11px] font-semibold text-white hover:bg-emerald-700" data-approve="${F.esc(r.id)}"><i data-lucide="check" class="h-3.5 w-3.5"></i>Approve</button>`,
              `<button class="inline-flex items-center gap-1 rounded bg-rose-600 px-2.5 py-1.5 text-[11px] font-semibold text-white hover:bg-rose-700" data-reject="${F.esc(r.id)}"><i data-lucide="x" class="h-3.5 w-3.5"></i>Reject</button>`,
            );
          }
          const actionCell = actions.length ? `<div class="flex flex-wrap items-center gap-2">${actions.join("")}</div>` : "—";
          return `<tr class="border-t"><td class="p-3">${F.esc(r.name)}</td><td class="p-3">${F.esc(r.studentId)}</td><td class="p-3">${F.esc(r.status)}</td><td class="p-3">${F.esc(r.dueDate || "—")}</td><td class="p-3">${actionCell}</td></tr>`;
        }),
      );
      $("#rows")
        ?.querySelectorAll("[data-approve],[data-reject]")
        .forEach(
          (b) =>
            (b.onclick = () => {
              const a = F.get("requirements", []),
                r = a.find(
                  (x) =>
                    x.id === b.dataset.approve || x.id === b.dataset.reject,
                );
              if (!r) return;
              r.status = b.dataset.approve ? "Approved" : "Rejected";
              r.reviewedBy = U.id;
              r.reviewedAt = new Date().toISOString();
              F.save("requirements", a);
              render();
              APP.toast(`Requirement ${r.status.toLowerCase()}`);
              // Best-effort: a notify failure must never block the table update.
              try {
                F.notify(
                  [r.studentId, ...(F.parentIdsFor ? F.parentIdsFor([r.studentId]) : [])],
                  `Requirement ${r.status}`,
                  `${r.name} was ${r.status.toLowerCase()}.`,
                  "requirement",
                  r.id,
                );
              } catch (_) { /* notification skipped */ }
            }),
        );
      $("#rows")
        ?.querySelectorAll("[data-preview]")
        .forEach(
          (b) =>
            (b.onclick = () => {
              const r = F.get("requirements", []).find(
                (x) => x.id === b.dataset.preview,
              );
              if (r) openRequirementPreview(r);
            }),
        );
    };
   $("#requirementForm")?.addEventListener("submit", (e) => {
  e.preventDefault();

  const all = F.get("requirements", []);
  const selected = $("#student").value;
  const requirementName = $("#name").value.trim();
  const newId = DG.generateId("REQ");

  all.push({
    id: newId,
    name: requirementName,
    studentId: selected,
    dueDate: $("#dueDate").value,
    status: "Pending",
    createdAt: new Date().toISOString(),
  });

  // Save the assigned requirement
  F.save("requirements", all);

  e.target.reset();
  render();
  APP.toast("Requirement assigned");

  // Best-effort: a notify failure must never block the table update.
  try {
    F.notify(
      [selected, ...(F.parentIdsFor ? F.parentIdsFor([selected]) : [])],
      "New Requirement Assigned",
      `${requirementName} has been assigned to you.`,
      "requirement",
      newId
    );
  } catch (_) { /* notification skipped */ }
});

render();
    // Live updates: re-render when requirements or user data change elsewhere.
    document.addEventListener("digitech:sync", () => {
      if (!window.DG_SYNC?.idle()) return;
      render();
    });
  }
  shell();
  if (page === "attendance") attendance();
  if (page === "announcements") announcements();
  if (page === "requirements") requirements();
})();
