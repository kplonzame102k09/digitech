<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Request Account | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script>
        (function () {
            try {
                var t = localStorage.getItem("dg-theme-last");
                if (t !== "dark" && t !== "light") {
                    t = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
                }
                document.documentElement.classList.toggle("dark", t === "dark");
                document.documentElement.style.colorScheme = t;
            } catch (e) {}
        })();
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
</head>

<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    <button type="button" data-theme-toggle title="Toggle dark / light mode" aria-label="Toggle dark / light mode"
        class="fixed right-4 top-4 z-50 inline-flex items-center justify-center p-2.5 rounded-xl hover:bg-green-500 hover:text-white dark:hover:bg-green-500">
        <i data-lucide="moon" data-theme-icon class="h-4 w-4"></i>
    </button>
    <div class="min-h-screen grid lg:grid-cols-2">
        <div
            class="hidden lg:flex relative overflow-hidden bg-gradient-to-br from-white via-emerald-50 to-green-200 text-slate-800 dark:text-slate-100 dark:from-slate-950 dark:via-emerald-950 dark:to-slate-900 p-12 flex-col justify-between">
            <div class="absolute -top-32 -right-32 w-80 h-80 bg-green-300/30 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-40 -left-32 w-96 h-96 bg-emerald-200/40 rounded-full blur-3xl"></div>

            <a href="{{ url('/') }}" class="relative z-10 flex gap-3 items-center">
                <img src="{{ asset('images/16432.png') }}"
                    class="w-14 h-14 flex items-center justify-center shadow-green-600/20">
                <b class="text-green-900 dark:text-green-200">
                    DIGITECH<br>
                    <small class="text-green-600 tracking-wider dark:text-green-400">COLLEGE</small>
                </b>
            </a>

            <div class="relative z-10 max-w-lg">
                <p class="text-green-600 uppercase text-sm font-semibold tracking-wider dark:text-green-400">
                    Request Account
                </p>

                <h1 class="text-4xl font-bold mt-3 leading-tight text-slate-900 dark:text-white">
                    New to Digitech?<br>
                    <span class="text-green-600 dark:text-green-400">Request your account here.</span>
                </h1>

                <p class="mt-5 text-slate-600 dark:text-slate-300 leading-relaxed">
                    Submit your details and our team will review your request.
                    You'll receive your login credentials once your account is approved.
                </p>

                <div class="mt-7 flex flex-wrap gap-3">
                    <span
                        class="inline-flex items-center gap-2 px-3 py-2 rounded bg-white/70 border border-green-100 text-sm text-slate-600 dark:text-slate-300 shadow-sm dark:bg-slate-900/70">
                        <i data-lucide="user-plus" class="w-4 text-green-600 dark:text-green-400"></i>
                        Student, Parent &amp; Guest
                    </span>
                    <span
                        class="inline-flex items-center gap-2 px-3 py-2 rounded bg-white/70 border border-green-100 text-sm text-slate-600 dark:text-slate-300 shadow-sm dark:bg-slate-900/70">
                        <i data-lucide="shield-check" class="w-4 text-green-600 dark:text-green-400"></i>
                        Admin-Reviewed
                    </span>
                </div>

                <div class="m-10 left-20 fixed inset-0 -z-10 pointer-events-none flex items-center justify-left">
                    <img src="{{ asset('images/16432.png') }}" alt=""
                        class="w-[500px] h-[500px] object-contain opacity-[0.15]">
                </div>
            </div>
        </div>

        <div class="flex items-center justify-center p-5">
            <div class="w-full max-w-2xl">
                <div class="p-7 sm:p-9">
                    <p class="text-sm font-semibold text-green-600 dark:text-green-400">Account Request</p>
                    <h1 class="text-2xl font-bold mt-1">Request an account</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">Fill in your details below. Fields marked * are required.</p>

                    <form id="requestAccountForm" class="space-y-4 mt-7">
                        <label class="block text-sm font-medium">
                            Role *
                            <select id="raRole" required
                                class="input mt-1 w-full rounded border px-3 py-3 cursor-pointer dark:bg-slate-900 dark:border-slate-700">
                                <option value="student">Student</option>
                                <option value="guest">Guest</option>
                                <option value="parent">Parent / Guardian</option>
                            </select>
                        </label>

                        <div class="grid grid-cols-2 gap-3">
                            <label class="block text-sm font-medium">
                                First name *
                                <input id="raFirstName" required maxlength="100"
                                    class="input mt-1 w-full rounded border px-3 py-3 dark:bg-slate-900 dark:border-slate-700"
                                    placeholder="Juan">
                            </label>
                            <label class="block text-sm font-medium">
                                Middle name
                                <input id="raMiddleName" maxlength="100"
                                    class="input mt-1 w-full rounded border px-3 py-3 dark:bg-slate-900 dark:border-slate-700"
                                    placeholder="Reyes">
                            </label>
                        </div>

                        <label class="block text-sm font-medium">
                            Last name *
                            <input id="raLastName" required maxlength="100"
                                class="input mt-1 w-full rounded border px-3 py-3 dark:bg-slate-900 dark:border-slate-700"
                                placeholder="Dela Cruz">
                        </label>

                        <label class="block text-sm font-medium">
                            Email address *
                            <input id="raEmail" type="email" required maxlength="255"
                                class="input mt-1 w-full rounded border px-3 py-3 dark:bg-slate-900 dark:border-slate-700"
                                placeholder="you@example.com">
                        </label>

                        <label class="block text-sm font-medium">
                            Contact number
                            <input id="raContact" maxlength="20"
                                class="input mt-1 w-full rounded border px-3 py-3 dark:bg-slate-900 dark:border-slate-700"
                                placeholder="09XXXXXXXXX">
                        </label>

                        <label class="block text-sm font-medium" id="raStrandField">
                            Program / Strand
                            <input id="raStrand" maxlength="120"
                                class="input mt-1 w-full rounded border px-3 py-3 dark:bg-slate-900 dark:border-slate-700"
                                placeholder="e.g. TVET – Cookery NC II">
                        </label>

                        <label class="block text-sm font-medium">
                            Reason for requesting an account *
                            <textarea id="raPurpose" required rows="3" minlength="20" maxlength="2000"
                                class="input mt-1 w-full rounded border px-3 py-3 resize-y dark:bg-slate-900 dark:border-slate-700"
                                placeholder="e.g. Enrolled as a first-year student for SY 2026–2027…"></textarea>
                            <span class="text-xs text-slate-400 dark:text-slate-500 mt-1 block">Minimum 20 characters</span>
                        </label>

                        <div id="raFeedback" class="hidden rounded bg-red-50 border border-red-200 p-3 text-sm text-red-700 dark:text-red-300 dark:bg-red-950/40"></div>

                        <div id="raSuccess" class="hidden rounded bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-700 dark:text-emerald-300">
                            <p class="font-semibold">Request submitted!</p>
                            <p id="raSuccessMessage" class="mt-1"></p>
                            <a href="{{ route('auth.login') }}" class="mt-3 inline-block text-green-600 font-semibold dark:text-green-400">← Back to login</a>
                        </div>

                        <button type="submit" id="raSubmit"
                            class="w-full bg-green-600 hover:bg-green-700 text-white rounded py-3.5 font-semibold">
                            Submit request
                        </button>
                    </form>

                    <p class="text-center text-sm text-slate-500 dark:text-slate-400 mt-6">
                        Already have an account?
                        <a href="{{ route('auth.login') }}" class="text-green-600 font-semibold dark:text-green-400">Sign in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const link = document.createElement('link');
        link.rel = 'icon';
        link.type = 'image/png';
        link.href = '/images/16432.png';
        document.head.appendChild(link);   
        (function () {
            var roleField = document.getElementById('raRole');
            var strandField = document.getElementById('raStrandField');

            function syncStrandVisibility() {
                strandField.style.display = roleField.value === 'student' ? '' : 'none';
            }
            roleField.addEventListener('change', syncStrandVisibility);
            syncStrandVisibility();

            document.getElementById('requestAccountForm').addEventListener('submit', function (e) {
                e.preventDefault();

                var feedback = document.getElementById('raFeedback');
                var success = document.getElementById('raSuccess');
                var submitBtn = document.getElementById('raSubmit');

                feedback.classList.add('hidden');
                success.classList.add('hidden');

                var payload = {
                    firstName: document.getElementById('raFirstName').value.trim(),
                    middleName: document.getElementById('raMiddleName').value.trim() || null,
                    lastName: document.getElementById('raLastName').value.trim(),
                    role: roleField.value,
                    email: document.getElementById('raEmail').value.trim(),
                    contact: document.getElementById('raContact').value.trim() || null,
                    strand: document.getElementById('raStrand').value.trim() || null,
                    purpose: document.getElementById('raPurpose').value.trim()
                };

                if (!payload.firstName || !payload.lastName || !payload.email || !payload.purpose) {
                    feedback.textContent = 'Please fill in all required fields.';
                    feedback.classList.remove('hidden');
                    return;
                }
                if (payload.purpose.length < 20) {
                    feedback.textContent = 'Please provide a more detailed reason (at least 20 characters).';
                    feedback.classList.remove('hidden');
                    return;
                }

                var csrf = document.querySelector('meta[name="csrf-token"]');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting…';

                fetch('/account-requests', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf ? csrf.content : '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                })
                .then(function (res) { return res.json().then(function (body) { return { status: res.status, body: body }; }); })
                .then(function (result) {
                    if (result.status === 201 && result.body.ok) {
                        var rid = result.body.request.request_id || '';
                        document.getElementById('raSuccessMessage').textContent =
                            'Your account request (' + rid + ') has been received. Please wait for admin review. You will be contacted at ' + payload.email + '.';
                        success.classList.remove('hidden');
                        document.getElementById('requestAccountForm').reset();
                        syncStrandVisibility();
                        return;
                    }
                    var errors = result.body.errors || {};
                    var messages = Object.keys(errors).map(function (k) { return errors[k][0]; });
                    feedback.textContent = messages.length ? messages.join(' ') : 'Submission failed. Please try again.';
                    feedback.classList.remove('hidden');
                })
                .catch(function () {
                    feedback.textContent = 'A network error occurred. Please check your connection and try again.';
                    feedback.classList.remove('hidden');
                })
                .finally(function () {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit request';
                });
            });

            var themeToggle = document.querySelector('[data-theme-toggle]');
            if (themeToggle) {
                themeToggle.addEventListener('click', function () {
                    var isDark = document.documentElement.classList.toggle('dark');
                    localStorage.setItem('dg-theme-last', isDark ? 'dark' : 'light');
                    var icon = document.querySelector('[data-theme-icon]');
                    if (icon) icon.setAttribute('data-lucide', isDark ? 'sun' : 'moon');
                    if (window.lucide) lucide.createIcons();
                });
            }

            if (window.lucide) lucide.createIcons();
        })();
    </script>
</body>

</html>
