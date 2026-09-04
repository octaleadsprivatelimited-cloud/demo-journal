<?php

namespace App\Console\Commands;

use App\Models\ManuscriptWorkflow;
use App\Models\Review;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendWorkflowReminders extends Command
{
    protected $signature = 'workflow:remind';

    protected $description = 'Queue one reminder per deadline and overdue event';

    public function handle(): int
    {
        $sent = 0;
        foreach (Review::with('article.workflow', 'reviewer')->whereIn('status', ['assigned', 'in_progress'])->cursor() as $review) {
            $w = $review->article?->workflow;
            if (! $w || ! in_array($w->stage, ['reviewer_assignment', 'under_review', 'reviewer_recheck']) || $review->submission_id != data_get($w->data, 'current_submission_id')) {
                continue;
            }
            $due = $review->status->value === 'assigned' ? $review->invitation_deadline : $review->due_at;
            if (! $due || $due->greaterThan(now()->addDays(2))) {
                continue;
            }
            $type = $due->isPast() ? 'overdue' : 'due soon';
            $key = 'review-'.$review->id.'-'.$due->timestamp.'-'.$type;
            $recipients = [$review->reviewer_id];
            if ($type === 'overdue') {
                $recipients[] = $review->article->assigned_editor_id;
                $recipients = array_merge($recipients, User::active()->whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'super-admin']))->pluck('id')->all());
            }
            $sent += $this->send($key, $review->article_id, 'Review '.$type, $recipients);
        }
        foreach (ManuscriptWorkflow::with('article')->whereIn('stage', ['minor_revision', 'major_revision', 'proofing'])->whereNotNull('deadline')->where('deadline', '<=', now()->addDays(2))->cursor() as $w) {
            if (! $w->article) {
                continue;
            }$type = $w->deadline->isPast() ? 'overdue' : 'due soon';
            $sent += $this->send('manuscript-'.$w->id.'-'.$w->deadline->timestamp.'-'.$type, $w->article_id, $w->label().' '.$type, [$w->article->created_by_id, $w->article->assigned_editor_id]);
        }
        $this->info('Queued '.$sent.' deadline notifications.');

        return self::SUCCESS;
    }

    private function send(string $key, int $articleId, string $message, array $ids): int
    {
        return DB::transaction(function () use ($key, $articleId, $message, $ids) {
            if (! DB::table('workflow_reminders')->insertOrIgnore(['key' => $key, 'created_at' => now()])) {
                return 0;
            }$users = User::active()->whereIn('id', array_unique(array_filter($ids)))->get();
            foreach ($users as $user) {
                $user->notify(new WorkflowNotification($articleId,$message));
            }

return $users->count();
        });
    }
}
