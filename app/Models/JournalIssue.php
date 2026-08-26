<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class JournalIssue extends Model {protected $fillable=['journal_volume_id','number','title','description','cover_image_path','publication_date','is_current'];protected function casts():array{return ['publication_date'=>'date','is_current'=>'boolean'];}public function volume():BelongsTo{return $this->belongsTo(JournalVolume::class,'journal_volume_id');}public function articles():HasMany{return $this->hasMany(Article::class)->published()->latest('published_at');}}
