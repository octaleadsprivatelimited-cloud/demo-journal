<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{Author,EditorialMember,EmailTemplate,IndexingService,JournalIssue,JournalVolume};
use Illuminate\Http\{RedirectResponse,Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
class ReadinessController extends Controller {
 private const MODELS=['authors'=>Author::class,'volumes'=>JournalVolume::class,'issues'=>JournalIssue::class,'editorial-members'=>EditorialMember::class,'indexing-services'=>IndexingService::class,'email-templates'=>EmailTemplate::class];
 public function index(string $resource):View {Gate::authorize('manageSettings');$model=$this->model($resource);$records=$model::query()->when($resource==='authors',fn($q)=>$q->when(request('q'),fn($q,$term)=>$q->where('name','like','%'.addcslashes($term,'%_').'%'))->orderBy('name'))->latest()->paginate(30)->withQueryString();return view('admin.readiness.index',['resource'=>$resource,'records'=>$records,'volumes'=>JournalVolume::query()->orderByDesc('year')->get()]);}
 public function store(Request $request,string $resource):RedirectResponse {Gate::authorize('manageSettings');$model=$this->model($resource);$model::query()->create($this->withUploads($request,$resource,$this->validated($request,$resource)));return back()->with('success',str($resource)->singular()->headline().' created.');}
 public function update(Request $request,string $resource,int $record):RedirectResponse {Gate::authorize('manageSettings');$model=$this->model($resource);$item=$model::query()->findOrFail($record);$item->update($this->withUploads($request,$resource,$this->validated($request,$resource)));return back()->with('success','Record updated.');}
 public function destroy(string $resource,int $record):RedirectResponse {Gate::authorize('manageSettings');$model=$this->model($resource);$model::query()->findOrFail($record)->delete();return back()->with('success','Record removed.');}
 private function model(string $r):string {abort_unless(isset(self::MODELS[$r]),404);return self::MODELS[$r];}
 private function validated(Request $r,string $x):array {$r->merge(['is_active'=>$r->boolean('is_active')]);return match($x){
  'authors'=>$r->validate(['name'=>'required|string|max:180','designation'=>'nullable|string|max:255','organization'=>'nullable|string|max:255','affiliation'=>'nullable|string|max:255','country'=>'nullable|string|max:120','biography'=>'nullable|string|max:10000','is_active'=>'required|boolean']),
  'volumes'=>$r->validate(['number'=>'required|string|max:40','year'=>'required|integer|min:1800|max:2200','title'=>'nullable|string|max:255','is_current'=>'sometimes|boolean']),
  'issues'=>$r->validate(['journal_volume_id'=>'required|exists:journal_volumes,id','number'=>'required|string|max:40','title'=>'nullable|string|max:255','description'=>'nullable|string|max:4000','cover'=>'nullable|file|mimes:jpg,jpeg,png,webp,svg|max:5120','publication_date'=>'nullable|date','is_current'=>'sometimes|boolean']),
  'editorial-members'=>$r->validate(['name'=>'required|string|max:180','group'=>'required|in:editorial_board,reviewers,advisors,publisher_staff','role'=>'required|string|max:120','institution'=>'nullable|string|max:255','department'=>'nullable|string|max:255','country'=>'nullable|string|max:120','credentials'=>'nullable|string|max:255','biography'=>'nullable|string|max:10000','orcid'=>['nullable','regex:/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/'],'photo'=>'nullable|file|mimes:jpg,jpeg,png,webp,svg|max:5120','sort_order'=>'nullable|integer|min:0','is_active'=>'sometimes|boolean']),
  'indexing-services'=>$r->validate(['name'=>'required|string|max:180','description'=>'nullable|string|max:2000','official_url'=>'nullable|url:http,https|max:2048','logo'=>'nullable|file|mimes:jpg,jpeg,png,webp,svg|max:5120','status'=>'required|in:pending_verification,verified,not_indexed','sort_order'=>'nullable|integer|min:0','is_active'=>'sometimes|boolean']),
  'email-templates'=>$r->validate(['key'=>'required|string|max:120','audience'=>'required|in:author,reviewer,editor_admin','subject'=>'required|string|max:255','body'=>'required|string|max:50000','is_active'=>'sometimes|boolean']),
 };}
 private function withUploads(Request $request,string $resource,array $data):array {foreach(['photo'=>'photo_path','logo'=>'logo_path','cover'=>'cover_image_path'] as $file=>$column){if($request->hasFile($file)){$data[$column]=$request->file($file)->store($resource,'public');}unset($data[$file]);}return $data;}
}
