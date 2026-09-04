<!doctype html><html lang="en"><head><meta charset="UTF-8"><style>
@page { margin: 48pt 48pt 65pt; }
body { font-family: 'DejaVu Sans', sans-serif; color:#20334b; font-size:10pt; line-height:1.6; }
header { border-bottom:2pt solid #208ac3; padding-bottom:16pt; margin-bottom:24pt; }
.logo { width:145pt; height:auto; margin-bottom:12pt; }
h1 { font-size:17pt; margin:0; } h2 { font-size:14pt; color:#208ac3; margin:0 0 20pt; }
.label { color:#607185; font-size:9pt; margin-bottom:2pt; }
p { margin:0 0 14pt; } .field { margin-bottom:13pt; } .title { font-size:12pt; font-weight:bold; }
footer { position:fixed; bottom:-37pt; font-size:8pt; color:#607185; border-top:1pt solid #ddd; width:100%; padding-top:8pt; }
</style></head><body>
<footer>Larix Journals · Singapore Journal of Cardiology · ISSN {{ config('workflow.issn') }}</footer>
<header><img class="logo" src="{{ $logo }}" alt="Larix International"><h1>{{ config('workflow.journal') }}</h1><div>ISSN {{ config('workflow.issn') }}</div></header>
<h2>Acceptance letter</h2>
<div class="field"><div class="label">Manuscript ID</div><strong>{{ $article->workflow->manuscript_id }}</strong></div>
<div class="field"><div class="label">Article title</div><div class="title">{{ $acceptance['title'] }}</div></div>
<div class="field"><div class="label">Authors</div>{{ $acceptance['author'] }}</div>
<div class="field"><div class="label">Acceptance date</div>{{ $acceptance['date'] }}</div>
<p>We are pleased to confirm that this manuscript has been accepted for publication in {{ config('workflow.journal') }} following editorial assessment and peer review.</p>
<p>The manuscript will proceed through copyediting, author proof review and publication preparation.</p>
<div class="field"><div class="label">Accepting editor</div><strong>{{ $acceptance['editor'] }}</strong></div>
</body></html>
