<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('articles', function (Blueprint $table): void { $table->string('volume', 40)->nullable()->index(); $table->string('issue', 40)->nullable()->index(); $table->string('article_number', 80)->nullable()->index(); $table->date('received_date')->nullable(); $table->date('revised_date')->nullable(); $table->date('accepted_date')->nullable(); $table->string('license', 255)->nullable(); $table->text('copyright_statement')->nullable(); $table->string('publication_notice', 40)->nullable()->index(); }); }
    public function down(): void { Schema::table('articles', fn (Blueprint $table) => $table->dropColumn(['volume','issue','article_number','received_date','revised_date','accepted_date','license','copyright_statement','publication_notice'])); }
};
