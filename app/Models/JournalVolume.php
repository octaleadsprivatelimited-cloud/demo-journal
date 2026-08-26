<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class JournalVolume extends Model {protected $fillable=['number','year','title','is_current']; protected function casts():array{return ['year'=>'integer','is_current'=>'boolean'];} public function issues():HasMany{return $this->hasMany(JournalIssue::class)->orderBy('publication_date');}}
