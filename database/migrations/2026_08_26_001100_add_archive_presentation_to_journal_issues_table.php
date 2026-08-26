<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('journal_issues', function (Blueprint $table): void {
            $table->text('description')->nullable();
            $table->string('cover_image_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('journal_issues', function (Blueprint $table): void {
            $table->dropColumn(['description', 'cover_image_path']);
        });
    }
};
