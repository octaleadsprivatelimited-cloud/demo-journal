<?php

namespace App\Http\Controllers\Public;

use App\Events\ContactSubmissionReceived;
use App\Http\Requests\Public\ContactRequest;
use App\Models\ContactSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class ContactController extends PublicController
{
    public function store(ContactRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $submission = ContactSubmission::query()->create([
            'user_id' => $request->user()?->getKey(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'subject' => $validated['subject'],
            'category' => $validated['category'] ?? 'general',
            'message' => $validated['message'],
            'status' => 'new',
            'ip_hash' => $request->ip()
                ? hash_hmac('sha256', $request->ip(), (string) config('app.key'))
                : null,
            'user_agent' => Str::limit((string) $request->userAgent(), 1024, ''),
        ]);

        ContactSubmissionReceived::dispatch($submission);

        return back()->with('success', 'Thank you. Your message has reached our editorial team.');
    }
}
