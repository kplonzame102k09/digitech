<!doctype html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Online Enrollment | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body data-student-page="enrollment" class="student-portal min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('student.components.sidebar')
    <div class="lg:pl-64">
        @include('student.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl">
                <section class="student-hero mb-7 p-6 sm:p-8">
                    <div class="relative z-10 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-100">Enrollment</p>
                            <h2 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">Build your Student
                                Record</h2>
                            <p class="mt-3 max-w-2xl text-sm leading-6 text-white">
                                Complete the details below, save a draft while you work, or submit
                                your enrollment for review.
                            </p>
                        </div>
                    </div>
                </section>

                <form id="enr" class="space-y-6">
                    @csrf
                    <section class="card p-5 sm:p-7">
                        <div class="flex items-start gap-3 border-b border-slate-200 pb-5 dark:border-slate-700">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300">
                                <i data-lucide="user-round"></i>
                            </span>
                            <div>
                                <h3 class="font-extrabold">Student information</h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    Verify your identity and add your current contact details.
                                </p>
                            </div>
                        </div>
                        <div class="mt-6 grid gap-5 md:grid-cols-3">
                            <label class="text-sm font-semibold">
                                Full name
                                <input id="fullName" readonly
                                    class="input mt-1.5 w-full rounded-xl border bg-slate-50 px-3 py-2.5 dark:bg-slate-800" />
                            </label>
                            <label class="text-sm font-semibold">
                                Student ID
                                <input id="studentId" readonly
                                    class="input mt-1.5 w-full rounded-xl border bg-slate-50 px-3 py-2.5 font-mono text-xs dark:bg-slate-800" />
                            </label>
                            <label class="text-sm font-semibold">
                                Contact number
                                <input id="contact" required class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"
                                    placeholder="e.g. +63 900 000 0000" />
                            </label>
                            <label class="text-sm font-semibold">
                                Birth date
                                <input id="birthDate" type="date" required
                                    class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" />
                            </label>
                            <label class="text-sm font-semibold md:col-span-2">
                                Home address
                                <input id="address" required class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"
                                    placeholder="House number, street, barangay, city" />
                            </label>
                        </div>
                    </section>
                    <section class="card p-5 sm:p-7">
                        <div class="flex items-start gap-3 border-b border-slate-200 pb-5 dark:border-slate-700">
                            <span
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300">
                                <i data-lucide="contact"></i>
                            </span>
                            <div>
                                <h3 class="font-extrabold">Guardian information</h3>
                                <p class="mt-1 text-sm text-slate-500">Add a guardian contact for enrollment and
                                    emergency
                                    communication.</p>
                            </div>
                        </div>
                        <div class="mt-6 grid gap-5 md:grid-cols-2">
                            <label class="text-sm font-semibold">
                                Guardian name
                                <input id="guardianName" required
                                    class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" placeholder="Full name" />
                            </label>
                            <label class="text-sm font-semibold">
                                Guardian contact
                                <input id="guardianContact" required
                                    class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"
                                    placeholder="Phone number or email" />
                            </label>
                        </div>
                    </section>
                    <section class="card p-5 sm:p-7">
                        <div class="flex items-start gap-3 border-b border-slate-200 pb-5 dark:border-slate-700">
                            <span
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
                                <i data-lucide="graduation-cap"></i>
                            </span>
                            <div>
                                <h3 class="font-extrabold">Academic information</h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    Choose the program and school-year details for this enrollment.
                                </p>
                            </div>
                        </div>
                        <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            <label class="text-sm font-semibold">
                                Program type
                                <select id="programType" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">
                                    <option>Senior High</option>
                                    <option>TVET</option>
                                </select>
                            </label>
                            <label class="text-sm font-semibold">
                                Grade level
                                <input id="gradeLevel" required
                                    class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"
                                    placeholder="e.g. Grade 12" />
                            </label>
                            <label class="text-sm font-semibold">
                                Strand / program
                                <input id="strand" required class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"
                                    placeholder="e.g. ICT" />
                            </label>
                            <label class="text-sm font-semibold">
                                Track / qualification
                                <input id="track" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"
                                    placeholder="e.g. CSS NC II" />
                            </label>
                            <label class="text-sm font-semibold">
                                Training level
                                <input id="training" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"
                                    placeholder="Optional for TVET" />
                            </label>
                            <label class="text-sm font-semibold">
                                School year
                                <input id="schoolYear" required
                                    class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" value="2026-2027" />
                            </label>
                        </div>
                    </section>
                    <section class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div>
                            <p class="text-sm font-bold">Ready to continue?</p>
                            <p class="mt-1 text-xs text-slate-500">You can save a draft and return later before
                                submitting.</p>
                            <div id="status" class="mt-3"></div>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <button type="button" id="saveDraftBtn" onclick="saveEnrollment('Draft')"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold dark:border-slate-700">
                                <i data-lucide="save" class="h-4 w-4"></i>
                                Save draft
                            </button>
                            <button type="button" id="submitEnrollmentBtn" onclick="saveEnrollment('Submitted')"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700">
                                <i data-lucide="send" class="h-4 w-4"></i>
                                Submit enrollment
                            </button>
                        </div>
                    </section>
                </form>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
@include('partials.portal-scripts', ['portalPage' => 'student/student.js'])
</body>

</html>