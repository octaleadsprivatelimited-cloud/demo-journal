<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone', 32)->nullable();
            $table->string('subject');
            $table->longText('message');
            $table->string('category', 64)->nullable()->index();
            $table->string('status', 32)->default('new')->index();
            $table->string('ip_hash', 64)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'created_at']);
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('status', 32)->default('active')->index();
            $table->uuid('token')->unique();
            $table->string('source', 64)->nullable();
            $table->timestamp('subscribed_at')->useCurrent();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('auditable');
            $table->string('event', 64)->index();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id', 64)->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'audit_auditable_created_index');
        });

        Schema::create('seo_metadata', function (Blueprint $table): void {
            $table->id();
            $table->morphs('seoable');
            $table->string('seo_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->json('focus_keywords')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('twitter_card', 64)->default('summary_large_image');
            $table->string('schema_type', 64)->default('Article');
            $table->json('structured_data')->nullable();
            $table->timestamps();
            $table->unique(['seoable_type', 'seoable_id']);
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->string('group', 64)->default('general')->index();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('article_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->string('ip_hash', 64)->nullable()->index();
            $table->text('referrer')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('viewed_at')->useCurrent();
            $table->index(['article_id', 'viewed_at']);
            $table->index(['user_id', 'viewed_at']);
        });

        Schema::create('search_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('query', 500);
            $table->json('filters')->nullable();
            $table->unsignedInteger('results_count')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('session_id')->nullable()->index();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('searched_at')->useCurrent();
            $table->index(['searched_at', 'results_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');
        Schema::dropIfExists('article_views');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('seo_metadata');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('contact_submissions');
    }
};
