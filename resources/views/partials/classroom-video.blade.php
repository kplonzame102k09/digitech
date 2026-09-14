{{-- Meet-style video per classroom (self-hosted LiveKit, no recording).
     Expects $videoRole: 'teacher'|'student'. Wired by public/js/classroom-video.js
     with per-role endpoints passed from the host page. Degrades gracefully
     when LiveKit is unconfigured or the CDN is unreachable. --}}
<section class="card mb-6 overflow-hidden p-5 sm:p-6" data-video-section>
    <div class="video-hero relative overflow-hidden rounded-2xl p-5 sm:p-6">
        <div class="relative flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-violet-300">Video conferencing</p>
                <h3 class="mt-1 text-xl font-extrabold text-white">Live class</h3>
                <p class="mt-1 flex min-h-5 items-center gap-2 text-sm text-white/70" data-video-status></p>
            </div>
            <button type="button" data-video-join-instant
                class="inline-flex shrink-0 items-center gap-2 rounded-full bg-emerald-500 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-950/40 transition hover:bg-emerald-400">
                @if ($videoRole === 'teacher')
                    <i data-lucide="play" class="h-4 w-4"></i>
                    Start instant meeting
                @else
                    <i data-lucide="video" class="h-4 w-4"></i>
                    Join live room
                @endif
            </button>
        </div>
    </div>
    <div id="videoError-{{ $videoRole }}" role="alert"
        class="mt-3 hidden rounded bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:bg-rose-950/30 dark:text-rose-300"></div>

    <div class="mt-5">
        <div class="mb-3 flex items-center justify-between">
            <h4 class="text-sm font-bold text-slate-700 dark:text-slate-200">Upcoming sessions</h4>
        </div>
        <div id="videoMeetings-{{ $videoRole }}" class="space-y-3"></div>
    </div>

    <div class="mt-5">
        <div class="mb-3 flex items-center justify-between">
            <h4 class="text-sm font-bold text-slate-700 dark:text-slate-200">Session history</h4>
            <button type="button" data-toggle-history class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-violet-600 transition hover:border-violet-300 dark:border-slate-700 dark:text-violet-400">
                <span data-history-label>Show</span>
            </button>
        </div>
        <div id="videoHistory-{{ $videoRole }}" class="hidden space-y-2"></div>
    </div>

    @if ($videoRole === 'teacher')
        <form data-video-schedule class="video-schedule mt-5 grid gap-3 rounded-2xl p-4 md:grid-cols-4">
            <input data-m-title maxlength="150" placeholder="Session title (e.g. Week 5 review)"
                class="input rounded border px-3 py-2.5 md:col-span-2" />
            <input data-m-starts type="datetime-local" class="input rounded border px-3 py-2.5" aria-label="Starts at" />
            <input data-m-ends type="datetime-local" class="input rounded border px-3 py-2.5" aria-label="Ends at" />
            <button class="inline-flex items-center justify-center gap-2 rounded bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-violet-500 md:col-span-4">
                <i data-lucide="calendar-plus" class="h-4 w-4"></i>Schedule session
            </button>
        </form>
    @endif
    <div data-video-overlay class="video-stage mt-5 hidden p-4 sm:p-5">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="video-live-dot relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                </span>
                <b class="text-sm font-bold text-white">In call</b>
                <span class="rounded-full bg-white/10 px-2.5 py-0.5 text-[11px] font-semibold text-white/70" data-video-count></span>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @if ($videoRole === 'teacher')
                    <button type="button" data-video-end-call class="inline-flex items-center gap-1.5 rounded-full bg-amber-500 px-4 py-2 text-xs font-bold text-white transition hover:bg-amber-400" title="End the live session for everyone">
                        <i data-lucide="phone-off" class="h-3.5 w-3.5"></i>End call
                    </button>
                @endif
                <button type="button" data-video-leave class="inline-flex items-center gap-1.5 rounded-full bg-rose-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-rose-500">
                    <i data-lucide="log-out" class="h-3.5 w-3.5"></i>Leave
                </button>
            </div>
        </div>
        <div data-video-grid class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"></div>
        <div class="video-controls mx-auto mt-5 flex w-fit flex-wrap items-center justify-center gap-3 rounded-full px-5 py-3">
            <button type="button" data-video-mic class="flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20" title="Toggle microphone">
                <i data-lucide="mic" class="h-5 w-5"></i>
            </button>
            <button type="button" data-video-cam class="flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20" title="Toggle camera">
                <i data-lucide="video" class="h-5 w-5"></i>
            </button>
            <button type="button" data-video-share class="flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20" title="Share screen">
                <i data-lucide="monitor" class="h-5 w-5"></i>
            </button>
        </div>
        <p class="mt-3 text-center text-[11px] text-white/40">Sessions are live only — no recording.</p>
    </div>
</section>
