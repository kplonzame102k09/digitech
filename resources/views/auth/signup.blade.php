<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Signup | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100 text-slate-800">
    <div class="min-h-screen px-4 py-5 sm:px-6 lg:px-8">
        <!-- Top Header -->
        <header class="mx-auto flex max-w-7xl items-center justify-between pb-5">
            <a href="/" class="flex items-center gap-3">
                <img src="{{ asset('images/16432.png') }}" class="h-12 w-12 object-contain" alt="Digitech College">

                <div class="leading-tight">
                    <b class="text-sm font-bold tracking-wide text-green-900">
                        DIGITECH
                    </b>
                    <div class="text-[9px] font-semibold tracking-[0.25em] text-green-600">
                        COLLEGE
                    </div>
                </div>
            </a>
            <a href="{{ route('auth.login') }}"
                class="flex items-center gap-2 text-xs font-semibold text-green-700 hover:text-green-900">
                <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
                Back to Login
            </a>
        </header>
        <!-- Main Card -->
        <main class="mx-auto max-w-7xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <!-- Green Header -->
            <section
                class="relative overflow-hidden bg-gradient-to-br from-white via-emerald-50 to-green-200 px-6 py-8 sm:px-8 lg:px-10">
                <!-- Decorative circles -->
                <div class="absolute -right-20 -top-24 h-64 w-64 rounded-full bg-green-300/30 blur-3xl"></div>
                <div class="absolute -bottom-32 -left-20 h-72 w-72 rounded-full bg-emerald-200/40 blur-3xl"></div>
                <div class="relative z-10">
                    <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-green-200 bg-white/70 px-3 py-1.5 text-[11px] font-medium text-green-700">
                        <i data-lucide="user-plus" class="h-5 w-5"></i>
                        New Portal Account
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        Create your Digitech account
                    </h1>
                    <p class="mt-2 max-w-2xl text-xs leading-relaxed text-slate-600 sm:text-sm">
                        Register your account to access enrollment, academic services,
                        documents, competencies, and other college resources.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-4 text-[10px] text-slate-500">
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="shield-check" class="h-3.5 w-3.5 text-green-600"></i>
                            Role-based access
                        </span>
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="badge-check" class="h-3.5 w-3.5 text-green-600"></i>
                            Permanent User ID
                        </span>
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="database" class="h-3.5 w-3.5 text-green-600"></i>
                            LocalStorage prototype
                        </span>
                    </div>
                </div>
            </section>
            <!-- Form Area -->
            <section class="relative px-6 py-7 sm:px-8 lg:px-10">
                <!-- Watermark -->
                <img src="{{ asset('images/16432.png') }}"
                    class="pointer-events-none absolute left-1/2 top-1/2 z-0 w-[420px] -translate-x-1/2 -translate-y-1/2 opacity-[0.055]"
                    alt="">
                <div class="relative z-10">
                    <!-- Account Information -->
                    <div class="mb-5">
                        <div class="flex items-center gap-3">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-green-50 text-green-600">
                                <i data-lucide="user-round" class="h-4 w-4"></i>
                            </div>
                            <div>
                                <h2 class="text-xs font-bold text-slate-900">
                                    Account Information
                                </h2>
                                <p class="text-[9px] text-slate-400">
                                    Enter your basic account details.
                                </p>
                            </div>
                        </div>
                    </div>
                    <!-- Profile Picture -->
                    <div class="mb-1 rounded-xl border border-green-100 bg-green-50/40 p-4">
                        <div class="flex items-center gap-4">
                            <div
                                class="relative flex h-16 w-16 shrink-0 items-center justify-center rounded-full border-2 border-white bg-white shadow-md">
                                <span class="text-xs font-bold text-green-600">
                                    PHOTO
                                </span>
                                <button type="button"
                                    class="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-green-600 text-white shadow hover:bg-green-700">
                                    <i data-lucide="camera" class="h-3.5 w-3.5"></i>
                                </button>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-slate-900">
                                    Profile Picture
                                </h3>
                                <p class="mt-1 text-[10px] text-slate-500">
                                    Upload a display picture for your portal profile.
                                </p>
                                <p class="mt-1 text-[8px] text-slate-400">
                                    JPG, PNG, or WEBP · Recommended: square image
                                </p>
                            </div>
                        </div>
                    </div>
                    <!-- Form -->
                    <form method="POST" action="{{ route('signup.submit') }}" class="mt-1">
                        @csrf
                        <!-- Row 1 -->
                        <div class="grid gap-3 md:grid-cols-3">
                            <label class="block text-[11px] font-medium text-slate-700">
                                First Name *
                                <input name="firstName" required value="{{ old('firstName') }}"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100"
                                    placeholder="First name">
                            </label>
                            <label class="block text-[11px] font-medium text-slate-700">
                                Middle Name
                                <input name="middleName" value="{{ old('middleName') }}"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100"
                                    placeholder="Middle name">
                            </label>
                            <label class="block text-[11px] font-medium text-slate-700">
                                Last Name *
                                <input name="lastName" required value="{{ old('lastName') }}"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100"
                                    placeholder="Last name">
                            </label>
                        </div>
                        <!-- Row 2 -->
                        <div class="mt-3 grid gap-3 md:grid-cols-3">
                            <label class="block text-[11px] font-medium text-slate-700">
                                Email *
                                <input name="email" type="email" required value="{{ old('email') }}"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100"
                                    placeholder="you@example.com">
                            </label>
                            <label class="block text-[11px] font-medium text-slate-700">
                                Username *
                                <input name="username" value="{{ old('username') }}"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100"
                                    placeholder="Choose a username">
                            </label>
                            <label class="block text-[11px] font-medium text-slate-700">
                                Contact Number *
                                <input type="tel" name="contact" required value="{{ old('contact') }}"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100"
                                    placeholder="09XXXXXXXXX">
                            </label>
                        </div>
                        <!-- Row 3 -->
                        <div class="mt-3 grid gap-3 md:grid-cols-3">
                            <label class="block text-[11px] font-medium text-slate-700">
                                Password *
                                <input name="password" type="password" required
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100"
                                    placeholder="Create a password">
                            </label>
                            <label class="block text-[11px] font-medium text-slate-700">
                                Confirm Password *
                                <input name="password_confirmation" type="password" required
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100"
                                    placeholder="Repeat your password">
                            </label>
                            <label class="block text-[11px] font-medium text-slate-700">
                                Role *
                                <select name="role" id="role"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100">
                                    <option value="student" @selected(old('role') === 'student')>
                                        Student
                                    </option>
                                    <option value="teacher" @selected(old('role') === 'teacher')>
                                        Teacher
                                    </option>
                                    <option value="parent" @selected(old('role') === 'parent')>
                                        Parent
                                    </option>
                                    <option value="guest" @selected(old('role') === 'guest')>
                                        Guest
                                    </option>
                                </select>
                            </label>
                        </div>
                        <!-- Role Password -->
                        <label id="rolePasswordContainer"
                            class="mt-3 hidden block text-[11px] font-medium text-slate-700">
                            Role Password
                            <input type="password" required name="rollPassword" id="rolePassword"
                                class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100">
                        </label>
                        <!-- Student Information -->
                        <div class="mb-5 mt-7">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-green-50 text-green-600">
                                    <i data-lucide="graduation-cap" class="h-4 w-4"></i>
                                </div>
                                <div>
                                    <h2 class="text-xs font-bold text-slate-900">
                                        Student Information
                                    </h2>
                                    <p class="text-[9px] text-slate-400">
                                        Additional information for student accounts.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <!-- Birth / Address -->
                        <div class="grid gap-3 md:grid-cols-3">
                            <label class="block text-[11px] font-medium text-slate-700">
                                Birth Date
                                <input type="date" name="birthDate" required value="{{ old('birthDate') }}"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100">
                            </label>
                            <label class="block text-[11px] font-medium text-slate-700 md:col-span-2">
                                Birth Place
                                <input type="text" name="birthPlace" required value="{{ old('birthPlace') }}"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100"
                                    placeholder="Birth place">
                            </label>
                        </div>
                        <!-- Address -->
                        <div class="mt-3 grid gap-3 md:grid-cols-4">
                            <label class="block text-[11px] font-medium text-slate-700">
                                Region
                                <select id="region" name="region" required
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100">
                                    <option value="">
                                        Select Region
                                    </option>
                                    @foreach($regions as $r)
                                        <option value="{{ $r->region_code }}">
                                            {{ $r->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-[11px] font-medium text-slate-700">
                                Province
                                <select id="province" name="province" required disabled
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100">
                                    <option value="">
                                        Select Province
                                    </option>
                                </select>
                            </label>
                            <label class="block text-[11px] font-medium text-slate-700">
                                City / Municipality
                                <select id="city" name="city" required disabled
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100">
                                    <option value="">
                                        Select City
                                    </option>
                                </select>
                            </label>
                            <label class="block text-[11px] font-medium text-slate-700">
                                Barangay
                                <select id="barangay" name="barangay" required disabled
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none transition focus:border-green-500 focus:ring-2 focus:ring-green-100">
                                    <option value="">
                                        Select Barangay
                                    </option>
                                </select>
                            </label>
                        </div>
                        <!-- Errors -->
                        @if ($errors->any())
                            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                                <ul class="list-inside list-disc space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <!-- Bottom -->
                        <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <button type="submit"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-green-600 px-6 py-3 text-xs font-semibold text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                <i data-lucide="user-plus" class="h-4 w-4"></i>
                                Create Account
                            </button>
                            <a href="{{ route('auth.login') }}"
                                class="inline-flex items-center justify-center bg-white px-5 py-3 text-xs font-medium text-slate-600 transition hover:bg-slate-200">
                                Already have an account?
                            </a>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            lucide.createIcons();
            const region = document.getElementById('region');
            const province = document.getElementById('province');
            const city = document.getElementById('city');
            const barangay = document.getElementById('barangay');
            region.addEventListener('change', async () => {
                province.innerHTML = '<option value="">Select Province</option>';
                city.innerHTML = '<option value="">Select City</option>';
                barangay.innerHTML = '<option value="">Select Barangay</option>';
                province.disabled = true;
                city.disabled = true;
                barangay.disabled = true;
                if (!region.value) return;
                try {
                    const res = await fetch(`/api/provinces/${region.value}`);
                    if (!res.ok) {
                        throw new Error(`Province API error: ${res.status}`);
                    }
                    const data = await res.json();
                    console.log('Provinces:', data);
                    province.innerHTML =
                        '<option value="">Select Province</option>' +
                        data.map(p => `
                    <option value="${p.province_code}">
                        ${p.name}
                    </option>
                `).join('');
                    province.disabled = false;
                } catch (error) {
                    console.error('Failed to load provinces:', error);
                }
            });
            province.addEventListener('change', async () => {
                city.innerHTML = '<option value="">Select City</option>';
                barangay.innerHTML = '<option value="">Select Barangay</option>';
                city.disabled = true;
                barangay.disabled = true;
                if (!province.value) return;
                try {
                    const res = await fetch(`/api/cities/${province.value}`);
                    if (!res.ok) {
                        throw new Error(`City API error: ${res.status}`);
                    }
                    const data = await res.json();
                    console.log('Cities:', data);
                    city.innerHTML =
                        '<option value="">Select City</option>' +
                        data.map(c => `
                    <option value="${c.city_code}">
                        ${c.name}
                    </option>
                `).join('');
                    city.disabled = false;
                } catch (error) {
                    console.error('Failed to load cities:', error);
                }
            });
            city.addEventListener('change', async () => {
                barangay.innerHTML = '<option value="">Select Barangay</option>';
                barangay.disabled = true;
                console.log('Selected city:', city.value);
                if (!city.value || city.value === 'undefined') {
                    console.error('Invalid city value:', city.value);
                    return;
                }
                try {
                    const url = `/api/barangays/${city.value}`;
                    console.log('Barangay API:', url);
                    const res = await fetch(url);
                    if (!res.ok) {
                        throw new Error(`Barangay API error: ${res.status}`);
                    }
                    const data = await res.json();
                    console.log('Barangays:', data);
                    barangay.innerHTML =
                        '<option value="">Select Barangay</option>' +
                        data.map(b => `
                    <option value="${b.barangay_code}">
                        ${b.name}
                    </option>
                `).join('');
                    barangay.disabled = false;
                } catch (error) {
                    console.error('Failed to load barangays:', error);
                }
            });
            const role = document.getElementById('role');
            const rolePasswordContainer = document.getElementById('rolePasswordContainer');
            const rolePassword = document.getElementById('rolePassword');
            function updateRolePassword() {
                if (role.value === 'admin' || role.value === 'teacher') {
                    rolePasswordContainer.classList.remove('hidden'); rolePassword.required = true;
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