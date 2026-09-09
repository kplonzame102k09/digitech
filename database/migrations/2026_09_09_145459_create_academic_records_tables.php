<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parent_student', function (Blueprint $table): void {
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['parent_id', 'student_id']);
        });

        Schema::create('enrollments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('studentId');
            $table->string('status')->index();
            $table->string('programType')->nullable();
            $table->string('gradeLevel')->nullable();
            $table->string('strand')->nullable();
            $table->string('track')->nullable();
            $table->string('schoolYear')->index();
            $table->string('trainingLevel')->nullable();
            $table->string('assignedTeacherId')->nullable();
            $table->string('assignedSection')->nullable();
            $table->text('reviewNotes')->nullable();
            $table->text('rejectionReason')->nullable();
            $table->timestamp('reviewedAt')->nullable();
            $table->string('reviewedBy')->nullable();
            $table->timestamps();

            $table->foreign('studentId')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('assignedTeacherId')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('reviewedBy')->references('user_id')->on('users')->nullOnDelete();
            $table->unique(['studentId', 'schoolYear']);
        });

        Schema::create('grades', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('studentId');
            $table->string('subject');
            $table->string('teacherId')->nullable();
            $table->decimal('grade', 5, 2)->nullable();
            $table->string('remarks')->nullable();
            $table->string('term')->nullable();
            $table->string('period')->nullable();
            $table->boolean('published')->default(false)->index();
            $table->timestamp('publishedAt')->nullable();
            $table->string('publishedBy')->nullable();
            $table->text('notes')->nullable();
            $table->string('updatedBy')->nullable();
            $table->timestamps();

            $table->foreign('studentId')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('teacherId')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('publishedBy')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('updatedBy')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['studentId', 'published']);
        });

        Schema::create('attendance', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('studentId');
            $table->date('date');
            $table->string('status');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('studentId')->references('user_id')->on('users')->cascadeOnDelete();
            $table->unique(['studentId', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('parent_student');
    }
};
