<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\ArticleStatusChanged;
use App\Events\ArticleSubmitted;
use App\Events\ContactSubmissionReceived;
use App\Events\ReviewAssigned;
use App\Events\ReviewCompleted;
use App\Listeners\AuthenticationAuditSubscriber;
use App\Listeners\NotifyAdminsOfContactSubmission;
use App\Listeners\NotifyAuthorOfArticleStatus;
use App\Listeners\NotifyAuthorOfArticleSubmission;
use App\Listeners\NotifyEditorsOfArticleSubmission;
use App\Listeners\NotifyReviewerOfAssignment;
use App\Listeners\NotifyStakeholdersOfCompletedReview;
use App\Listeners\WriteArticleStatusAudit;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

final class EventServiceProvider extends ServiceProvider
{
    /** @var array<class-string, list<class-string>> */
    protected $listen = [
        ArticleStatusChanged::class => [
            WriteArticleStatusAudit::class,
            NotifyAuthorOfArticleStatus::class,
        ],
        ArticleSubmitted::class => [
            NotifyEditorsOfArticleSubmission::class,
            NotifyAuthorOfArticleSubmission::class,
        ],
        ReviewAssigned::class => [
            NotifyReviewerOfAssignment::class,
        ],
        ReviewCompleted::class => [
            NotifyStakeholdersOfCompletedReview::class,
        ],
        ContactSubmissionReceived::class => [
            NotifyAdminsOfContactSubmission::class,
        ],
    ];

    public function boot(): void
    {
        Event::subscribe(AuthenticationAuditSubscriber::class);
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
