<section class="rounded-xl border border-slate-100 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-800/40">
    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Family &amp; contact</h4>
    <div class="mt-4 grid gap-5 sm:grid-cols-2">
        <label class="text-sm font-semibold sm:col-span-2">Email address
            <input data-profile-field="email" readonly
                class="input mt-1.5 w-full rounded border bg-slate-50 px-3 py-3 dark:bg-slate-800" />
        </label>
        <label class="text-sm font-semibold">Contact number
            <input data-profile-field="contact" required maxlength="20"
                class="input mt-1.5 w-full rounded border px-3 py-3"
                placeholder="e.g. +63 900 000 0000" />
        </label>
        <label class="text-sm font-semibold">Occupation
            <input data-profile-field="occupation" maxlength="120"
                class="input mt-1.5 w-full rounded border px-3 py-3"
                placeholder="Your occupation" />
        </label>
        <label class="text-sm font-semibold sm:col-span-2">Emergency contact
            <input data-profile-field="emergencyContact" maxlength="120"
                class="input mt-1.5 w-full rounded border px-3 py-3"
                placeholder="Phone number or contact name" />
        </label>
    </div>
</section>
<section class="rounded-xl border border-slate-100 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-800/40">
    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Address</h4>
    <div class="mt-4 grid gap-5 sm:grid-cols-2">
        <label class="text-sm font-semibold">Barangay
            <input data-profile-field="barangay" maxlength="120"
                class="input mt-1.5 w-full rounded border px-3 py-3"
                placeholder="e.g. Barangay Pala-o" />
        </label>
        <label class="text-sm font-semibold">City / Municipality
            <input data-profile-field="city" maxlength="120"
                class="input mt-1.5 w-full rounded border px-3 py-3"
                placeholder="e.g. Iligan City" />
        </label>
        <label class="text-sm font-semibold">Province
            <input data-profile-field="province" maxlength="120"
                class="input mt-1.5 w-full rounded border px-3 py-3"
                placeholder="e.g. Lanao del Norte" />
        </label>
        <label class="text-sm font-semibold">Region
            <input data-profile-field="region" maxlength="120"
                class="input mt-1.5 w-full rounded border px-3 py-3"
                placeholder="e.g. Region X" />
        </label>
        <label class="text-sm font-semibold sm:col-span-2">Street address
            <input data-profile-field="address" maxlength="255"
                class="input mt-1.5 w-full rounded border px-3 py-3"
                placeholder="House number, street" />
        </label>
    </div>
</section>