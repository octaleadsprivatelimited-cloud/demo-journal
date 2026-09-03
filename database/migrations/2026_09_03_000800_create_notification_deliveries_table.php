<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email');
            $table->string('event_type', 64);
            $table->string('idempotency_key', 128)->unique();
            $table->string('template', 64);
            $table->string('subject');
            $table->string('provider', 64)->default('microsoft-email');
            $table->string('provider_message_id')->nullable();
            $table->string('status', 32)->default('queued')->index();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->text('payload')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['article_id', 'event_type']);
            $table->index(['recipient_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('notification_deliveries'); }
};
