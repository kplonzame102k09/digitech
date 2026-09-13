<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_meetings', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('classroom_id');
            $table->string('title')->default('Live class');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('room_name');
            $table->string('status')->default('scheduled')->index();
            $table->string('createdBy')->nullable();
            $table->timestamps();

            $table->foreign('classroom_id')->references('id')->on('classrooms')->cascadeOnDelete();
            $table->foreign('createdBy')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['classroom_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_meetings');
    }
};
