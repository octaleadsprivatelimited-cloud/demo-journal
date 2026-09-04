@can('manageHomepage', $article)
<div class="portal-card">
    <div class="portal-card-head"><h2>Homepage hero</h2></div>
    <form class="portal-card-body portal-form" method="post" action="{{ route('admin.articles.action', $article) }}">
        @csrf
        <input type="hidden" name="action" value="homepage">
        <label class="portal-field">Article placement
            <select class="portal-select" name="placement">
                <option value="hidden" @selected(!$article->is_featured && !$article->is_homepage_latest)>Hidden from hero</option>
                <option value="featured" @selected($article->is_featured)>Featured article</option>
                <option value="latest" @selected(!$article->is_featured && $article->is_homepage_latest)>Latest article</option>
            </select>
        </label>
        <p class="portal-help">Only published articles appear. Featured selections play first, followed by latest selections, newest first in each group. Hidden articles remain in the archive and recent publications.</p>
        <button class="portal-button" type="submit">Save hero placement</button>
    </form>
</div>
@endcan
@can('update', $article)
<div class="portal-card"><div class="portal-card-body">
    <form method="post" action="{{ route('admin.articles.action', $article) }}">
        @csrf
        <input type="hidden" name="action" value="{{ $article->is_trending ? 'untrend' : 'trend' }}">
        <button class="portal-button" type="submit">{{ $article->is_trending ? 'Remove trending' : 'Mark trending' }}</button>
    </form>
</div></div>
@endcan
