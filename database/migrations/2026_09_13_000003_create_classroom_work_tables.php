<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_activities', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('classroom_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('dueDate')->nullable();
            $table->string('createdBy')->nullable();
            $table->timestamps();

            $table->foreign('classroom_id')->references('id')->on('classrooms')->cascadeOnDelete();
            $table->foreign('createdBy')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['classroom_id', 'dueDate']);
        });

        Schema::create('classroom_submissions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('activity_id');
            $table->string('studentId');
            $table->string('fileUrl')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('Submitted')->index();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamp('submittedAt')->nullable();
            $table->timestamp('gradedAt')->nullable();
            $table->string('gradedBy')->nullable();
            $table->timestamps();

            $table->foreign('activity_id')->references('id')->on('classroom_activities')->cascadeOnDelete();
            $table->foreign('studentId')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('gradedBy')->references('user_id')->on('users')->nullOnDelete();
            $table->unique(['activity_id', 'studentId']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_submissions');
        Schema::dropIfExists('classroom_activities');
    }
};
