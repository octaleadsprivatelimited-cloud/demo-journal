@php
$user = auth()->user();
$super = $user->hasRole('super-admin');
$groups = [
 'Articles' => [
  ['All articles','admin.articles.index','articles.view',[]],
  ['Add new','admin.articles.create','articles.create',[]],
  ['Categories','admin.categories.index','categories.manage',[]],
  ['Tags','admin.tags.index','tags.manage',[]],
 ],
 'Reviews' => [
  ['Submissions','admin.submissions.index','articles.review',[]],
  ['Assign reviewers','admin.assign-reviewer.index','articles.review',[]],
  ['Review reports','admin.reviews.index','articles.review',[]],
  ['Manuscript workflow','workflow.index','articles.review',[]],
 ],
 'Media' => [
  ['Media library','admin.media.index','media.manage',[]],
  ['Article files','admin.uploads.index','media.manage',[]],
 ],
 'Website' => [
  ['Pages & policies','admin.pages.index','settings.manage',[]],
  ['Editorial board','admin.readiness.index','settings.manage',['resource'=>'editorial-members']],
  ['Volumes','admin.readiness.index','settings.manage',['resource'=>'volumes']],
  ['Issues','admin.readiness.index','settings.manage',['resource'=>'issues']],
 ],
 'People' => [
  ['Author profiles','admin.readiness.index','settings.manage',['resource'=>'authors']],
  ['Users','admin.users.index','users.manage',[]],
  ['Roles','admin.roles.index','users.manage',[]],
 ],
 'Messages' => [
  ['Enquiries','admin.contacts.index','contacts.manage',[]],
  ['Comments','admin.comments.index','comments.moderate',[]],
  ['Newsletter','admin.newsletter.index','newsletter.manage',[]],
 ],
 'Settings' => [
  ['General settings','admin.settings.index','settings.manage',[]],
  ['Email templates','admin.email-templates','settings.manage',[]],
  ['Activity log','admin.audit.index','audit.view',[]],
 ],
];
@endphp
<x-portal.nav-link :href="$user->hasAnyRole('admin','super-admin') ? route('admin.dashboard') : route('editor.dashboard')" icon="dashboard" :active="request()->routeIs('admin.dashboard','editor.dashboard')">Dashboard</x-portal.nav-link>
@foreach($groups as $label=>$links)
@php
$visible=collect($links)->filter(function($link) use($user,$super) {
 if (!$user->hasPermission($link[2])) return false;
 if (in_array($link[1],['admin.readiness.index','admin.users.index','admin.roles.index','admin.settings.index'])) return $super;
 if (in_array($link[1],['admin.uploads.index','admin.email-templates'])) return $user->hasAnyRole('admin','super-admin');
 return true;
});
$isActive=fn($link)=>request()->routeIs(str_replace(['.index','.create'],'.*',$link[1])) && (!isset($link[3]['resource']) || request()->route('resource')===$link[3]['resource']);
@endphp
@if($visible->isNotEmpty())
<details class="admin-nav-section" @if($visible->contains($isActive)) open @endif>
 <summary>{{ $label }}</summary><div>
 @foreach($visible as $link)
 <x-portal.nav-link :href="route($link[1],$link[3])" icon="arrow" :active="request()->routeIs($link[1]) && (!isset($link[3]['resource']) || request()->route('resource')===$link[3]['resource'])">{{ $link[0] }}</x-portal.nav-link>
 @endforeach
 </div>
</details>
@endif
@endforeach
@if($user->hasAnyRole('admin','super-admin'))<x-portal.nav-link :href="route('admin.account.edit')" icon="users" :active="request()->routeIs('admin.account.*')">My account</x-portal.nav-link>@endif
@if($user->hasRole('editor'))<x-portal.nav-link :href="route('editor.profile.edit')" icon="users" :active="request()->routeIs('editor.profile.*')">My profile</x-portal.nav-link>@endif
