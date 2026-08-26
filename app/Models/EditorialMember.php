<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EditorialMember extends Model {protected $fillable=['name','group','role','institution','department','country','credentials','biography','orcid','photo_path','sort_order','is_active'];protected function casts():array{return ['is_active'=>'boolean','sort_order'=>'integer'];}public function getDesignationAttribute():string{return $this->role;}public function getOrganizationAttribute():?string{return $this->institution;}public function getAvatarPathAttribute():?string{return $this->photo_path;}public function getIsVerifiedAttribute():bool{return true;}public function getSlugAttribute():string{return str($this->name)->slug()->toString();}}
