<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Enums\ReviewRecommendation;
use App\Enums\ReviewStatus;
use App\Enums\SubmissionStatus;
use App\Models\Article;
use App\Models\ArticleView;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContactSubmission;
use App\Models\NewsletterSubscriber;
use App\Models\Role;
use App\Models\SeoMetadata;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class EditorialContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureDemoSeedingIsAllowed();

        $authorRole = Role::query()->where('slug', 'author')->firstOrFail();
        $reviewerRole = Role::query()->where('slug', 'reviewer')->firstOrFail();
        $editorRole = Role::query()->where('slug', 'editor')->firstOrFail();

        $authors = collect(range(1, 10))->map(function (): Author {
            return Author::factory()->for($this->createDemoUser())->create();
        });
        $authors->each(fn (Author $author) => $author->user()->firstOrFail()->roles()->syncWithoutDetaching([
            $authorRole->getKey() => ['assigned_at' => now()],
        ]));

        $reviewers = collect(range(1, 4))->map(fn (): User => $this->createDemoUser());
        $reviewers->each(fn (User $user) => $user->roles()->attach($reviewerRole, ['assigned_at' => now()]));
        $editors = collect(range(1, 2))->map(fn (): User => $this->createDemoUser());
        $editors->each(fn (User $user) => $user->roles()->attach($editorRole, ['assigned_at' => now()]));

        $categories = collect([
            ['name' => 'Science & Discovery', 'description' => 'Peer-informed research and discoveries across the natural sciences.'],
            ['name' => 'Technology & Society', 'description' => 'Critical perspectives on technology and its social consequences.'],
            ['name' => 'Public Policy', 'description' => 'Evidence-led analysis of institutions, regulation, and public life.'],
            ['name' => 'Culture & Ideas', 'description' => 'Essays on culture, history, philosophy, and contemporary ideas.'],
            ['name' => 'Climate & Environment', 'description' => 'Research and reporting on environmental systems and climate action.'],
        ])->map(fn (array $data, int $index) => Category::query()->create([...$data, 'sort_order' => $index + 1, 'is_active' => true]));

        $tags = collect([
            'Artificial Intelligence', 'Biodiversity', 'Climate Policy', 'Democracy', 'Digital Humanities',
            'Education', 'Energy Transition', 'Ethics', 'Global Health', 'Innovation', 'Machine Learning',
            'Open Science', 'Public Finance', 'Research Methods', 'Social Justice', 'Space Science',
            'Sustainability', 'Urban Futures', 'Water Security', 'Work & Economy',
        ])->map(fn (string $name) => Tag::query()->create(['name' => $name, 'description' => "Research and commentary about {$name}."]));

        $statuses = [
            ArticleStatus::Published, ArticleStatus::Published, ArticleStatus::Published,
            ArticleStatus::Draft, ArticleStatus::Submitted, ArticleStatus::UnderReview,
            ArticleStatus::RevisionRequired, ArticleStatus::Approved, ArticleStatus::Scheduled,
            ArticleStatus::Rejected,
        ];

        foreach (range(0, 49) as $index) {
            $status = $statuses[$index % count($statuses)];
            $primaryAuthor = $authors[$index % $authors->count()];
            $article = Article::factory()->create([
                'category_id' => $categories[$index % $categories->count()]->getKey(),
                'created_by_id' => $primaryAuthor->user_id,
                'assigned_editor_id' => $editors[$index % $editors->count()]->getKey(),
                'status' => $status,
                'view_count' => 0,
                'submitted_at' => $status === ArticleStatus::Draft ? null : now()->subDays(60 - ($index % 30)),
                'approved_at' => in_array($status, [ArticleStatus::Approved, ArticleStatus::Scheduled, ArticleStatus::Published], true) ? now()->subDays(15) : null,
                'scheduled_for' => $status === ArticleStatus::Scheduled ? now()->addDays(($index % 10) + 1) : null,
                'published_at' => $status === ArticleStatus::Published ? now()->subDays(($index % 90) + 1) : null,
                'rejected_at' => $status === ArticleStatus::Rejected ? now()->subDays(3) : null,
            ]);

            $coauthors = $authors->where('id', '!=', $primaryAuthor->getKey())->random(2);
            $article->authors()->attach($primaryAuthor, ['is_corresponding' => true, 'sort_order' => 0]);
            foreach ($coauthors as $position => $coauthor) {
                $article->authors()->attach($coauthor, ['is_corresponding' => false, 'sort_order' => $position + 1]);
            }
            $article->tags()->sync($tags->random(4)->pluck('id')->all());

            SeoMetadata::query()->create([
                'seoable_type' => $article->getMorphClass(),
                'seoable_id' => $article->getKey(),
                'seo_title' => Str::limit($article->title, 60, ''),
                'meta_description' => Str::limit((string) $article->abstract, 155, ''),
                'focus_keywords' => $article->keywords,
                'schema_type' => 'ScholarlyArticle',
            ]);

            if ($status !== ArticleStatus::Draft) {
                $version = $article->versions()->create([
                    'created_by_id' => $primaryAuthor->user_id,
                    'version_number' => 1,
                    'title' => $article->title,
                    'subtitle' => $article->subtitle,
                    'abstract' => $article->abstract,
                    'content' => $article->content,
                    'keywords' => $article->keywords,
                    'references' => $article->references,
                    'change_summary' => 'Initial submission',
                ]);
                $submissionStatus = match ($status) {
                    ArticleStatus::Submitted => SubmissionStatus::Pending,
                    ArticleStatus::UnderReview => SubmissionStatus::InReview,
                    ArticleStatus::RevisionRequired => SubmissionStatus::RevisionRequested,
                    ArticleStatus::Rejected => SubmissionStatus::Rejected,
                    default => SubmissionStatus::Accepted,
                };
                $submission = $article->submissions()->create([
                    'article_version_id' => $version->getKey(),
                    'submitted_by_id' => $primaryAuthor->user_id,
                    'round' => 1,
                    'status' => $submissionStatus,
                    'cover_letter' => 'Please consider this manuscript for publication.',
                    'submitted_at' => $article->submitted_at,
                    'decision_at' => in_array($submissionStatus, [SubmissionStatus::Accepted, SubmissionStatus::Rejected], true) ? now()->subDays(3) : null,
                ]);

                if (in_array($status, [ArticleStatus::UnderReview, ArticleStatus::RevisionRequired, ArticleStatus::Approved, ArticleStatus::Rejected], true)) {
                    $completed = $status !== ArticleStatus::UnderReview;
                    $article->reviews()->create([
                        'submission_id' => $submission->getKey(),
                        'reviewer_id' => $reviewers[$index % $reviewers->count()]->getKey(),
                        'assigned_by_id' => $editors[$index % $editors->count()]->getKey(),
                        'status' => $completed ? ReviewStatus::Completed : ReviewStatus::Assigned,
                        'recommendation' => $completed ? match ($status) {
                            ArticleStatus::Approved => ReviewRecommendation::Approve,
                            ArticleStatus::Rejected => ReviewRecommendation::Reject,
                            default => ReviewRecommendation::MajorRevision,
                        } : null,
                        'comments_to_author' => $completed ? 'The manuscript is well considered; please address the detailed methodological notes.' : null,
                        'due_at' => now()->addDays(14),
                        'completed_at' => $completed ? now()->subDays(2) : null,
                    ]);
                }
            }

            if ($status === ArticleStatus::Published) {
                $seededViewCount = ($index % 7) + 3;

                foreach (range(1, $seededViewCount) as $viewIndex) {
                    ArticleView::query()->create([
                        'article_id' => $article->getKey(),
                        'session_id' => (string) Str::uuid(),
                        'ip_hash' => hash('sha256', "demo-{$index}-{$viewIndex}"),
                        'referrer' => $viewIndex % 2 === 0 ? 'https://scholar.google.com/' : null,
                        'viewed_at' => now()->subDays($viewIndex),
                    ]);
                }

                $article->update(['view_count' => $seededViewCount]);
            }
        }

        ContactSubmission::factory()->count(12)->create();
        NewsletterSubscriber::factory()->count(30)->create();
    }

    private function ensureDemoSeedingIsAllowed(): void
    {
        if (! config('publication.seeding.demo_content', false)) {
            throw new RuntimeException('Demo content seeding requires the explicit SEED_DEMO_CONTENT=true opt-in.');
        }

        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo content may only be seeded in local or testing environments.');
        }
    }

    private function createDemoUser(): User
    {
        return User::factory()->create([
            'password' => Hash::make(Str::random(48)),
            'remember_token' => null,
        ]);
    }
}
