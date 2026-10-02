<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
$counts=[];
foreach(['articles','authors','reviews','submissions','workflow_files','media'] as $table) $counts[$table]=DB::table($table)->count();
$missing=[];
foreach(DB::table('articles')->get(['id','pdf_path','featured_image_path']) as $article) {
 foreach(['pdf_path'=>'local','featured_image_path'=>'public'] as $field=>$disk) if($article->$field && !Storage::disk($disk)->exists($article->$field)) $missing[]='article:'.$article->id.':'.$field;
}
foreach(DB::table('workflow_files')->get(['id','path']) as $file) if($file->path && !Storage::disk('local')->exists($file->path)) $missing[]='workflow:'.$file->id;
echo json_encode(['counts'=>$counts,'missing_files'=>$missing,'environment'=>app()->environment(),'debug'=>config('app.debug'),'published'=>App\Models\Article::published()->count(),'bypasses'=>[config('security.local_admin_bypass.enabled'),config('security.local_author_bypass.enabled'),config('security.local_editor_bypass.enabled'),config('security.local_reviewer_bypass.enabled')]],JSON_PRETTY_PRINT).PHP_EOL;
