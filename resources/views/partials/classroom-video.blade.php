{{-- Meet-style video per classroom (self-hosted LiveKit, no recording).
     Expects $videoRole: 'teacher'|'student'. Wired by public/js/classroom-video.js
     with per-role endpoints passed from the host page. Degrades gracefully
     when LiveKit is unconfigured or the CDN is unreachable. --}}
<section class="card mb-6 p-5 sm:p-6" data-video-section>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-violet-600">Video conferencing</p>
            <h3 class="mt-1 text-xl font-extrabold">Live class</h3>
            <p class="mt-1 text-sm text-slate-500" data-video-status></p>
        </div>
        <button type="button" data-video-join-instant
            class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">
            <i data-lucide="video" class="h-4 w-4"></i>
            Join live room
        </button>
    </div>
    <div id="videoError-{{ $videoRole }}" role="alert"
        class="mt-3 hidden rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:bg-rose-950/30 dark:text-rose-300"></div>
    <div id="videoMeetings-{{ $videoRole }}" class="mt-4 space-y-3"></div>
    @if ($videoRole === 'teacher')
        <form data-video-schedule class="mt-4 grid gap-3 rounded-2xl bg-slate-50 p-4 dark:bg-slate-800 md:grid-cols-4">
            <input data-m-title maxlength="150" placeholder="Session title (e.g. Week 5 review)"
                class="input rounded-xl border px-3 py-2.5 md:col-span-2" />
            <input data-m-starts type="datetime-local" class="input rounded-xl border px-3 py-2.5" aria-label="Starts at" />
            <input data-m-ends type="datetime-local" class="input rounded-xl border px-3 py-2.5" aria-label="Ends at" />
            <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white dark:bg-slate-100 dark:text-slate-900 md:col-span-4">Schedule session</button>
        </form>
    @endif
    <div data-video-overlay class="mt-4 hidden rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                </span>
                <b class="text-sm">In call</b>
            </div>
            <button type="button" data-video-leave class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-700">Leave</button>
        </div>
        <div data-video-grid class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"></div>
        <div class="mt-4 flex flex-wrap items-center justify-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
            <button type="button" data-video-mic class="flex h-12 w-12 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800" title="Toggle microphone">
                <i data-lucide="mic" class="h-5 w-5"></i>
            </button>
            <button type="button" data-video-cam class="flex h-12 w-12 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800" title="Toggle camera">
                <i data-lucide="video" class="h-5 w-5"></i>
            </button>
            <button type="button" data-video-share class="flex h-12 w-12 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800" title="Share screen">
                <i data-lucide="monitor" class="h-5 w-5"></i>
            </button>
        </div>
        <p class="mt-3 text-center text-[11px] text-slate-400">Sessions are live only — no recording.</p>
    </div>
</section>
