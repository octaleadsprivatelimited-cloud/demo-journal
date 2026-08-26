<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up():void { Schema::table('authors',function(Blueprint $t):void{$t->string('affiliation')->nullable()->after('organization');$t->string('orcid',19)->nullable()->unique()->after('website_url');}); } public function down():void { Schema::table('authors',fn(Blueprint $t)=>$t->dropUnique(['orcid'])->dropColumn(['affiliation','orcid'])); } };
