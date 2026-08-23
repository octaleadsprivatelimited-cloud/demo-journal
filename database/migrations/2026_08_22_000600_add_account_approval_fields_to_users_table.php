<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('requested_role', 24)->nullable()->after('status')->index();
            $table->foreignId('approved_by_id')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_id');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['approved_by_id']);
            $table->dropIndex(['requested_role']);
            $table->dropColumn(['requested_role', 'approved_by_id', 'approved_at', 'rejected_at']);
        });
    }
};
