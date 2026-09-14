<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Join classroom | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

<body class="flex min-h-screen items-center justify-center bg-slate-50 p-4 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    <div class="card w-full max-w-md p-6 text-center sm:p-8">
        <img src="{{ asset('images/16432.png') }}" alt="Digitech College" class="mx-auto h-14 w-14 rounded-2xl object-cover" />
        <p class="mt-4 text-xs font-bold uppercase tracking-[0.18em] text-emerald-600 dark:text-emerald-400">Classroom invite</p>
        <h2 id="joinName" class="mt-2 text-2xl font-extrabold">Loading…</h2>
        <p id="joinMeta" class="mt-1 text-sm text-slate-500 dark:text-slate-400"></p>
        <p id="joinDesc" class="mt-3 text-sm text-slate-600 dark:text-slate-300"></p>
        <div id="joinError" role="alert"
            class="mt-4 hidden rounded bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:bg-rose-950/30 dark:text-rose-300"></div>
        <button id="joinBtn" type="button"
            class="mt-6 hidden w-full rounded bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700">Join
            classroom</button>
        <a href="/login" class="mt-4 inline-block text-sm font-semibold text-slate-500 dark:text-slate-400">Back to login</a>
    </div>
    <script>
        (function () {
            const token = @json($token);
            const nameEl = document.getElementById("joinName");
            const metaEl = document.getElementById("joinMeta");
            const descEl = document.getElementById("joinDesc");
            const errorEl = document.getElementById("joinError");
            const btn = document.getElementById("joinBtn");
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
            const setError = (m) => {
                errorEl.textContent = m || "";
                errorEl.classList.toggle("hidden", !m);
            };
            const post = async (url, body) => {
                const r = await fetch(url, {
                    method: "POST",
                    headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": csrf, "X-Requested-With": "XMLHttpRequest" },
                    credentials: "same-origin",
                    body: JSON.stringify(body || {}),
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok || d.ok === false) throw new Error(d.error || `Request failed (${r.status})`);
                return d;
            };
            (async () => {
                try {
                    const r = await fetch(`/api/classrooms/preview/${encodeURIComponent(token)}`, {
                        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
                        credentials: "same-origin",
                    });
                    const d = await r.json().catch(() => ({}));
                    if (!r.ok || d.ok === false) throw new Error(d.error || "Invite not found.");
                    nameEl.textContent = d.classroom.name || "Classroom";
                    metaEl.textContent = `${d.classroom.subject || "No subject"} · ${d.classroom.teacherName || "Your teacher"}`;
                    descEl.textContent = d.classroom.description || "";
                    if (d.classroom.joined) {
                        btn.textContent = "Already joined — open portal";
                        btn.onclick = () => (window.location.href = "/student/classrooms");
                    } else {
                        btn.onclick = async () => {
                            setError("");
                            btn.disabled = true;
                            try {
                                await post("/student/api/classrooms/join", { token });
                                window.location.href = "/student/classrooms";
                            } catch (e) {
                                setError(e.message || "Could not join. Only student accounts can join.");
                                btn.disabled = false;
                            }
                        };
                    }
                    btn.classList.remove("hidden");
                } catch (e) {
                    nameEl.textContent = "Invite unavailable";
                    setError(e.message || "This link is invalid or expired.");
                }
                if (window.lucide) lucide.createIcons();
            })();
        })();
    </script>
</body>

</html>
