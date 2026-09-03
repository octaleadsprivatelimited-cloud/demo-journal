<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->string('status', 64)->change();
            $table->string('article_number', 64)->nullable()->unique()->after('public_id');
            $table->unsignedInteger('revision_round')->default(0)->after('status');
            $table->timestamp('metadata_validated_at')->nullable()->after('rejected_at');
            $table->foreignId('metadata_validated_by_id')->nullable()->constrained('users')->nullOnDelete()->after('metadata_validated_at');
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->timestamp('invited_at')->nullable()->after('assigned_by_id');
            $table->timestamp('responded_at')->nullable()->after('invited_at');
            $table->timestamp('submitted_at')->nullable()->after('completed_at');
            $table->unsignedInteger('revision_round')->default(1)->after('submission_id');
            $table->string('attachment_path')->nullable()->after('confidential_comments');
        });

        Schema::table('submissions', function (Blueprint $table): void {
            $table->string('status', 64)->change();
        });

        Schema::create('editor_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('decided_by_id')->constrained('users')->restrictOnDelete();
            $table->string('decision_type', 32);
            $table->text('decision_letter')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('previous_status', 64);
            $table->string('resulting_status', 64);
            $table->unsignedInteger('revision_round')->default(1);
            $table->timestamp('revision_deadline')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->index(['article_id', 'decided_at']);
        });

        Schema::create('manuscript_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 32);
            $table->string('disk', 64)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('revision_round')->default(0);
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            $table->index(['article_id', 'kind', 'is_current']);
        });

        Schema::create('production_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage', 32);
            $table->string('disk', 64)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->unsignedInteger('version_number');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
            $table->unique(['article_id', 'stage', 'version_number']);
        });

        Schema::create('doi_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('doi')->nullable()->unique();
            $table->string('prefix')->nullable();
            $table->string('suffix')->nullable();
            $table->string('url')->nullable();
            $table->string('provider', 64)->nullable();
            $table->string('status', 32)->default('not_prepared')->index();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('response')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();
        });

        Schema::create('internal_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visibility', 32)->default('editorial_staff');
            $table->text('body');
            $table->timestamps();
            $table->index(['article_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_notes');
        Schema::dropIfExists('doi_records');
        Schema::dropIfExists('production_files');
        Schema::dropIfExists('manuscript_files');
        Schema::dropIfExists('editor_decisions');
        Schema::table('reviews', fn (Blueprint $table) => $table->dropColumn(['invited_at', 'responded_at', 'submitted_at', 'revision_round', 'attachment_path']));
        Schema::table('articles', fn (Blueprint $table) => $table->dropConstrainedForeignId('metadata_validated_by_id')->dropColumn(['article_number', 'revision_round', 'metadata_validated_at']));
    }
};
