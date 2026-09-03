<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, follow">
    <title>{{ $article->title }} — {{ data_get($site, 'name') }}</title>
    <style>
        :root{color:#171b1a;font-family:Georgia,'Times New Roman',serif;font-size:11pt;line-height:1.65}body{margin:0 auto;max-width:760px;padding:42px 32px}header{border-bottom:1px solid #aaa;margin-bottom:32px;padding-bottom:24px}.journal{font:700 10pt Arial,sans-serif;letter-spacing:.12em;text-transform:uppercase}.category{color:#8a4b2b;font:700 9pt Arial,sans-serif;letter-spacing:.1em;text-transform:uppercase}h1{font-size:30pt;line-height:1.08;margin:18px 0 12px}h2{font-size:18pt;margin-top:2em}h3{font-size:14pt;margin-top:1.7em}.dek{color:#4c5552;font-size:15pt}.meta{font:10pt Arial,sans-serif;color:#4c5552}.abstract{background:#f2f0e9;border-left:3px solid #8a4b2b;margin:30px 0;padding:18px 22px}.content img{height:auto;max-width:100%}.content blockquote{border-left:3px solid #777;font-size:14pt;margin:2em 0;padding-left:1.5em}.content a{color:inherit}.references{border-top:1px solid #aaa;margin-top:36px;padding-top:20px;font-size:9.5pt}.print-actions{position:fixed;right:20px;top:20px}.print-actions button{background:#173b35;border:0;color:#fff;cursor:pointer;padding:10px 16px}@media print{body{padding:0}.print-actions{display:none}@page{margin:2cm}}
    </style>
</head>
<body>
    <div class="print-actions"><button type="button" onclick="window.print()">Print article</button></div>
    <header>
        <p class="journal">{{ data_get($site, 'name') }}</p>
        @if($article->category)<p class="category">{{ $article->category->name }}</p>@endif
        <h1>{{ $article->title }}</h1>
        @if($article->subtitle)<p class="dek">{{ $article->subtitle }}</p>@endif
        <p class="meta">By {{ $article->authors->pluck('name')->join(', ') }} · {{ optional($article->published_at ?? $article->created_at)->format('F j, Y') }} · {{ $article->reading_time_minutes }} min read</p>
        <p class="meta">{{ route('articles.show', $article->slug) }}</p>
    </header>
    @if($article->abstract)<section class="abstract"><strong>Abstract.</strong> {{ $article->abstract }}</section>@endif
    <main class="content">{!! $article->content !!}</main>
    @if(collect($article->references)->filter()->isNotEmpty())
        <section class="references"><h2>References</h2><ol>@foreach($article->references as $reference)<li>{{ is_array($reference) ? ($reference['citation'] ?? $reference['title'] ?? collect($reference)->filter()->join('. ')) : $reference }}</li>@endforeach</ol></section>
    @endif
    <script>window.addEventListener('load',()=>{if(new URLSearchParams(location.search).has('auto'))window.print()});</script>
</body>
</html>
