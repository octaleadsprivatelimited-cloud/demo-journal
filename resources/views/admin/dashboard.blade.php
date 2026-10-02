@extends('layouts.portal')
@section('title','Dashboard')
@section('section','Dashboard')
@section('eyebrow','Journal administration')
@section('page-title','Dashboard')
@section('page-description','Manage articles, reviews and the information readers see on your website.')
@section('page-actions')
<a class="portal-button" href="{{ route('home') }}">View website ↗</a>
<a class="portal-button primary" href="{{ route('admin.articles.create') }}">Add article</a>
@endsection
@section('content')
<div class="metric-grid">
 <a href="{{ route('admin.articles.index',['status'=>'published']) }}"><x-portal.metric label="Published articles" :value="$stats['published']" hint="Visible on the website" /></a>
 <a href="{{ route('admin.articles.index',['status'=>'draft']) }}"><x-portal.metric label="Draft articles" :value="$stats['drafts']" hint="Continue editing" /></a>
 <a href="{{ route('admin.submissions.index',['status'=>'pending']) }}"><x-portal.metric label="Awaiting decision" :value="$stats['pending']" hint="Review submissions" tone="gold" /></a>
 <a href="{{ route('admin.reviews.index') }}"><x-portal.metric label="Reviews due soon" :value="$stats['reviewsDue']" hint="Within seven days" tone="blue" /></a>
</div>
<section class="portal-card admin-workflow-guide" style="margin-bottom:20px">
 <div class="portal-card-head"><div><h2>Article workflow</h2><p>Follow these steps for every manuscript submitted by an author.</p></div><a class="portal-button small" href="{{ route('workflow.index') }}">Open workflow</a></div>
 <div class="portal-card-body"><div class="admin-flow-steps">
  <a class="admin-flow-step" href="{{ route('admin.submissions.index',['status'=>'pending']) }}"><span class="admin-flow-number">1</span><strong>New submissions</strong><small>{{ $stats['pending'] }} waiting for an editorial check</small></a>
  <a class="admin-flow-step" href="{{ route('workflow.index',['stage'=>'editorial_screening']) }}"><span class="admin-flow-number">2</span><strong>Assign an editor</strong><small>Open the manuscript and choose its handling editor</small></a>
  <a class="admin-flow-step" href="{{ route('admin.assign-reviewer.index') }}"><span class="admin-flow-number">3</span><strong>Assign reviewers</strong><small>{{ $stats['underReview'] }} currently under review</small></a>
  <a class="admin-flow-step" href="{{ route('workflow.index',['publication_status'=>'approved']) }}"><span class="admin-flow-number">4</span><strong>Make a decision</strong><small>{{ $stats['approved'] }} approved for production or publication</small></a>
  <a class="admin-flow-step" href="{{ route('admin.articles.index',['status'=>'published']) }}"><span class="admin-flow-number">5</span><strong>Publish</strong><small>{{ $stats['published'] }} visible on the website</small></a>
 </div></div>
</section>
<div class="dashboard-grid">
 <section class="portal-card">
  <div class="portal-card-head"><div><h2>Submissions to review</h2><p>Open a submission to review it or assign a reviewer.</p></div><a class="portal-button small" href="{{ route('admin.assign-reviewer.index') }}">Assign reviewers</a></div>
  @forelse($pendingSubmissions as $submission)
   <a class="admin-queue-row" href="{{ route('admin.submissions.show',$submission) }}"><span class="admin-queue-main"><strong>{{ $submission->article?->title ?? 'Archived manuscript' }}</strong><small>{{ $submission->submitter?->name ?? 'System' }} · {{ $submission->submitted_at?->diffForHumans() }}</small></span><x-portal.status value="pending" /></a>
  @empty<div class="portal-card-body"><x-portal.empty title="No pending submissions" message="New author submissions will appear here." /></div>@endforelse
 </section>
 <section class="portal-card">
  <div class="portal-card-head"><div><h2>Review follow-up</h2><p>Assignments approaching their due date.</p></div><a class="portal-button small" href="{{ route('admin.reviews.index') }}">All reviews</a></div>
  @forelse($reviewsNeedingAttention as $review)
   <a class="admin-queue-row" href="{{ route('admin.reviews.show',$review) }}"><span class="admin-queue-main"><strong>{{ $review->article?->title ?? 'Archived manuscript' }}</strong><small>{{ $review->reviewer?->name ?? 'Unassigned' }} · Due {{ $review->due_at?->toFormattedDateString() }}</small></span><x-portal.status :value="$review->status" /></a>
  @empty<div class="portal-card-body"><x-portal.empty title="No reviews due soon" message="Review assignments due within seven days will appear here." /></div>@endforelse
 </section>
</div>
<section class="portal-card" style="margin-top:20px">
 <div class="portal-card-head"><h2>Manage your website</h2></div>
 <div class="portal-card-body admin-start-grid">
  <a class="admin-start-card" href="{{ route('admin.articles.index') }}"><strong>Articles →</strong><span>Edit, publish or move articles to trash.</span></a>
  @if(auth()->user()->hasPermission('settings.manage'))<a class="admin-start-card" href="{{ route('admin.pages.index') }}"><strong>Pages & policies →</strong><span>Update the pages linked above the footer.</span></a>@endif
  @if(auth()->user()->hasRole('super-admin'))<a class="admin-start-card" href="{{ route('admin.readiness.index','authors') }}"><strong>Author profiles →</strong><span>Edit contributor names and profile details.</span></a>@endif
  <a class="admin-start-card" href="{{ route('admin.contacts.index') }}"><strong>Enquiries →</strong><span>{{ $stats['contacts'] }} new messages from your readers.</span></a>
 </div>
</section>
@if($stats['applications'] && auth()->user()->hasRole('super-admin'))<div class="portal-card" style="margin-top:20px"><div class="portal-card-body"><a href="{{ route('admin.users.index') }}">{{ $stats['applications'] }} account applications need approval →</a></div></div>@endif
@endsection
