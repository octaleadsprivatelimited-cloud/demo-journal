<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable()->index();
            $table->text('biography')->nullable();
            $table->string('designation')->nullable();
            $table->string('organization')->nullable()->index();
            $table->string('avatar_path')->nullable();
            $table->string('website_url')->nullable();
            $table->json('social_links')->nullable();
            $table->boolean('is_verified')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['parent_id', 'sort_order']);
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->text('excerpt')->nullable();
            $table->text('abstract')->nullable();
            $table->longText('content');
            $table->json('keywords')->nullable();
            $table->json('references')->nullable();
            $table->string('publication_type', 64)->default('article')->index();
            $table->string('doi')->nullable()->unique();
            $table->string('featured_image_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_trending')->default(false)->index();
            $table->boolean('comments_enabled')->default(true);
            $table->boolean('pdf_download_enabled')->default(true);
            $table->unsignedSmallInteger('reading_time_minutes')->default(1);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['category_id', 'status', 'published_at']);
            $table->index(['status', 'scheduled_for']);
            $table->index(['is_featured', 'published_at']);
            $table->index(['is_trending', 'published_at']);
            $table->index(['created_by_id', 'status']);
            $table->index(['assigned_editor_id', 'status']);
        });

        Schema::create('article_authors', function (Blueprint $table): void {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_corresponding')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->primary(['article_id', 'author_id']);
            $table->index(['author_id', 'sort_order']);
        });

        Schema::create('article_tags', function (Blueprint $table): void {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['article_id', 'tag_id']);
            $table->index(['tag_id', 'article_id']);
        });

        Schema::create('article_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('abstract')->nullable();
            $table->longText('content');
            $table->json('keywords')->nullable();
            $table->json('references')->nullable();
            $table->text('change_summary')->nullable();
            $table->timestamps();
            $table->unique(['article_id', 'version_number']);
        });

        Schema::create('submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('round')->default(1);
            $table->string('status', 32)->default('pending')->index();
            $table->text('cover_letter')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('decision_at')->nullable();
            $table->timestamps();
            $table->unique(['article_id', 'round']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('assigned')->index();
            $table->string('recommendation', 32)->nullable();
            $table->text('comments_to_author')->nullable();
            $table->text('confidential_comments')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['reviewer_id', 'status', 'due_at']);
            $table->index(['article_id', 'status']);
        });

        Schema::create('review_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('location')->nullable();
            $table->boolean('is_confidential')->default(false);
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->nullableMorphs('mediable');
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->string('extension', 16)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64)->index();
            $table->string('collection', 64)->default('default')->index();
            $table->string('visibility', 16)->default('private');
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['disk', 'path']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE INDEX articles_full_text_search_index ON articles USING GIN (
                    to_tsvector('simple',
                        coalesce(title, '') || ' ' ||
                        coalesce(subtitle, '') || ' ' ||
                        coalesce(abstract, '') || ' ' ||
                        coalesce(content, '')
                    )
                )
            SQL);
        }

        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->text('body');
            $table->string('status', 32)->default('pending')->index();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['article_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS articles_full_text_search_index');
        }

        Schema::dropIfExists('comments');
        Schema::dropIfExists('media');
        Schema::dropIfExists('review_comments');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('article_versions');
        Schema::dropIfExists('article_tags');
        Schema::dropIfExists('article_authors');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('authors');
    }
};
