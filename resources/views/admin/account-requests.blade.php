<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Account Requests | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('admin.components.sidebar')
    <div class="lg:pl-64">
        @include('admin.components.header', ['title' => 'Account Requests', 'subtitle' => 'Administration'])
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="mb-2 p-6 sm:p-8">
                    <p class="text-sm font-semibold text-purple-600 dark:text-purple-400">
                        Administration
                    </p>
                    <h2 class="mt-1 text-3xl font-bold tracking-tight">
                        Account Request Management
                    </h2>
                    <p class="mt-2 text-slate-500">
                        Review and approve incoming account requests from students, parents, and guests.
                    </p>
                </section>
                <section class="card p-4">
                    <div>
                        <label class="sr-only" for="q">
                            Search requests
                        </label>
                        <input id="q" class="input w-full rounded border px-3 py-3" placeholder="Search by name, email, or request ID" />
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-[auto_auto_auto] xl:items-center">
                            <select id="statusFilter" class="input rounded border px-3 py-3">
                                <option value="">All statuses</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="denied">Denied</option>
                            </select>
                            <select id="roleFilter" class="input rounded border px-3 py-3">
                                <option value="">All roles</option>
                                <option value="student">Students</option>
                                <option value="parent">Parents</option>
                                <option value="guest">Guests</option>
                            </select>
                        </div>
                    </div>
                </section>
                <section class="card mt-5 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="p-4">Request ID</th>
                                    <th class="p-4">Name</th>
                                    <th class="p-4">Role</th>
                                    <th class="p-4">Email</th>
                                    <th class="p-4">Status</th>
                                    <th class="p-4">Submitted</th>
                                    <th class="p-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rows"></tbody>
                        </table>
                    </div>
                    <div id="emptyState" class="hidden p-10 text-center">
                        <i data-lucide="inbox" class="mx-auto h-8 w-8 text-slate-300"></i>
                        <h3 class="mt-3 font-semibold">No account requests found</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Try a different search or filter.
                        </p>
                    </div>
                    <div id="tableFooter"
                        class="hidden items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 dark:border-slate-800">
                        <p id="resultCount" class="text-sm text-slate-500 dark:text-slate-400"></p>
                        <button type="button" id="loadMoreBtn"
                            class="rounded border border-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                            Load more
                        </button>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
    
    <!-- View Details Dialog -->
    <dialog id="detailDialog" class="w-[min(560px,calc(100%-2rem))] rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <section class="card p-6 dark:text-white">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-semibold text-green-600 dark:text-green-400">Request details</p>
                    <h3 id="detailRequestId" class="mt-1 text-xl font-bold"></h3>
                </div>
                <button type="button" data-close-detail class="rounded p-2 text-slate-400 hover:bg-slate-100">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div id="detailBody" class="mt-6 grid gap-3 sm:grid-cols-2"></div>
            <div class="mt-6 flex justify-end">
                <button type="button" data-close-detail
                    class="rounded bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white dark:bg-slate-100 dark:text-slate-900">
                    Close
                </button>
            </div>
        </section>
    </dialog>

    <!-- Approve Dialog -->
    <dialog id="approveDialog" class="w-[min(560px,calc(100%-2rem))] rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <form id="approveForm" class="card p-6 dark:text-white">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-semibold text-green-600 dark:text-green-400">Approve request</p>
                    <h3 id="approveRequestId" class="mt-1 text-xl font-bold"></h3>
                </div>
                <button type="button" data-close-approve class="rounded p-2 text-slate-400 hover:bg-slate-100">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <input id="approveRequestIdInput" type="hidden" />
            <div class="mt-6 grid gap-3">
                <label class="block text-sm font-medium">
                    Username *
                    <input id="approveUsername" required class="input mt-1 w-full rounded border px-3 py-2.5 dark:bg-slate-900 dark:border-slate-700" placeholder="Choose a username" />
                </label>
                <label class="block text-sm font-medium">
                    Password *
                    <input id="approvePassword" type="password" required minlength="8" class="input mt-1 w-full rounded border px-3 py-2.5 dark:bg-slate-900 dark:border-slate-700" placeholder="Set initial password" />
                </label>
                <label class="block text-sm font-medium">
                    Admin notes (optional)
                    <textarea id="approveNotes" rows="3" maxlength="1000" class="input mt-1 w-full rounded border px-3 py-2.5 resize-y dark:bg-slate-900 dark:border-slate-700" placeholder="Any notes for the approval..."></textarea>
                </label>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" data-close-approve
                    class="rounded border border-slate-200 px-4 py-2.5 text-sm font-semibold dark:border-slate-700">
                    Cancel
                </button>
                <button type="submit"
                    class="rounded bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700">
                    Approve & Create Account
                </button>
            </div>
        </form>
    </dialog>

    <!-- Reject Dialog -->
    <dialog id="rejectDialog" class="w-[min(560px,calc(100%-2rem))] rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <form id="rejectForm" class="card p-6 dark:text-white">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-semibold text-red-600 dark:text-red-400">Reject request</p>
                    <h3 id="rejectRequestId" class="mt-1 text-xl font-bold"></h3>
                </div>
                <button type="button" data-close-reject class="rounded p-2 text-slate-400 hover:bg-slate-100">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <input id="rejectRequestIdInput" type="hidden" />
            <div class="mt-6">
                <label class="block text-sm font-medium">
                    Rejection reason * (minimum 10 characters)
                    <textarea id="rejectReason" required minlength="10" maxlength="1000" rows="4" class="input mt-1 w-full rounded border px-3 py-2.5 resize-y dark:bg-slate-900 dark:border-slate-700" placeholder="Explain why this request is being rejected..."></textarea>
                </label>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" data-close-reject
                    class="rounded border border-slate-200 px-4 py-2.5 text-sm font-semibold dark:border-slate-700">
                    Cancel
                </button>
                <button type="submit"
                    class="rounded bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700">
                    Reject Request
                </button>
            </div>
        </form>
    </dialog>

    <template id="requestRowTemplate">
        <tr class="border-t border-slate-100 dark:border-slate-800">
            <td class="p-4 font-mono text-xs"></td>
            <td class="p-4">
                <div>
                    <b data-row-name class="block"></b>
                    <small data-row-email class="text-xs text-slate-400"></small>
                </div>
            </td>
            <td class="p-4"><span data-row-role></span></td>
            <td class="p-4"><span data-row-email-column></span></td>
            <td class="p-4"><span data-row-status></span></td>
            <td class="p-4"><span data-row-date></span></td>
            <td class="p-4">
                <div class="flex justify-end gap-2">
                    <button type="button" data-action="details" class="rounded p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400" title="View details">
                        <i data-lucide="eye" class="h-4 w-4"></i>
                    </button>
                    <button type="button" data-action="approve" class="rounded p-2 text-green-600 hover:bg-green-50 dark:text-green-400" title="Approve request">
                        <i data-lucide="check-circle" class="h-4 w-4"></i>
                    </button>
                    <button type="button" data-action="reject" class="rounded p-2 text-red-600 hover:bg-red-50 dark:text-red-400" title="Reject request">
                        <i data-lucide="x-circle" class="h-4 w-4"></i>
                    </button>
                </div>
            </td>
        </tr>
    </template>

    <script>
        (function () {
            const API_BASE = '/admin/api/account-requests';
            const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
            let requests = [];
            let filteredRequests = [];
            let currentPage = 1;
            let hasMorePages = false;
            let totalCount = 0;

            // Fetch requests from API (reset = fetch page 1, otherwise load the next page)
            async function fetchRequests(reset = true) {
                const params = new URLSearchParams();
                const search = document.getElementById('q').value.trim();
                const status = document.getElementById('statusFilter').value;
                const role = document.getElementById('roleFilter').value;

                if (search) params.append('search', search);
                if (status) params.append('status', status);
                if (role) params.append('role', role);
                params.append('page', reset ? 1 : currentPage + 1);

                try {
                    const response = await fetch(`${API_BASE}?${params.toString()}`);
                    const data = await response.json();
                    if (data.ok) {
                        const pageItems = data.requests || [];
                        requests = reset ? pageItems : requests.concat(pageItems);
                        filteredRequests = requests;
                        const pagination = data.pagination || {};
                        currentPage = pagination.currentPage || currentPage || 1;
                        hasMorePages = !!pagination.hasMorePages;
                        totalCount = typeof pagination.total === 'number' ? pagination.total : requests.length;
                        renderRequests();
                    }
                } catch (error) {
                    console.error('Failed to fetch requests:', error);
                }
            }

            // Render requests table
            function renderRequests() {
                const tbody = document.getElementById('rows');
                const emptyState = document.getElementById('emptyState');
                const template = document.getElementById('requestRowTemplate');
                const footer = document.getElementById('tableFooter');
                const resultCount = document.getElementById('resultCount');
                const loadMoreBtn = document.getElementById('loadMoreBtn');

                tbody.innerHTML = '';

                if (filteredRequests.length === 0) {
                    emptyState.classList.remove('hidden');
                    footer.classList.add('hidden');
                    return;
                }

                emptyState.classList.add('hidden');

                filteredRequests.forEach(request => {
                    const clone = template.content.cloneNode(true);
                    const row = clone.querySelector('tr');

                    row.dataset.id = request.id;
                    row.dataset.requestId = request.request_id;
                    row.dataset.status = request.status;

                    clone.querySelector('td:first-child').textContent = request.request_id;
                    clone.querySelector('[data-row-name]').textContent = `${request.firstName} ${request.lastName}`;
                    clone.querySelector('[data-row-email]').textContent = request.email;
                    clone.querySelector('[data-row-role]').textContent = formatRole(request.role);
                    clone.querySelector('[data-row-email-column]').textContent = request.email;
                    clone.querySelector('[data-row-status]').textContent = formatStatus(request.status);
                    clone.querySelector('[data-row-status]').className = getStatusClass(request.status);
                    clone.querySelector('[data-row-date]').textContent = formatDate(request.createdAt);

                    // Disable approve/reject for non-pending requests
                    if (request.status !== 'pending') {
                        clone.querySelector('[data-action="approve"]').disabled = true;
                        clone.querySelector('[data-action="approve"]').classList.add('opacity-50', 'cursor-not-allowed');
                        clone.querySelector('[data-action="reject"]').disabled = true;
                        clone.querySelector('[data-action="reject"]').classList.add('opacity-50', 'cursor-not-allowed');
                    }

                    tbody.appendChild(clone);
                });

                footer.classList.remove('hidden');
                resultCount.textContent = `${filteredRequests.length} of ${totalCount} request${totalCount === 1 ? '' : 's'}`;
                loadMoreBtn.classList.toggle('hidden', !hasMorePages);

                if (window.lucide) lucide.createIcons();
            }

            // Format role for display
            function formatRole(role) {
                const roles = {
                    student: 'Student',
                    parent: 'Parent / Guardian',
                    guest: 'Guest'
                };
                return roles[role] || role;
            }

            // Format status for display
            function formatStatus(status) {
                const statuses = {
                    pending: 'Pending',
                    approved: 'Approved',
                    denied: 'Denied'
                };
                return statuses[status] || status;
            }

            // Get status badge class
            function getStatusClass(status) {
                const classes = {
                    pending: 'inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
                    approved: 'inline-flex items-center rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-950/40 dark:text-green-300',
                    denied: 'inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300'
                };
                return classes[status] || '';
            }

            // Format date
            function formatDate(dateString) {
                if (!dateString) return '-';
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric' 
                });
            }

            // Minimal HTML entity escaping to prevent XSS.
            function esc(str) {
                if (str == null) return '';
                const s = String(str);
                return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            // Show details dialog
            function showDetails(requestId) {
                const request = requests.find(r => r.request_id === requestId);
                if (!request) return;

                document.getElementById('detailRequestId').textContent = request.request_id;
                const body = document.getElementById('detailBody');
                body.innerHTML = `
                    <div>
                        <span class="text-xs text-slate-500 dark:text-slate-400">Full Name</span>
                        <p class="font-medium">${esc(request.firstName)} ${esc(request.middleName) ? esc(request.middleName) + ' ' : ''}${esc(request.lastName)}</p>
                    </div>
                    <div>
                        <span class="text-xs text-slate-500 dark:text-slate-400">Email</span>
                        <p class="font-medium">${esc(request.email)}</p>
                    </div>
                    <div>
                        <span class="text-xs text-slate-500 dark:text-slate-400">Role</span>
                        <p class="font-medium">${esc(formatRole(request.role))}</p>
                    </div>
                    <div>
                        <span class="text-xs text-slate-500 dark:text-slate-400">Contact</span>
                        <p class="font-medium">${esc(request.contact) || '-'}</p>
                    </div>
                    ${request.strand ? `
                    <div>
                        <span class="text-xs text-slate-500 dark:text-slate-400">Program / Strand</span>
                        <p class="font-medium">${esc(request.strand)}</p>
                    </div>
                    ` : ''}
                    <div>
                        <span class="text-xs text-slate-500 dark:text-slate-400">Status</span>
                        <p class="font-medium">${esc(formatStatus(request.status))}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <span class="text-xs text-slate-500 dark:text-slate-400">Reason for Request</span>
                        <p class="font-medium mt-1">${esc(request.purpose)}</p>
                    </div>
                    ${request.adminNotes ? `
                    <div class="sm:col-span-2">
                        <span class="text-xs text-slate-500 dark:text-slate-400">Admin Notes</span>
                        <p class="font-medium mt-1">${esc(request.adminNotes)}</p>
                    </div>
                    ` : ''}
                    <div>
                        <span class="text-xs text-slate-500 dark:text-slate-400">Submitted</span>
                        <p class="font-medium">${formatDate(request.createdAt)}</p>
                    </div>
                    <div>
                        <span class="text-xs text-slate-500 dark:text-slate-400">Last Updated</span>
                        <p class="font-medium">${formatDate(request.updatedAt)}</p>
                    </div>
                `;

                document.getElementById('detailDialog').showModal();
            }

            // Show approve dialog
            function showApprove(requestId) {
                const request = requests.find(r => r.request_id === requestId);
                if (!request) return;

                document.getElementById('approveRequestId').textContent = request.request_id;
                document.getElementById('approveRequestIdInput').value = request.id;
                document.getElementById('approveUsername').value = '';
                document.getElementById('approvePassword').value = '';
                document.getElementById('approveNotes').value = '';
                document.getElementById('approveDialog').showModal();
            }

            // Show reject dialog
            function showReject(requestId) {
                const request = requests.find(r => r.request_id === requestId);
                if (!request) return;

                document.getElementById('rejectRequestId').textContent = request.request_id;
                document.getElementById('rejectRequestIdInput').value = request.id;
                document.getElementById('rejectReason').value = '';
                document.getElementById('rejectDialog').showModal();
            }

            // Return a single user-facing error message from an API error body.
            function firstErrorMessage(data) {
                if (!data) return '';
                if (data.error) return data.error;
                if (data.message) return data.message;
                if (data.errors && typeof data.errors === 'object') {
                    const fields = Object.keys(data.errors);
                    for (let i = 0; i < fields.length; i++) {
                        const value = data.errors[fields[i]];
                        if (Array.isArray(value) && value.length) return value[0];
                        if (typeof value === 'string') return value;
                    }
                }
                return '';
            }

            // Handle approve form submission
            async function handleApprove(e) {
                e.preventDefault();
                const id = document.getElementById('approveRequestIdInput').value;
                const username = document.getElementById('approveUsername').value.trim();
                const password = document.getElementById('approvePassword').value;
                const adminNotes = document.getElementById('approveNotes').value.trim();

                try {
                    const response = await fetch(`${API_BASE}/${id}/approve`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': CSRF_TOKEN
                        },
                        body: JSON.stringify({ username, password, adminNotes })
                    });

                    const data = await response.json();
                    if (data.ok) {
                        document.getElementById('approveDialog').close();
                        alert('Account request approved and user account created successfully!');
                        fetchRequests();
                    } else {
                        alert(firstErrorMessage(data) || 'Failed to approve request');
                    }
                } catch (error) {
                    console.error('Failed to approve request:', error);
                    alert('An error occurred while approving the request');
                }
            }

            // Handle reject form submission
            async function handleReject(e) {
                e.preventDefault();
                const id = document.getElementById('rejectRequestIdInput').value;
                const adminNotes = document.getElementById('rejectReason').value.trim();

                try {
                    const response = await fetch(`${API_BASE}/${id}/reject`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': CSRF_TOKEN
                        },
                        body: JSON.stringify({ adminNotes })
                    });

                    const data = await response.json();
                    if (data.ok) {
                        document.getElementById('rejectDialog').close();
                        alert('Account request rejected successfully!');
                        fetchRequests();
                    } else {
                        alert(firstErrorMessage(data) || 'Failed to reject request');
                    }
                } catch (error) {
                    console.error('Failed to reject request:', error);
                    alert('An error occurred while rejecting the request');
                }
            }

            // Event listeners
            document.getElementById('q').addEventListener('input', () => fetchRequests(true));
            document.getElementById('statusFilter').addEventListener('change', () => fetchRequests(true));
            document.getElementById('roleFilter').addEventListener('change', () => fetchRequests(true));
            document.getElementById('loadMoreBtn').addEventListener('click', () => fetchRequests(false));

            document.getElementById('rows').addEventListener('click', (e) => {
                const button = e.target.closest('button[data-action]');
                if (!button) return;

                const row = button.closest('tr');
                const requestId = row.dataset.requestId;
                const action = button.dataset.action;

                switch (action) {
                    case 'details':
                        showDetails(requestId);
                        break;
                    case 'approve':
                        showApprove(requestId);
                        break;
                    case 'reject':
                        showReject(requestId);
                        break;
                }
            });

            document.getElementById('approveForm').addEventListener('submit', handleApprove);
            document.getElementById('rejectForm').addEventListener('submit', handleReject);

            // Dialog close handlers
            document.querySelectorAll('[data-close-detail]').forEach(btn => {
                btn.addEventListener('click', () => document.getElementById('detailDialog').close());
            });
            document.querySelectorAll('[data-close-approve]').forEach(btn => {
                btn.addEventListener('click', () => document.getElementById('approveDialog').close());
            });
            document.querySelectorAll('[data-close-reject]').forEach(btn => {
                btn.addEventListener('click', () => document.getElementById('rejectDialog').close());
            });

            // Initial fetch
            fetchRequests();

            if (window.lucide) lucide.createIcons();
        })();
    </script>
    @include('partials.portal-scripts', ['portalPage' => 'admin/account-requests.js'])
</body>
</html>
