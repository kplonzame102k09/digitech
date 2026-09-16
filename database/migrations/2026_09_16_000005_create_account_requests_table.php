<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->unique();
            $table->enum('role', ['student', 'parent', 'guest']);
            $table->enum('status', ['pending', 'approved', 'denied'])->default('pending');
            $table->string('firstName', 100);
            $table->string('lastName', 100);
            $table->string('middleName', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('username')->nullable();
            $table->string('contact', 30)->nullable();
            $table->string('strand')->nullable();
            $table->text('purpose');
            $table->text('adminNotes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_requests');
    }
};
