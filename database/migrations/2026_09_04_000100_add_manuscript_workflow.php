<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_sequences', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->unsignedBigInteger('value')->default(0);
        });
        Schema::create('manuscript_workflows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('article_id')->unique()->constrained();
            $t->string('manuscript_id')->nullable()->unique();
            $t->string('stage')->default('draft')->index();
            $t->json('data')->nullable();
            $t->timestamp('deadline')->nullable()->index();
            $t->unsignedInteger('revision')->default(0);
            $t->timestamps();
        });
        Schema::create('workflow_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('article_id')->constrained();
            $t->foreignId('uploaded_by_id')->constrained('users');
            $t->foreignId('article_version_id')->nullable()->constrained();
            $t->string('purpose', 60);
            $t->string('path');
            $t->string('original_name');
            $t->string('mime_type');
            $t->string('checksum', 64);
            $t->unsignedBigInteger('size');
            $t->unsignedInteger('round')->default(0);
            $t->timestamps();
        });
        Schema::create('workflow_activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('article_id')->constrained();
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('role');
            $t->string('action');
            $t->string('from_stage');
            $t->string('to_stage');
            $t->text('comments')->nullable();
            $t->json('data')->nullable();
            $t->boolean('author_visible')->default(true);
            $t->timestamps();
        });
        Schema::create('workflow_reminders', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->timestamp('created_at');
        });
        Schema::table('reviews', function (Blueprint $t) {
            $t->text('editor_message')->nullable();
            $t->timestamp('invitation_deadline')->nullable();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->json('reviewer_profile')->nullable();
        });
        Schema::table('authors', function (Blueprint $t) {
            $t->string('department')->nullable();
            $t->string('country')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('authors', fn (Blueprint $t) => $t->dropColumn(['department', 'country']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('reviewer_profile'));
        Schema::table('reviews', fn (Blueprint $t) => $t->dropColumn(['editor_message', 'invitation_deadline']));
        Schema::dropIfExists('workflow_reminders');
        Schema::dropIfExists('workflow_activities');
        Schema::dropIfExists('workflow_files');
        Schema::dropIfExists('manuscript_workflows');
        Schema::dropIfExists('workflow_sequences');
    }
};
