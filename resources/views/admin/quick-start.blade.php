<div class="portal-card"><div class="portal-card-head"><div><h2>Welcome to your journal</h2><p>Choose what you want to do. More tools are grouped in the left menu.</p></div></div><div class="portal-card-body admin-start-grid">
@foreach([['Write an article','Create a draft and upload your files.','admin.articles.create','articles.create'],['Manage articles','Find, edit, or move articles to trash.','admin.articles.index','articles.view'],['Assign a reviewer','Choose a manuscript and invite a reviewer.','admin.assign-reviewer.index','articles.review'],['Manage media','Upload images or documents. Images up to 5 MB.','admin.media.index','media.manage']] as [$label,$description,$route,$permission])
@if(auth()->user()->hasPermission($permission))<a class="admin-start-card" href="{{ route($route) }}"><strong>{{ $label }} →</strong><span>{{ $description }}</span></a>@endif
@endforeach
</div></div>
