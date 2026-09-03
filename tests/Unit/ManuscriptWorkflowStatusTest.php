<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ArticleStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManuscriptWorkflowStatusTest extends TestCase
{
    #[Test]
    public function granular_manuscript_transitions_are_validated(): void
    {
        $this->assertTrue(ArticleStatus::InitialCheck->canTransitionTo(ArticleStatus::FormatCheck));
        $this->assertTrue(ArticleStatus::AwaitingEditorDecision->canTransitionTo(ArticleStatus::MajorRevisionRequested));
        $this->assertTrue(ArticleStatus::FinalProofApproved->canTransitionTo(ArticleStatus::MetadataCheck));
        $this->assertFalse(ArticleStatus::Draft->canTransitionTo(ArticleStatus::Published));
        $this->assertFalse(ArticleStatus::UnderReview->canTransitionTo(ArticleStatus::Published));
    }
}
