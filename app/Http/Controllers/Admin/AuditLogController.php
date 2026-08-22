<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAuditLogs');

        return view('admin.audit.index', ['logs' => AuditLog::query()->with('actor:id,name,email')
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->input('event')))->when($request->filled('actor'), fn ($q) => $q->where('actor_id', $request->integer('actor')))
            ->latest('created_at')->paginate(40)->withQueryString(), 'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event')]);
    }

    public function show(AuditLog $auditLog): View
    {
        Gate::authorize('viewAuditLogs');

        return view('admin.audit.show', ['log' => $auditLog->load('actor:id,name,email')]);
    }
}
