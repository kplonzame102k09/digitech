{{--
    Classroom message channel panel.

    Shared by the teacher and student classroom-detail pages, the same way
    partials/classroom-video.blade.php is. The tab button itself stays in each
    page's tab bar (it has to sit inside that specific flex row); this partial
    is the panel body only.

    The panel is inert until public/js/classroom-messages.js loads. That script
    owns rendering, the composer, attachment upload and the idle-gated poll, and
    is a no-op when the page has no message panel.
--}}
<section data-detail-panel="messages" class="hidden">
    {{-- dvh on phones so the browser chrome/URL bar collapsing does not clip
         the composer; vh from sm up. min-h is reduced on phones so a short
         landscape viewport cannot push the composer off-screen. --}}
    <div class="card flex h-[70dvh] min-h-[300px] flex-col overflow-hidden p-0 sm:h-[70vh] sm:min-h-[420px]">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-3 py-3 sm:px-5 sm:py-4 dark:border-slate-800">
            <div class="min-w-0">
                <h3 class="font-extrabold">Messages</h3>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    Shared with everyone in this classroom.
                </p>
            </div>
            <span data-msg-badge
                class="hidden shrink-0 rounded-full bg-violet-600 px-2 py-0.5 text-xs font-bold text-white dark:bg-violet-500"></span>
        </div>

        <div data-msg-error class="hidden mx-3 mt-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 sm:mx-5 sm:mt-4 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200"></div>

        {{-- overscroll-contain stops a flick on the thread from dragging the
             whole page once the thread is scrolled to its end. --}}
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3 sm:px-5 sm:py-4">
            {{-- Only revealed when the server reports older history, so the
                 channel does not open with a dead control. --}}
            <button type="button" data-msg-older
                class="mx-auto mb-4 hidden items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                <i data-lucide="chevron-up" class="h-3.5 w-3.5"></i>
                Load earlier messages
            </button>
            <div data-msg-empty class="flex h-full flex-col items-center justify-center gap-2 py-10 text-center">
                <i data-lucide="messages-square" class="h-8 w-8 text-slate-300 dark:text-slate-600"></i>
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">No messages yet</p>
                <p class="max-w-xs text-xs text-slate-400 dark:text-slate-500">
                    Start the conversation. Everyone in this classroom can read it.
                </p>
            </div>
            <div data-msg-list class="flex flex-col gap-3 sm:gap-4"></div>
        </div>

        <form data-msg-form class="border-t border-slate-200 p-3 sm:p-4 dark:border-slate-800">
            {{-- text-base on phones: anything smaller makes iOS Safari zoom the
                 viewport in on focus, which then never scrolls back out. --}}
            <textarea data-msg-input rows="2" maxlength="2000"
                placeholder="Write a message&hellip;  (Enter to send, Shift+Enter for a new line)"
                class="input w-full resize-y rounded border px-3 py-2.5 text-base sm:text-sm"></textarea>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 sm:flex-nowrap sm:gap-3">
                <div class="min-w-0 flex-1">
                    <input type="file" id="msgFileInput" data-msg-file class="sr-only"
                        accept=".pdf,.jpeg,.jpg,.png,.webp" />
                    <label for="msgFileInput"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        <i data-lucide="paperclip" class="h-3.5 w-3.5"></i>
                        <span data-msg-file-label class="max-w-[10rem] truncate">Attach</span>
                    </label>
                    <p class="mt-1 text-[10px] text-slate-400 sm:text-[11px] dark:text-slate-500">PDF, image, up to 5 MB</p>
                </div>
                <button type="submit" data-msg-send
                    class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-violet-500 dark:hover:bg-violet-600">
                    <i data-lucide="send" class="h-4 w-4"></i>
                    Send
                </button>
            </div>
        </form>
    </div>
</section>
