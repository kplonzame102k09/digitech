<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50">
    <div class="min-h-screen grid lg:grid-cols-2">
        <div
            class="hidden lg:flex relative overflow-hidden bg-gradient-to-br from-white via-emerald-50 to-green-200 text-slate-800 p-12 flex-col justify-between">
            <div class="absolute -top-32 -right-32 w-80 h-80 bg-green-300/30 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-40 -left-32 w-96 h-96 bg-emerald-200/40 rounded-full blur-3xl"></div>

            <a href="{{ ('/index') }}" class="relative z-10 flex gap-3 items-center">
                <img src="{{ asset('images/16432.png') }}"
                    class="w-14 h-14 flex items-center justify-center shadow-green-600/20">
                <!-- <span class="bg-green-600 text-white rounded-lg p-2">
                    <i data-lucide="graduation-cap" class="w-8 h-8"></i>
                </span> -->

                <b class="text-green-900">
                    DIGITECH<br>
                    <small class="text-green-600 tracking-wider">COLLEGE</small>
                </b>
            </a>

            <div class="relative z-10 max-w-lg">
                <p class="text-green-600 uppercase text-sm font-semibold tracking-wider">
                    Integrated Web Portal
                </p>

                <h1 class="text-5xl font-bold mt-3 leading-tight text-slate-900">
                    One portal for your
                    <span class="text-green-600">college journey.</span>
                </h1>

                <p class="mt-5 text-slate-600 leading-relaxed">
                    Enrollment, documents, grades, competencies, and role-specific services
                    — all connected in one convenient portal.
                </p>

                <div class="mt-7 flex flex-wrap gap-3">
                    <span
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white/70 border border-green-100 text-sm text-slate-600 shadow-sm">
                        <i data-lucide="shield-check" class="w-4 text-green-600"></i>
                        Secure Access
                    </span>

                    <span
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white/70 border border-green-100 text-sm text-slate-600 shadow-sm">
                        <i data-lucide="smartphone" class="w-4 text-green-600"></i>
                        Mobile Friendly
                    </span>

                    <span
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white/70 border border-green-100 text-sm text-slate-600 shadow-sm">
                        <i data-lucide="layers-3" class="w-4 text-green-600"></i>
                        All-in-One
                    </span>
                    <div class="m-10 left-20 fixed inset-0 -z-10 pointer-events-none flex items-center justify-left">
                        <img src="{{ asset('images/16432.png') }}" alt=""
                            class="w-[500px] h-[500px] object-contain opacity-[0.15]">
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-center p-5">
            <div class="w-full max-w-md">
                <div class="card p-7 sm:p-9 shadow-xl">
                    <p class="text-sm font-semibold text-green-600">Welcome back</p>
                    <h1 class="text-2xl font-bold mt-1">Sign in to your portal</h1>
                    <p class="text-sm text-slate-500 mt-2">Use your generated User ID or registered email.</p>
                    <form method="POST" action="{{ route('login.submit') }}" class="space-y-4 mt-7">
                        @csrf

                        <label class="block text-sm font-medium">
                            Role
                            <select id="role" name="role"
                                class="input mt-1 w-full rounded-xl border px-3 py-3 cursor-pointer">
                                <option value="student" @selected(old('role') === 'student')>Student</option>
                                <option value="teacher" @selected(old('role') === 'teacher')>Teacher</option>
                                <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                                <option value="parent" @selected(old('role') === 'parent')>Parent</option>
                                <option value="guest" @selected(old('role') === 'guest')>Guest</option>
                            </select>
                        </label>

                        <label class="block text-sm font-medium">
                            User ID
                            <input id="value" name="user_id" required value="{{ old('user_id') }}"
                                class="input mt-1 w-full rounded-xl border px-3 py-3" placeholder="STU-2026-XXXXXX">
                        </label>

                        <label class="block text-sm font-medium">
                            Account Password
                            <input name="password" type="password" required
                                class="input mt-1 w-full rounded-xl border px-3 py-3">
                        </label>

                        <label id="rolePasswordContainer" class="block text-sm font-medium hidden">
                            Role Password
                            <input type="password" name="rolePassword" id="rolePassword"
                                class="input mt-1 w-full rounded-xl border px-3 py-3" required>
                        </label>

                        @if ($errors->any())
                            <div class="rounded-xl bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                                <ul class="list-disc list-inside">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <button
                            class="w-full bg-green-600 hover:bg-green-700 text-white rounded-xl py-3.5 font-semibold">
                            Sign In
                        </button>
                    </form>
                    <p class="text-center text-sm text-slate-500 mt-6">
                        No account?
                        <br>
                        <a class="font-semibold text-green-700" href="{{ route('auth.signup') }}">Signup</a>
                        <br>
                        -----------------------------------or-----------------------------------
                        <br>
                        Go to the nearest Digitech College branch.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () { 
            lucide.createIcons();
            const roleSelect = document.getElementById('role');
            const userIdInput = document.getElementById('value');

            const placeholders = {
                student: 'STU-2026-XXXXXX',
                teacher: 'TCH-2026-XXXXXX',
                admin: 'ADM-2026-XXXXXX',
                parent: 'PRT-2026-XXXXXX',
                guest: 'GUEST-2026-XXXXX'
            };

            function updateUserIdPlaceholder() {
                userIdInput.placeholder = placeholders[roleSelect.value];
                userIdInput.value = '';
            }

            roleSelect.addEventListener('change', updateUserIdPlaceholder);
            updateUserIdPlaceholder();
            const role = document.getElementById('role'); 
            const rolePasswordContainer = document.getElementById('rolePasswordContainer'); 
            const rolePassword = document.getElementById('rolePassword'); 
            function updateRolePassword() { 
                const selectedRole = role.value.toLowerCase(); 
                if (selectedRole === 'admin' || selectedRole === 'teacher') { 
                    rolePasswordContainer.classList.remove('hidden'); 
                    rolePassword.required = true; 
                } else { 
                    rolePasswordContainer.classList.add('hidden'); 
                    rolePassword.required = false; 
                    rolePassword.value = '';
                } 
            } role.addEventListener('change', updateRolePassword); 
            updateRolePassword();
});
    </script>
</body>

</html>