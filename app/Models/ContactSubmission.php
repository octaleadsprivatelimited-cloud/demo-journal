<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactStatus;
use App\Models\Concerns\HasAuditLogs;
use App\Observers\AuditObserver;
use App\Observers\ContactSubmissionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([AuditObserver::class, ContactSubmissionObserver::class])]
class ContactSubmission extends Model
{
    use HasAuditLogs, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'assigned_to_id', 'name', 'email', 'phone', 'subject', 'message',
        'category', 'status', 'ip_hash', 'user_agent', 'read_at', 'replied_at', 'closed_at',
    ];

    protected $hidden = ['ip_hash', 'user_agent'];

    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
            'read_at' => 'datetime',
            'replied_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function scopeStatus(Builder $query, ContactStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof ContactStatus ? $status->value : $status);
    }
}
