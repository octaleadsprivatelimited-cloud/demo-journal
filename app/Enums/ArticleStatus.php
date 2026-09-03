<?php

declare(strict_types=1);

namespace App\Enums;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InitialCheck = 'initial_check';
    case ReturnedForSubmissionCorrection = 'returned_for_submission_correction';
    case FormatCheck = 'format_check';
    case SimilarityCheck = 'similarity_check';
    case EditorialScreening = 'editorial_screening';
    case AwaitingReviewerAssignment = 'awaiting_reviewer_assignment';
    case ReviewerInvited = 'reviewer_invited';
    case UnderReview = 'under_review';
    case ReviewsCompleted = 'reviews_completed';
    case AwaitingEditorDecision = 'awaiting_editor_decision';
    case MinorRevisionRequested = 'minor_revision_requested';
    case MajorRevisionRequested = 'major_revision_requested';
    case RevisionSubmitted = 'revision_submitted';
    case UnderReReview = 'under_re_review';
    case RevisionRequired = 'revision_required';
    case Approved = 'approved';
    case Accepted = 'accepted';
    case Copyediting = 'copyediting';
    case ProofPreparation = 'proof_preparation';
    case ProofSentToAuthor = 'proof_sent_to_author';
    case AuthorCorrectionsSubmitted = 'author_corrections_submitted';
    case FinalProofReview = 'final_proof_review';
    case FinalProofApproved = 'final_proof_approved';
    case MetadataCheck = 'metadata_check';
    case DoiPreparation = 'doi_preparation';
    case DoiRegistrationPending = 'doi_registration_pending';
    case DoiAssigned = 'doi_assigned';
    case ReadyForPublication = 'ready_for_publication';
    case Rejected = 'rejected';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted],
            // Legacy direct transitions are retained for existing editorial controls;
            // new workflow screens use the more granular path below.
            self::Submitted => [self::InitialCheck, self::UnderReview, self::RevisionRequired, self::Approved, self::Rejected],
            self::InitialCheck => [self::ReturnedForSubmissionCorrection, self::FormatCheck],
            self::ReturnedForSubmissionCorrection => [self::Submitted],
            self::FormatCheck => [self::SimilarityCheck],
            self::SimilarityCheck => [self::EditorialScreening],
            self::EditorialScreening => [self::ReturnedForSubmissionCorrection, self::AwaitingReviewerAssignment, self::Rejected],
            self::AwaitingReviewerAssignment => [self::ReviewerInvited, self::UnderReview],
            self::ReviewerInvited => [self::UnderReview],
            self::UnderReview => [self::ReviewsCompleted, self::AwaitingEditorDecision, self::RevisionRequired, self::Approved, self::Rejected],
            self::UnderReReview => [self::ReviewsCompleted, self::AwaitingEditorDecision],
            self::ReviewsCompleted => [self::AwaitingEditorDecision],
            self::AwaitingEditorDecision => [self::MinorRevisionRequested, self::MajorRevisionRequested, self::Rejected, self::Accepted],
            self::MinorRevisionRequested, self::MajorRevisionRequested => [self::RevisionSubmitted, self::Rejected],
            self::RevisionRequired => [self::RevisionSubmitted, self::Submitted, self::Rejected],
            self::RevisionSubmitted => [self::UnderReReview, self::AwaitingEditorDecision],
            self::Accepted, self::Approved => [self::Copyediting, self::Scheduled, self::Published, self::Archived],
            self::Copyediting => [self::ProofPreparation],
            self::ProofPreparation => [self::ProofSentToAuthor],
            self::ProofSentToAuthor => [self::AuthorCorrectionsSubmitted],
            self::AuthorCorrectionsSubmitted => [self::FinalProofReview],
            self::FinalProofReview => [self::FinalProofApproved],
            self::FinalProofApproved => [self::MetadataCheck],
            self::MetadataCheck => [self::DoiPreparation],
            self::DoiPreparation => [self::DoiRegistrationPending, self::ReadyForPublication],
            self::DoiRegistrationPending => [self::DoiAssigned],
            self::DoiAssigned => [self::ReadyForPublication],
            self::ReadyForPublication => [self::Published],
            self::Scheduled => [self::Published, self::Approved, self::Archived],
            self::Published => [self::Archived],
            self::Rejected => [self::Draft, self::Submitted, self::Archived],
            self::Archived => [self::Draft, self::Published],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::UnderReview => 'Under Review',
            self::RevisionRequired => 'Revision Required',
            default => str($this->value)->replace('_', ' ')->title()->toString(),
        };
    }
}
