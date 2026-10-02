<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

final class CheckMailConfiguration extends Command
{
    protected $signature = 'mail:check {--authenticate : Check the SMTP connection and authentication without sending an email}';

    protected $description = 'Check journal mail configuration without sending email or displaying credentials';

    public function handle(): int
    {
        $mailer = (string) config('mail.default');
        $this->line('Mailer: '.$mailer);
        $this->line('Sender: '.config('mail.from.address'));
        $this->line('Form alerts: '.config('publication.contact_email'));
        $this->line('Application alerts: '.config('publication.account_notification_email'));

        if ($mailer !== 'smtp') {
            $this->error('SMTP delivery is not enabled. Log and array mailers do not deliver to inboxes.');

            return self::FAILURE;
        }
        foreach (['mail.from.address', 'publication.contact_email', 'publication.account_notification_email'] as $key) {
            if (! filter_var(config($key), FILTER_VALIDATE_EMAIL)) {
                $this->error('Missing or invalid email setting: '.$key);

                return self::FAILURE;
            }
        }
        if (config('app.env') === 'production' && (blank(config('mail.mailers.smtp.username')) || blank(config('mail.mailers.smtp.password')))) {
            $this->error('SMTP credentials are missing. Add them to the private server environment.');

            return self::FAILURE;
        }
        if (! $this->option('authenticate')) {
            $this->info('SMTP settings are present. Connection and inbox delivery have not been verified.');

            return self::SUCCESS;
        }

        $transport = null;
        try {
            $transport = Mail::mailer('smtp')->getSymfonyTransport();
            if (! $transport instanceof SmtpTransport) {
                $this->error('The configured transport is not SMTP.');

                return self::FAILURE;
            }
            $transport->start();
            $this->info('SMTP connection and authentication succeeded. No email was sent.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            // SMTP exceptions can include credentials; never print their messages.
            $this->error('SMTP check failed ('.class_basename($exception).'). Check the mailbox credentials, TLS settings and outbound SMTP access.');

            return self::FAILURE;
        } finally {
            if ($transport instanceof SmtpTransport) {
                try {
                    $transport->stop();
                } catch (Throwable) {
                    // A failed connection may already be closed.
                }
            }
        }
    }
}
