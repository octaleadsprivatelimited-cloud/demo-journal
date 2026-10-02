<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DemoJournalSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('publication.seeding.demo_content')) {
            throw new RuntimeException('Demo journals require a local/testing environment and SEED_DEMO_CONTENT=true.');
        }

        DB::transaction(function (): void {
            $creator = User::firstOrCreate(['email' => 'demo-editor@example.invalid'], [
                'name' => 'Demo Editorial Desk', 'password' => Str::random(64), 'is_active' => false,
            ]);
            $author = Author::firstOrCreate(['slug' => 'demo-editorial-desk'], [
                'user_id' => $creator->id, 'name' => 'Demo Editorial Desk',
                'email' => 'demo-editor@example.invalid', 'is_active' => true, 'is_verified' => false,
                'biography' => 'Fictional editorial profile for demonstration articles. These articles are illustrative perspectives, not peer-reviewed research.',
            ]);

            foreach ($this->articles() as $index => [$title, $categoryName, $abstract, $sections]) {
                $slug = Str::slug($title);
                // Preserve administrator edits and deletions when this seeder is run again.
                if (Article::withTrashed()->where('article_number', 'DEMO-'.($index + 1))->exists()) {
                    continue;
                }
                $category = Category::firstOrCreate(['slug' => Str::slug($categoryName)], [
                    'name' => $categoryName, 'description' => 'Perspectives on '.strtolower($categoryName).'.',
                    'is_active' => true, 'sort_order' => $index + 1,
                ]);
                $content = '<p><strong>Demo article:</strong> Illustrative editorial content for this website. This is not a published research study.</p>';
                foreach ($sections as $heading => $paragraph) {
                    $content .= '<h2>'.e($heading).'</h2><p>'.e($paragraph).'</p>';
                }
                $article = Article::create([
                    'title' => $title, 'slug' => $slug, 'category_id' => $category->id,
                    'created_by_id' => $creator->id, 'abstract' => $abstract, 'excerpt' => $abstract,
                    'content' => $content, 'publication_type' => 'analysis',
                    'article_number' => 'DEMO-'.($index + 1), 'keywords' => [$categoryName, 'Demo', 'Sustainability'],
                    'status' => ArticleStatus::Published, 'published_at' => now()->subDays($index + 1),
                    'is_featured' => true, 'is_homepage_latest' => true, 'is_trending' => false,
                    'comments_enabled' => false, 'pdf_download_enabled' => false,
                    'publication_notice' => 'none',
                ]);
                $article->authors()->attach($author->id, ['is_corresponding' => true, 'sort_order' => 0]);
            }
        });
    }

    private function articles(): array
    {
        return [
            [
                'Edge AI in the Field: Designing Useful Tools for Small Farms', 'Technology & Society',
                'A practical perspective on bringing crop-monitoring tools closer to the field, with attention to connectivity, maintenance, and farmer control over data.',
                [
                    'The design problem' => 'A crop-monitoring tool must fit the working day of the person using it. A photograph taken in uneven light, a shared phone, or an unreliable mobile connection can expose weaknesses that never appear in a laboratory demonstration. A useful prototype begins with a specific task, such as recording visible leaf damage for later review, rather than promising to automate every decision on a farm.',
                    'An offline-first workflow' => 'In an illustrative design, a phone stores observations locally and offers a preliminary classification. The interface shows uncertainty and allows the farmer to mark an answer as incorrect. When a connection becomes available, selected records can be shared with an adviser. The original image, crop stage, and field notes remain available so that a person can interpret the suggestion in context.',
                    'What to evaluate' => 'A pilot should examine missed problems, false alerts, battery use, and the time required to record an observation. Evaluation should include different devices, seasons, and field conditions. Farmers should be able to export their records and understand who can access them. Model accuracy alone does not establish whether a tool is useful or affordable.',
                    'Editorial conclusion' => 'The strongest starting point is a narrow, understandable service with a clear hand-off to a human adviser. Maintenance, training, and feedback deserve a place in the project budget alongside software development.',
                ],
            ],
            [
                'Smarter Irrigation: Building a Soil-Moisture Monitoring Routine', 'Farming & Agriculture',
                'How a small farm might combine soil observations, simple sensors, and irrigation records into a repeatable water-management routine.',
                [
                    'Start with the field' => 'An irrigation plan begins with an understanding of the field rather than a device purchase. Soil texture, slope, crop stage, and uneven water distribution can all matter when interpreting a reading. This demonstration outlines a monitoring routine; it does not prescribe irrigation thresholds or claim measured water savings.',
                    'A manageable pilot' => 'A grower could begin with one clearly mapped management zone and record sensor location, installation depth, and maintenance dates. Readings should be compared with direct observations of the soil and crop. An isolated value should prompt a check for placement or equipment problems before it becomes the basis for an irrigation decision.',
                    'Record decisions as well as readings' => 'A useful log captures when irrigation started, how long it ran, recent rainfall, and the reason for the decision. Keeping these notes together makes it easier to investigate an unexpected result. The routine must also identify who checks the equipment and what happens when a sensor stops reporting.',
                    'Learning over a season' => 'A seasonal review can compare management zones and identify questions for a qualified local adviser. Any claim about savings should account for rainfall, crop differences, and the method used to measure water. A clear record is more valuable than an impressive dashboard without context.',
                ],
            ],
            [
                'From Crop Residues to Soil Care: A Practical Composting Perspective', 'Soil & Environment',
                'An editorial look at planning a farm composting workflow around available materials, quality checks, and the realities of day-to-day operations.',
                [
                    'Define the purpose' => 'Before creating a composting area, a farm needs to decide what materials it expects to handle and how the finished material will be used. A seasonal inventory can reveal whether residues arrive steadily or in short bursts. The plan should also account for labour, access, drainage, and nearby activities.',
                    'Keep the process observable' => 'An illustrative operating record lists incoming materials, pile formation dates, observations, and turning events. Monitoring should follow an appropriate local protocol. Unfamiliar or potentially contaminated inputs need assessment rather than automatic acceptance simply because they are organic.',
                    'Quality before application' => 'Finished material should be assessed for its intended use. Appearance alone is not a substitute for relevant quality checks or advice about application. A small trial area and documented observations offer a more cautious learning process than immediate use across the entire farm.',
                    'Make the work repeatable' => 'A sustainable workflow assigns responsibility for records and routine checks. The objective is a process the farm can maintain through its busiest months. This demo presents planning considerations and does not report a composting experiment or quantified soil benefits.',
                ],
            ],
            [
                'Solar-Powered Cold Rooms: Planning Around the Harvest', 'Energy & Infrastructure',
                'A planning framework for a shared produce cold room, connecting energy supply with harvest schedules, operating costs, and reliable maintenance.',
                [
                    'Begin with demand' => 'A shared cold room needs an operating plan grounded in the produce it will receive. Harvest timing, delivery volumes, storage duration, and product requirements determine the questions that an engineering assessment must answer. Solar generation is only one part of that assessment.',
                    'Design the service' => 'A cooperative might establish booking windows, intake records, and clear responsibility for handling produce. The service should explain how users are charged and who responds when temperatures move outside the chosen operating range. Different products may require separate handling arrangements.',
                    'Account for interruptions' => 'A technical review should consider periods of low generation, equipment faults, and delays in obtaining replacement parts. Monitoring is useful only when someone receives the alert and can act. Training, preventive maintenance, and an appropriate contingency plan belong in the operating budget.',
                    'Evaluate the whole model' => 'An illustrative feasibility review would test several utilisation and cost scenarios. It should distinguish projected benefits from measured outcomes. This demo does not provide investment estimates or claim that a particular system will pay for itself.',
                ],
            ],
            [
                'Traceable Harvests: Simple Digital Records for Food Cooperatives', 'Food Systems & Innovation',
                'A practical approach to linking harvest batches, handling records, and buyer deliveries without making record-keeping harder for small producers.',
                [
                    'A shared record' => 'Traceability starts with consistent identifiers and clear responsibility. A cooperative can define a batch at intake and link it to the supplier, date, product, and handling record. The format should be understandable to the people entering information and the people who later need to retrieve it.',
                    'Design for ordinary mistakes' => 'An effective workflow allows corrections while preserving the history of a record. Duplicate labels, missing entries, and split batches need explicit handling rules. A printed label or code can help retrieve information, but it does not establish that the underlying entry is accurate.',
                    'Keep access proportionate' => 'Producers, warehouse staff, and buyers may need different views of the same batch. Personal details should not appear on a public lookup page merely because they are stored internally. A simple export process helps a cooperative retain access to its information if it changes software.',
                    'Test retrieval, not just entry' => 'A demonstration exercise can ask staff to follow a delivery back to its intake records and identify related batches. The exercise reveals gaps in procedures before a real incident occurs. This article describes a proposed workflow, not a certified food-safety system or completed field study.',
                ],
            ],
        ];
    }
}
