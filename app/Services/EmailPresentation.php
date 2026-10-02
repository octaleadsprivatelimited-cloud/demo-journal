<?php

namespace App\Services;

use Illuminate\Notifications\Messages\MailMessage;

class EmailPresentation
{
    public static function designs(): array
    {
        return [
            'application' => ['Account applications', 'Account application update', '#087e8b', 'Application → Super Admin review → Decision', 'View application', 'Your account application has an update from the editorial office.'],
            'reset' => ['Account security', 'Choose a new password', '#254bb5', 'Only use this link if you requested a password reset.', 'Reset password', 'We received a request to reset the password for your journal account. Use the secure button below to choose a new password.'],
            'verification' => ['Welcome to the journal', 'Verify your email address', '#087e8b', 'Confirm your address to finish setting up your account.', 'Verify email address', 'Thank you for creating an account. Please confirm your email address before accessing your workspace.'],
            'submission' => ['Submission receipt', 'Your manuscript is with us', '#176b58', 'Received → Editorial checks → Peer review', 'Track submission', 'Dear Dr. Priya Sharma, we have received your manuscript “Technology-assisted approaches to cardiovascular care”. The editorial office will check your submission and contact you about the next step.'],
            'review' => ['Peer review invitation', 'Your expertise is requested', '#6b46a3', 'Review the invitation and declare any competing interests before accepting.', 'View review invitation', 'You have been invited to review “Technology-assisted approaches to cardiovascular care”. Open the assignment to see the manuscript, review deadline and response options.'],
            'review-completed' => ['Editorial update', 'A review is ready', '#526388', 'The editorial team can now consider the reviewer’s feedback.', 'Read review', 'A reviewer has submitted their report for “Technology-assisted approaches to cardiovascular care”. Read the comments and recommendation in your editorial workspace.'],
            'revision' => ['Action required', 'Please revise your manuscript', '#ac6416', 'Read feedback → Revise manuscript → Upload response', 'View revision request', 'The editor has requested revisions to “Technology-assisted approaches to cardiovascular care”. Please address the comments and include a point-by-point response with your revised files.'],
            'acceptance' => ['Editorial decision', 'Your manuscript is accepted', '#16744a', 'Accepted → Production → Author proof', 'View acceptance details', 'Congratulations. Your manuscript “Technology-assisted approaches to cardiovascular care” has been accepted. The production team will contact you about the next steps.'],
            'proof' => ['Production checklist', 'Your proof needs approval', '#a65a27', 'Check author names • Affiliations • Figures • References', 'Review your proof', 'The proof for “Technology-assisted approaches to cardiovascular care” is ready. Review the final layout and submit corrections or approval in your workspace.'],
            'publication' => ['New publication', 'Your research is now published', '#185a79', 'Read your article and share the published link with your colleagues.', 'Read published article', '“Technology-assisted approaches to cardiovascular care” is now available on the journal website. Thank you for contributing your research.'],
            'account' => ['Security notice', 'Your account was updated', '#a33c45', 'Did not make this change? Contact the journal immediately.', 'Open my account', 'Your sign-in details have changed. This notice helps you keep track of changes to your journal account. Your password is never included in email.'],
            'contact' => ['Journal correspondence', 'A message from the editorial office', '#556250', 'Keep this correspondence for your records.', 'Contact the journal', 'Thank you for contacting the journal. The editorial office has an update regarding your enquiry. Replies and additional instructions will appear in this message.'],
            'newsletter' => ['Journal digest', 'Discover the latest research', '#653959', 'Research updates • Journal news • New publications', 'Explore the journal', 'Explore newly published research and updates from our editorial community. Visit the journal to read the latest articles and announcements.'],
        ];
    }

    public static function type(string $text): string
    {
        $text = strtolower($text);
        foreach ([
            'reset' => ['reset password', 'reset your password', 'password reset'],
            'verification' => ['verify', 'verification'],
            'account' => ['account settings', 'account was updated'],
            'publication' => ['published', 'publication'],
            'revision' => ['revision', 'revise'],
            'proof' => ['proof'],
            'acceptance' => ['accepted', 'acceptance'],
            'review-completed' => ['review completed', 'review submitted', 'review has been submitted'],
            'review' => ['review assignment', 'reviewer invitation', 'assigned a manuscript', 'invited to review'],
            'newsletter' => ['newsletter', 'digest'],
            'contact' => ['enquiry', 'contact', 'reply'],
            'submission' => ['submission', 'submitted', 'received'],
        ] as $type => $terms) {
            foreach ($terms as $term) {
                if (str_contains($text, $term)) return $type;
            }
        }
        return 'contact';
    }

    public static function preview(string $type): MailMessage
    {
        $design = self::designs()[$type];
        return (new MailMessage)->subject($design[1])->greeting('Hello,')
            ->markdown('notifications::email', ['templateType' => $type, 'isPreview' => true])
            ->line($design[5])->action($design[4], url('/'))
            ->line($type === 'reset' ? 'This reset link expires in '.config('auth.passwords.users.expire', 60).' minutes. If you did not request this, no action is needed.' : 'Sign in to your workspace for the complete details.');
    }
}
