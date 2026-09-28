<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalise legacy `PAR-` parent user ids to the `PRT-` prefix.
 *
 * LoginAttemptGuard::roleForUserId() maps `PRT` to the parent role, and both
 * id generators (PortalDataService::generateUserId and
 * AccountRequestController::generateRequestId) already emit `PRT`. Accounts
 * stored as `PAR-` therefore resolve to the student role, the login lookup
 * filters on `where('role', 'student')` and never matches, so those accounts
 * are locked out no matter what password they hold.
 *
 * Only the prefix is rewritten; the year and random suffix are preserved, so a
 * renamed id cannot collide with an existing `PRT-` account unless one already
 * occupies exactly that id, in which case the row is skipped rather than
 * clobbered.
 *
 * Idempotent: a no-op once no `PAR-` ids remain.
 */
return new class extends Migration
{
    public function up(): void
    {
        $renames = $this->collectRenames();

        if ($renames === []) {
            return;
        }

        // 32 foreign keys reference users.user_id, including
        // notifications.userId. The new id cannot exist before the old one is
        // replaced, and the old one cannot go while the notification still
        // points at it, so no ordering satisfies the constraints. Checks are
        // disabled for this connection only, and always restored.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($renames as $old => $new) {
                DB::table('users')->where('user_id', $old)->update(['user_id' => $new]);
            }

            // A notification links to a user_id as a plain string, so it must
            // follow the rename or the inbox entry becomes unresolvable.
            if (Schema::hasTable('notifications')) {
                foreach ($renames as $old => $new) {
                    DB::table('notifications')->where('userId', $old)->update(['userId' => $new]);
                    DB::table('notifications')->where('recordId', $old)->update(['recordId' => $new]);
                }
            }

            // A child lists its guardian in childId / childIds, so those
            // references follow the rename too.
            $this->rewriteChildReferences($renames);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // audit_logs.recordId is deliberately NOT rewritten. It is an
        // append-only history of what was true at the time, and rewriting it
        // would falsify past entries. The column carries no foreign key (only
        // audit_logs.actorId does), so leaving the old id there is safe and
        // old rows keep showing what was recorded at the time.
    }

    /**
     * Intentionally a no-op.
     *
     * Reversing the rename cannot be inferred safely: any PRT- account could be
     * rewritten back to PAR-, including the PRT-2026-HE6855 account that was
     * never PAR- to begin with, which would silently corrupt an unrelated user.
     * A one-way data normalisation is preferable to a destructive rollback, so
     * this only documents that the change is not reversible.
     */
    public function down(): void
    {
        //
    }

    /**
     * Map every legacy id to its rewritten form, skipping any whose target is
     * already taken so an existing account is never overwritten.
     *
     * @return array<string, string> old id => new id
     */
    private function collectRenames(): array
    {
        $users = DB::table('users')
            ->select('user_id')
            ->where('user_id', 'like', 'PAR-%')
            ->pluck('user_id');

        $taken = DB::table('users')->pluck('user_id')->flip();

        $renames = [];

        foreach ($users as $userId) {
            $new = 'PRT'.substr((string) $userId, 3);

            if ($new === $userId || $taken->has($new)) {
                continue;
            }

            $renames[$userId] = $new;
        }

        return $renames;
    }

    /**
     * Point childId / childIds at the renamed ids, preserving array order and
     * leaving every unrelated entry untouched.
     *
     * Rows are matched on either column: a guardian is usually recorded in
     * childIds with childId left null, so matching childId alone would miss
     * them.
     *
     * @param  array<string, string>  $renames
     */
    private function rewriteChildReferences(array $renames): void
    {
        $rows = DB::table('users')
            ->select('id', 'childId', 'childIds')
            ->where(function ($query) use ($renames): void {
                foreach (array_keys($renames) as $old) {
                    $query->orWhere('childId', $old)
                        ->orWhere('childIds', 'like', '%"'.$old.'"%');
                }
            })
            ->get();

        foreach ($rows as $row) {
            $changes = [];

            if (isset($row->childId) && isset($renames[$row->childId])) {
                $changes['childId'] = $renames[$row->childId];
            }

            $decoded = is_string($row->childIds) ? json_decode($row->childIds, true) : $row->childIds;

            if (is_array($decoded)) {
                $mapped = array_map(
                    fn (mixed $id): mixed => is_string($id) ? ($renames[$id] ?? $id) : $id,
                    $decoded,
                );

                if ($mapped !== $decoded) {
                    $changes['childIds'] = json_encode($mapped);
                }
            }

            if ($changes !== []) {
                DB::table('users')->where('id', $row->id)->update($changes);
            }
        }
    }
};
