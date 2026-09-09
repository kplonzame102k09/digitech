<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'rolePassword')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('rolePassword');
            });
        }

        $this->createDocumentRequests();
        $this->createRequirements();
        $this->createCompetencies();
        $this->createNotifications();
        $this->createAnnouncements();
        $this->createAuditLogs();
        $this->createParentLinkRequests();
        $this->createSystemSettings();

        $this->migrateLegacyCollections();
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('parent_link_requests');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('competencies');
        Schema::dropIfExists('requirements');
        Schema::dropIfExists('document_requests');
    }

    private function createDocumentRequests(): void
    {
        if (Schema::hasTable('document_requests')) {
            return;
        }

        Schema::create('document_requests', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('studentId');
            $table->string('documentType');
            $table->string('purpose');
            $table->unsignedInteger('copies')->default(1);
            $table->text('notes')->nullable();
            $table->string('status')->default('Pending')->index();
            $table->timestamp('requestDate')->nullable();
            $table->text('reviewNotes')->nullable();
            $table->text('rejectionReason')->nullable();
            $table->string('releaseMethod')->nullable();
            $table->timestamp('releaseDate')->nullable();
            $table->timestamp('reviewedAt')->nullable();
            $table->string('reviewedBy')->nullable();
            $table->string('createdBy')->nullable();
            $table->timestamps();

            $table->foreign('studentId')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('reviewedBy')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('createdBy')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    private function createRequirements(): void
    {
        if (Schema::hasTable('requirements')) {
            return;
        }

        Schema::create('requirements', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('studentId');
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('status')->default('Pending')->index();
            $table->date('dueDate')->nullable();
            $table->timestamp('submittedAt')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('studentId')->references('user_id')->on('users')->cascadeOnDelete();
        });
    }

    private function createCompetencies(): void
    {
        if (Schema::hasTable('competencies')) {
            return;
        }

        Schema::create('competencies', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('studentId');
            $table->string('competency');
            $table->string('qualification')->nullable();
            $table->string('status')->default('Not Started')->index();
            $table->date('assessmentDate')->nullable();
            $table->string('assessor')->nullable();
            $table->text('evidence')->nullable();
            $table->text('remarks')->nullable();
            $table->string('createdBy')->nullable();
            $table->string('updatedBy')->nullable();
            $table->timestamps();

            $table->foreign('studentId')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('createdBy')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('updatedBy')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    private function createNotifications(): void
    {
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('userId');
            $table->string('title');
            $table->text('message')->nullable();
            $table->boolean('read')->default(false)->index();
            $table->string('source')->nullable();
            $table->string('recordId')->nullable();
            $table->timestamps();

            $table->foreign('userId')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index(['userId', 'read']);
        });
    }

    private function createAnnouncements(): void
    {
        if (Schema::hasTable('announcements')) {
            return;
        }

        Schema::create('announcements', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('category')->nullable();
            $table->string('audience')->default('all')->index();
            $table->string('authorId')->nullable();
            $table->timestamps();

            $table->foreign('authorId')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    private function createAuditLogs(): void
    {
        if (Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('entity')->index();
            $table->string('recordId')->nullable();
            $table->string('action');
            $table->text('from')->nullable();
            $table->text('to')->nullable();
            $table->text('notes')->nullable();
            $table->text('reason')->nullable();
            $table->string('actorId')->nullable();
            $table->timestamp('createdAt')->useCurrent();

            $table->foreign('actorId')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['entity', 'recordId']);
        });
    }

    private function createParentLinkRequests(): void
    {
        if (Schema::hasTable('parent_link_requests')) {
            return;
        }

        Schema::create('parent_link_requests', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('parentId');
            $table->string('studentId');
            $table->string('status')->default('Pending')->index();
            $table->timestamp('reviewedAt')->nullable();
            $table->string('reviewedBy')->nullable();
            $table->timestamps();

            $table->foreign('parentId')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('studentId')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('reviewedBy')->references('user_id')->on('users')->nullOnDelete();
            $table->unique(['parentId', 'studentId']);
        });
    }

    private function createSystemSettings(): void
    {
        if (Schema::hasTable('system_settings')) {
            return;
        }

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('theme')->default('light');
            $table->boolean('teacherRegistration')->default(true);
            $table->boolean('adminRegistration')->default(false);
            $table->string('institutionName')->default('Digitech College');
            $table->string('schoolYear')->default(date('Y').'-'.(date('Y') + 1));
            $table->decimal('passingGrade', 5, 2)->default(75);
            $table->date('enrollmentDeadline')->nullable();
            $table->boolean('notifyStudents')->default(true);
            $table->boolean('notifyParents')->default(true);
            $table->boolean('notifyTeachers')->default(true);
            $table->boolean('notifyAdmins')->default(true);
            $table->string('updatedBy')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('updatedBy')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Best-effort port of legacy portal_collections payloads into the new tables.
     */
    private function migrateLegacyCollections(): void
    {
        $rows = DB::table('portal_collections')->get(['key', 'value']);

        foreach ($rows as $row) {
            $value = json_decode($row->value, true);

            $this->copySettings($value);
            $this->copyAuditLogs($value);
        }
    }

    private function copySettings(mixed $value): void
    {
        if (DB::table('system_settings')->exists() || ! is_array($value)) {
            return;
        }

        $allowed = [
            'theme', 'teacherRegistration', 'adminRegistration', 'institutionName',
            'schoolYear', 'passingGrade', 'enrollmentDeadline', 'notifyStudents',
            'notifyParents', 'notifyTeachers', 'notifyAdmins',
        ];

        $data = collect($value)->only($allowed)->all();

        if ($data !== []) {
            DB::table('system_settings')->insert($data);
        }
    }

    private function copyAuditLogs(mixed $value): void
    {
        if (DB::table('audit_logs')->exists() || ! is_array($value)) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($value as $entry) {
            if (! is_array($entry) || empty($entry['id'])) {
                continue;
            }

            $rows[] = [
                'id' => $entry['id'],
                'entity' => $entry['entity'] ?? 'system',
                'recordId' => $entry['recordId'] ?? null,
                'action' => $entry['action'] ?? 'update',
                'from' => $entry['from'] ?? null,
                'to' => $entry['to'] ?? null,
                'notes' => $entry['notes'] ?? null,
                'reason' => $entry['reason'] ?? null,
                'actorId' => $entry['actorId'] ?? null,
                'createdAt' => Carbon::parse($entry['date'] ?? $now)->format('Y-m-d H:i:s'),
            ];
        }

        if ($rows !== []) {
            DB::table('audit_logs')->insert($rows);
        }
    }
};
