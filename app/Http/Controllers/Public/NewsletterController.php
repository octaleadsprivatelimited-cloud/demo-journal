<?php

namespace App\Http\Controllers\Public;

use App\Http\Requests\Public\NewsletterRequest;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class NewsletterController extends PublicController
{
    public function subscribe(NewsletterRequest $request): RedirectResponse
    {
        abort_unless($this->featureEnabled('newsletter'), 404);

        $validated = $request->validated();

        $subscriber = NewsletterSubscriber::withTrashed()
            ->firstOrNew(['email' => $validated['email']]);

        if (method_exists($subscriber, 'trashed') && $subscriber->trashed()) {
            $subscriber->restore();
        }

        $subscriber->fill([
            'name' => $validated['name'] ?? $subscriber->name,
            'status' => 'active',
            'source' => $validated['source'] ?? 'website',
            'subscribed_at' => $subscriber->subscribed_at ?? now(),
            'unsubscribed_at' => null,
            'token' => $subscriber->token ?: (string) Str::uuid(),
        ])->save();

        return back()->with(
            'success',
            $subscriber->wasRecentlyCreated
                ? 'You are on the list. Watch your inbox for the next edition.'
                : 'Your journal subscription is active.',
        );
    }

    public function unsubscribe(string $token): RedirectResponse
    {
        $subscriber = NewsletterSubscriber::query()
            ->where('token', $token)
            ->firstOrFail();

        $subscriber->forceFill([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ])->save();

        return redirect()->route('home')->with('success', 'You have been unsubscribed. You can rejoin at any time.');
    }
}
