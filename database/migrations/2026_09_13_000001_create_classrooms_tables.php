<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('teacherId');
            $table->string('name');
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->string('inviteCode', 9)->unique();
            $table->string('inviteToken', 64)->unique();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->foreign('teacherId')->references('user_id')->on('users')->cascadeOnDelete();
        });

        Schema::create('classroom_student', function (Blueprint $table): void {
            $table->string('classroom_id');
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->foreign('classroom_id')->references('id')->on('classrooms')->cascadeOnDelete();
            $table->primary(['classroom_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_student');
        Schema::dropIfExists('classrooms');
    }
};
