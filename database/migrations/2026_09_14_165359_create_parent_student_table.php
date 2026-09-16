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
        Schema::dropIfExists('parent_student'); // Drop if exists with wrong structure
        
        Schema::create('parent_student', function (Blueprint $table) {
            $table->string('parent_id');
            $table->string('student_id');
            $table->timestamps();

            $table->foreign('parent_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('student_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->primary(['parent_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parent_student');
    }
};
