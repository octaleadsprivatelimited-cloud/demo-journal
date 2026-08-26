<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('journal_volumes', function(Blueprint $t){$t->id();$t->string('number',40);$t->unsignedSmallInteger('year')->index();$t->string('title')->nullable();$t->boolean('is_current')->default(false)->index();$t->timestamps();$t->unique(['number','year']);});
  Schema::create('journal_issues', function(Blueprint $t){$t->id();$t->foreignId('journal_volume_id')->constrained()->cascadeOnDelete();$t->string('number',40);$t->string('title')->nullable();$t->date('publication_date')->nullable()->index();$t->boolean('is_current')->default(false)->index();$t->timestamps();$t->unique(['journal_volume_id','number']);});
  Schema::table('articles', function(Blueprint $t){$t->foreignId('journal_issue_id')->nullable()->after('category_id')->constrained()->nullOnDelete();});
  Schema::create('editorial_members', function(Blueprint $t){$t->id();$t->string('name');$t->string('group',40)->index();$t->string('role');$t->string('institution')->nullable();$t->string('department')->nullable();$t->string('country')->nullable();$t->string('credentials')->nullable();$t->text('biography')->nullable();$t->string('orcid')->nullable();$t->string('photo_path')->nullable();$t->unsignedInteger('sort_order')->default(0);$t->boolean('is_active')->default(true)->index();$t->timestamps();});
  Schema::create('indexing_services', function(Blueprint $t){$t->id();$t->string('name');$t->text('description')->nullable();$t->string('official_url')->nullable();$t->string('logo_path')->nullable();$t->string('status',40)->default('pending_verification')->index();$t->unsignedInteger('sort_order')->default(0);$t->boolean('is_active')->default(false)->index();$t->timestamps();});
  Schema::create('email_templates', function(Blueprint $t){$t->id();$t->string('key')->unique();$t->string('audience',40)->index();$t->string('subject');$t->text('body');$t->json('available_variables')->nullable();$t->boolean('is_active')->default(true);$t->timestamps();});
  Schema::table('reviews', function(Blueprint $t){$t->boolean('conflict_declared')->default(false);$t->text('response_note')->nullable();$t->timestamp('responded_at')->nullable();$t->string('review_file_path')->nullable();});
 }
 public function down(): void {Schema::table('reviews',fn(Blueprint $t)=>$t->dropColumn(['conflict_declared','response_note','responded_at','review_file_path']));Schema::table('articles',fn(Blueprint $t)=>$t->dropConstrainedForeignId('journal_issue_id'));Schema::dropIfExists('email_templates');Schema::dropIfExists('indexing_services');Schema::dropIfExists('editorial_members');Schema::dropIfExists('journal_issues');Schema::dropIfExists('journal_volumes');}
};
