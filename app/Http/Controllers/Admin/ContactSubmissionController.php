<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ContactStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactReplyRequest;
use App\Http\Requests\Admin\ContactStatusRequest;
use App\Models\ContactSubmission;
use App\Models\User;
use App\Notifications\ContactReplyNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ContactSubmissionController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ContactSubmission::class);

        return view('admin.contacts.index', ['contacts' => ContactSubmission::query()->with('assignee:id,name')->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.addcslashes($request->input('q'), '%_').'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('subject', 'like', $term));
            })
            ->latest()->paginate(25)->withQueryString(), 'statuses' => ContactStatus::cases()]);
    }

    public function show(ContactSubmission $contact): View
    {
        Gate::authorize('view', $contact);
        if ($contact->status === ContactStatus::New) {
            $contact->update(['status' => ContactStatus::Read, 'read_at' => now()]);
        }

        return view('admin.contacts.show', ['contact' => $contact->load('assignee:id,name,email'), 'assignees' => User::query()->active()->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(ContactStatusRequest $request, ContactSubmission $contact): RedirectResponse
    {
        Gate::authorize('update', $contact);
        $status = $request->enum('status', ContactStatus::class);
        $data = ['status' => $status, 'assigned_to_id' => $request->input('assigned_to_id')];
        if ($status === ContactStatus::Read) {
            $data['read_at'] = $contact->read_at ?? now();
        } if ($status === ContactStatus::Replied) {
            $data['replied_at'] = now();
        } if ($status === ContactStatus::Closed) {
            $data['closed_at'] = now();
        }
        $contact->update($data);

        return back()->with('success', 'Enquiry status updated.');
    }

    public function destroy(ContactSubmission $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);
        $contact->delete();

        return redirect()->route('admin.contacts.index')->with('success', 'Enquiry archived.');
    }

    public function reply(ContactReplyRequest $request, ContactSubmission $contact): RedirectResponse
    {
        Gate::authorize('update', $contact);
        Notification::route('mail', [$contact->email => $contact->name])->notify(new ContactReplyNotification($request->string('subject')->toString(), $request->string('body')->toString(), $request->user()->name));
        $contact->update(['status' => ContactStatus::Replied, 'replied_at' => now(), 'read_at' => $contact->read_at ?? now(), 'assigned_to_id' => $request->user()->getKey()]);

        return back()->with('success', 'Reply queued for delivery to '.$contact->email.'.');
    }

    public function export(): StreamedResponse
    {
        Gate::authorize('export', ContactSubmission::class);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Name', 'Email', 'Phone', 'Subject', 'Category', 'Status', 'Submitted']);
            ContactSubmission::query()->orderBy('id')->chunk(500, fn ($rows) => $rows->each(fn ($row) => fputcsv($out, [$row->name, $row->email, $row->phone, $row->subject, $row->category, $row->status->value, $row->created_at->toISOString()])));
            fclose($out);
        }, 'contact-enquiries-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
