<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_messages', function (Blueprint $table): void {
            // Surrogate ordering key, deliberately the primary key.
            //
            // created_at cannot order this table: Laravel's MySQL grammar binds
            // DateTime values as 'Y-m-d H:i:s', so every row truncates to the
            // second and two messages sent in the same second are
            // indistinguishable. That silently breaks the unread badge
            // (created_at > lastReadAt is false for an equal second) and makes a
            // timestamp cursor skip or repeat messages. An auto-increment
            // column gives a strict total order regardless of clock resolution,
            // timezone or NTP steps, and is required to be the primary key here
            // because MySQL will not accept an auto-increment column that is
            // not a key.
            $table->bigIncrements('seq');

            // Public, URL-ciphered identifier in the PREFIX-YEAR-RANDOM form
            // the rest of the schema uses. Random, so it carries no ordering.
            $table->string('id', 191)->unique();
            $table->string('classroom_id', 191);
            $table->string('userId', 191);
            $table->text('body')->nullable();
            $table->text('fileUrl')->nullable();
            $table->string('fileName')->nullable();
            $table->timestamps();

            $table->foreign('classroom_id')->references('id')->on('classrooms')->cascadeOnDelete();
            $table->foreign('userId')->references('user_id')->on('users')->cascadeOnDelete();

            // The hot access path is always "newest rows in one classroom".
            $table->index(['classroom_id', 'seq']);
        });

        Schema::create('classroom_message_reads', function (Blueprint $table): void {
            $table->string('id', 191)->primary();
            $table->string('classroom_id', 191);
            $table->string('userId', 191);
            $table->timestamp('lastReadAt')->nullable();
            // Highest seq the person has actually read, rather than a wall
            // clock. Same reason as classroom_messages.seq: a timestamp pointer
            // cannot reliably tell a newly arrived message from one already
            // seen when both land in the same second.
            $table->unsignedBigInteger('lastReadSeq')->default(0);
            $table->timestamps();

            $table->foreign('classroom_id')->references('id')->on('classrooms')->cascadeOnDelete();
            $table->foreign('userId')->references('user_id')->on('users')->cascadeOnDelete();

            // One read pointer per person per classroom.
            $table->unique(['classroom_id', 'userId']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_message_reads');
        Schema::dropIfExists('classroom_messages');
    }
};
