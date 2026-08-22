<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NewsletterCampaignRequest;
use App\Jobs\SendNewsletterCampaign;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Services\RichTextSanitizer;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class NewsletterSubscriberController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', NewsletterSubscriber::class);

        return view('admin.newsletter.index', ['subscribers' => NewsletterSubscriber::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))->when($request->filled('q'), fn ($q) => $q->where('email', 'like', '%'.addcslashes($request->input('q'), '%_').'%'))
            ->latest('subscribed_at')->paginate(30)->withQueryString(), 'campaigns' => NewsletterCampaign::query()->with('creator:id,name')->latest()->limit(12)->get()]);
    }

    public function destroy(NewsletterSubscriber $subscriber): RedirectResponse
    {
        Gate::authorize('delete', $subscriber);
        $subscriber->delete();

        return back()->with('success', 'Subscriber removed.');
    }

    public function export(): StreamedResponse
    {
        Gate::authorize('export', NewsletterSubscriber::class);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Name', 'Email', 'Status', 'Source', 'Subscribed']);
            NewsletterSubscriber::query()->orderBy('id')->chunk(500, fn ($rows) => $rows->each(fn ($row) => fputcsv($out, [$row->name, $row->email, $row->status->value, $row->source, $row->subscribed_at?->toISOString()])));
            fclose($out);
        }, 'newsletter-subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function createCampaign(): View
    {
        Gate::authorize('viewAny', NewsletterSubscriber::class);

        return view('admin.newsletter.create');
    }

    public function storeCampaign(NewsletterCampaignRequest $request, RichTextSanitizer $sanitizer): RedirectResponse
    {
        Gate::authorize('viewAny', NewsletterSubscriber::class);
        $campaign = NewsletterCampaign::query()->create(['public_id' => (string) Str::uuid(), 'created_by_id' => $request->user()->getKey(), 'subject' => $request->input('subject'),
            'preview_text' => $request->input('preview_text'), 'content' => $sanitizer->sanitize($request->input('content')), 'status' => $request->input('action') === 'schedule' ? 'scheduled' : 'draft',
            'scheduled_for' => $request->input('action') === 'schedule' ? CarbonImmutable::parse($request->input('scheduled_for')) : null]);
        $this->dispatchCampaign($campaign, $request->input('action'));

        return redirect()->route('admin.newsletter.index')->with('success', $request->input('action') === 'draft' ? 'Campaign saved as a draft.' : 'Campaign queued for delivery.');
    }

    public function editCampaign(NewsletterCampaign $campaign): View
    {
        Gate::authorize('viewAny', NewsletterSubscriber::class);
        abort_unless(in_array($campaign->status, ['draft', 'scheduled', 'failed'], true), 409, 'Sent campaigns are immutable.');

        return view('admin.newsletter.edit', compact('campaign'));
    }

    public function updateCampaign(NewsletterCampaignRequest $request, NewsletterCampaign $campaign, RichTextSanitizer $sanitizer): RedirectResponse
    {
        Gate::authorize('viewAny', NewsletterSubscriber::class);
        abort_unless(in_array($campaign->status, ['draft', 'scheduled', 'failed'], true), 409, 'Sent campaigns are immutable.');
        $campaign->update(['subject' => $request->input('subject'), 'preview_text' => $request->input('preview_text'), 'content' => $sanitizer->sanitize($request->input('content')),
            'status' => $request->input('action') === 'schedule' ? 'scheduled' : 'draft', 'scheduled_for' => $request->input('action') === 'schedule' ? CarbonImmutable::parse($request->input('scheduled_for')) : null, 'failure_message' => null]);
        $this->dispatchCampaign($campaign, $request->input('action'));

        return redirect()->route('admin.newsletter.index')->with('success', 'Campaign updated'.($request->input('action') === 'draft' ? '.' : ' and queued.'));
    }

    public function sendCampaign(NewsletterCampaign $campaign): RedirectResponse
    {
        Gate::authorize('viewAny', NewsletterSubscriber::class);
        abort_unless(in_array($campaign->status, ['draft', 'failed'], true), 409);
        $campaign->update(['status' => 'draft', 'scheduled_for' => null, 'failure_message' => null]);
        SendNewsletterCampaign::dispatch($campaign->getKey());

        return back()->with('success', 'Campaign queued for immediate delivery.');
    }

    public function destroyCampaign(NewsletterCampaign $campaign): RedirectResponse
    {
        Gate::authorize('viewAny', NewsletterSubscriber::class);
        abort_unless(in_array($campaign->status, ['draft', 'failed'], true), 409);
        $campaign->delete();

        return back()->with('success', 'Campaign draft deleted.');
    }

    private function dispatchCampaign(NewsletterCampaign $campaign, string $action): void
    {
        if ($action === 'send') {
            SendNewsletterCampaign::dispatch($campaign->getKey());
        }
        if ($action === 'schedule') {
            SendNewsletterCampaign::dispatch($campaign->getKey())->delay($campaign->scheduled_for);
        }
    }
}
