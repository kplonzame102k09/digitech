<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>User Management | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../css/custom.css" />
    @vite([ 'resources/css/app.css', 'resource/js/app.js' ])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('components.sidebar')
    <div class="lg:pl-64">
        @include('components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="student-hero mb-7 p-6 sm:p-8 mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-purple-600">
                            Administration
                        </p>
                        <h2 class="mt-1 text-3xl font-bold tracking-tight">
                            User Management
                        </h2>
                        <p class="mt-2 text-white">
                            Create, review, secure, and maintain portal accounts.
                        </p>
                    </div>
                    <button type="button" data-create-user 
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-green-700">
                        <i data-lucide="user-plus" class="h-4 w-4"></i>
                        Add user
                    </button>
                </section>
                <section class="card p-4">
                    <div class="grid gap-3 lg:grid-cols-[1fr_auto_auto_auto_auto] lg:items-center">
                        <label class="sr-only" for="q">
                            Search users
                        </label>
                        <input id="q" class="input w-full rounded-xl border px-3 py-3" placeholder="Search by name, email, username, or User ID" />
                        <select id="filter" class="input rounded-xl border px-3 py-3">
                            <option value="">All roles</option>
                            <option value="student">Students</option>
                            <option value="teacher">Teachers</option>
                            <option value="admin">Admins</option>
                            <option value="parent">Parents</option>
                        </select>
                        <select id="statusFilter" class="input rounded-xl border px-3 py-3">
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                         <button type="button" data-import-users
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-green-500 border border-slate-600 px-4 py-3 text-sm font-semibold dark:border-slate-500">
                            <i data-lucide="upload" class="h-4 w-4"></i>
                            Import CSV
                        </button>
                        <button type="button" data-export-users
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 border border-slate-600 px-4 py-3 text-sm font-semibold dark:border-slate-500">
                            <i data-lucide="download" class="h-4 w-4"></i>
                            Export CSV
                        </button>
                        
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                        <select id="sortBy" class="input rounded-lg border px-2.5 py-2">
                            <option value="name">Sort: Name</option>
                            <option value="role">Sort: Role</option>
                            <option value="status">Sort: Status</option>
                            <option value="id">Sort: User ID</option>
                        </select>
                        <span id="resultCount"></span>
                        <span id="selectionCount" class="font-semibold text-green-700"></span>
                        <button type="button" data-bulk-toggle class="ml-auto hidden rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white dark:bg-slate-100 dark:text-slate-900">
                            Apply bulk action
                        </button>
                    </div>
                </section>
                <section class="card mt-5 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="w-12 p-4">
                                        <input id="selectAll" type="checkbox" aria-label="Select all visible users" />
                                    </th>
                                    <th class="p-4">User</th>
                                    <th class="p-4">User ID</th>
                                    <th class="p-4">Role</th>
                                    <th class="p-4">Email</th>
                                    <th class="p-4">Status</th>
                                    <th class="p-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rows"></tbody>
                        </table>
                    </div>
                    <div id="emptyState" class="hidden p-10 text-center">
                        <i data-lucide="users-round" class="mx-auto h-8 w-8 text-slate-300"></i>
                        <h3 class="mt-3 font-semibold">No users found</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Try a different search or create a new account.
                        </p>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
    <dialog id="userDialog" class="w-[min(680px,calc(100%-2rem))] rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <form id="userForm" class="card max-h-[90vh] overflow-y-auto p-6 dark:text-white">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p id="dialogEyebrow" class="text-sm font-semibold text-green-600">
                        New account
                    </p>
                    <h3 id="dialogTitle" class="mt-1 text-xl font-bold">Add user</h3>
                </div>
                <button type="button" data-close-dialog class="rounded-lg p-2 text-slate-400 hover:bg-slate-100">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <input id="userId" type="hidden" />
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold">
                    First name
                    <input id="firstName" required class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" />
                </label>
                <label class="text-sm font-semibold">
                    Last name
                    <input id="lastName" required class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" />
                </label>
                <label class="text-sm font-semibold">
                    Email
                    <input id="email" type="email" required class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" />
                </label>
                <label class="text-sm font-semibold">
                    Username
                    <input id="username" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" />
                </label>
                <label class="text-sm font-semibold">
                    Role
                    <select id="role" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="parent">Parent</option>
                        <option value="admin">Admin</option>
                    </select>
                </label>
                <label class="text-sm font-semibold">
                    Account status
                    <select id="accountStatus" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </label>
                <label class="text-sm font-semibold sm:col-span-2">
                    Password
                    <input id="password" type="password" minlength="6" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"
                        placeholder="Required for a new account; leave blank to keep current password" />
                </label>
                <label class="text-sm font-semibold">
                    Contact number
                    <input id="contact" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" />
                </label>
                <label class="text-sm font-semibold">
                    Program / strand
                    <input id="strand" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" />
                </label>
                <label id="childLinkField" class="hidden text-sm font-semibold sm:col-span-2">
                    Linked student
                    <select id="childId" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">
                        <option value="">No linked student</option>
                    </select>
                </label>
                <label class="text-sm font-semibold sm:col-span-2">
                    Address
                    <textarea id="address" rows="2" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"></textarea>
                </label>
            </div>
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <span id="formFeedback" class="text-xs text-slate-500"></span>
                <div class="flex gap-3">
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="student-hero mb-7 p-6 sm:p-8 mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-purple-600">
                            Academic Records
                        </p>
                        <h2 class="mt-1 text-3xl font-bold tracking-tight">
                            Grade Management
                        </h2>
                        <p class="mt-2 text-white">
                            Review, correct, and publish academic results across the portal.
                        </p>
                    </div>
                    <button type="button" data-export-grades
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold dark:border-slate-700">
                        <i data-lucide="download" class="h-4 w-4"></i>
                        Export CSV
                    </button>
                </section>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="card p-5">
                 
                    <button type="button" data-close-dialog
                        class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold dark:border-slate-700">
                        Cancel
                    </button>
                    <button type="submit"
                        class="rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700">
                        Save account
                    </button>
                </div>
            </div>
        </form>
    </dialog>
    <dialog id="detailDialog"
        class="w-[min(560px,calc(100%-2rem))] rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <section class="card p-6 dark:text-white">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-semibold text-green-600">Account details</p>
                    <h3 id="detailName" class="mt-1 text-xl font-bold"></h3>
                </div>
                <button type="button" data-close-detail class="rounded-lg p-2 text-slate-400 hover:bg-slate-100">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div id="detailBody" class="mt-6 grid gap-3 sm:grid-cols-2"></div>
            <div class="mt-6 flex justify-end">
                <button type="button" data-close-detail
                    class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white dark:bg-slate-100 dark:text-slate-900">
                    Close
                </button>
            </div>
        </section>
    </dialog>
    <template id="userRowTemplate">
        <tr class="border-t border-slate-100 dark:border-slate-800">
            <td class="p-4">
                <input type="checkbox" data-select-user aria-label="Select user" />
            </td>
            <td class="p-4">
                <div class="flex items-center gap-3">
                    <span class="relative flex h-9 w-9 shrink-0 items-center justify-center">
                        <img data-user-photo
                            class="absolute inset-0 h-9 w-9 rounded-full object-cover"
                            alt="User profile photo" />
                        <span data-user-initials
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-green-50 text-xs font-bold text-green-700"></span>
                    </span>
                    <div>
                        <b data-user-name class="block"></b><small data-user-username
                            class="text-xs text-slate-400"></small>
                    </div>
                </div>
            </td>
            <td data-user-id class="p-4 font-mono text-xs"></td>
            <td class="p-4"><span data-user-role></span></td>
            <td data-user-email class="p-4"></td>
            <td class="p-4"><span data-user-status></span></td>
            <td class="p-4">
                <div class="flex justify-end gap-2">
                    <button type="button" data-action="details" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="View details">
                        <i data-lucide="eye" class="h-4 w-4"></i>
                    </button>
                    <button type="button" data-action="edit" class="rounded-lg p-2 text-blue-600 hover:bg-blue-50" title="Edit user">
                        <i data-lucide="pencil" class="h-4 w-4"></i>
                    </button>
                    <button type="button" data-action="toggle" class="rounded-lg p-2 text-amber-600 hover:bg-amber-50" title="Change account status">
                        <i data-lucide="pause-circle" class="h-4 w-4"></i>
                    </button>
                    <button type="button" data-action="reset" class="rounded-lg p-2 text-purple-600 hover:bg-purple-50" title="Reset password">
                        <i data-lucide="key-round" class="h-4 w-4"></i>
                    </button>
                    <button type="button" data-action="delete" class="rounded-lg p-2 text-red-600 hover:bg-red-50" title="Delete user">
                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                    </button>
                </div>
            </td>
        </tr>
    </template>
    <script>
        lucide.createIcons();
    </script>

    
</body>
</html>