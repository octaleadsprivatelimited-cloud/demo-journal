<details class="portal-card workflow-guide">
    <summary class="portal-card-head"><strong>How the workflow works — 6 main steps</strong></summary>
    <div class="portal-card-body">
        <p>Open a manuscript, read its current stage and use the available action for your role. The extra stages record checks within these six steps; you do not complete every tab on every visit.</p>
        <ol class="workflow-guide-steps">
            @foreach(array_slice(config('workflow-guide.phases'), 0, 6) as $phase)
                <li><strong>{{ $phase['label'] }}</strong><p>{{ $phase['owner'] }}</p><p>{{ $phase['description'] }}</p></li>
            @endforeach
        </ol>
        <p>Emails and in-app notifications tell the relevant author, editor or reviewer when action is needed. Only the actions allowed at the current stage are available. DOI and indexing checks record work completed with the external provider.</p>
        @if(auth()->user()->hasRole('super-admin'))<p><strong>Add staff:</strong> Users → New user → select Editor or Reviewer. Alternatively, approve a registered staff application under Users. Creating a public Editorial board profile does not create a login account.</p>@endif
    </div>
</details>
