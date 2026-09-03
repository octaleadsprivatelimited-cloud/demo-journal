<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriberStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'status', 'token', 'source', 'subscribed_at', 'confirmed_at', 'unsubscribed_at',
    ];

    protected $hidden = ['token'];

    protected static function booted(): void
    {
        static::creating(function (self $subscriber): void {
            $subscriber->token ??= (string) Str::uuid();
            $subscriber->subscribed_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => SubscriberStatus::class,
            'subscribed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SubscriberStatus::Active);
    }

    public function unsubscribe(): bool
    {
        return $this->forceFill([
            'status' => SubscriberStatus::Unsubscribed,
            'unsubscribed_at' => now(),
        ])->save();
    }
}
