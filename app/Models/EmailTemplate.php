<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EmailTemplate extends Model {protected $fillable=['key','audience','subject','body','available_variables','is_active'];protected function casts():array{return ['available_variables'=>'array','is_active'=>'boolean'];}}
