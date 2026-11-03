<?php

declare(strict_types=1);

// CLI only. Temporary public staging contains authenticated ciphertext, never
// plaintext credentials, and must additionally deny every HTTP request.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

final class SjnrMailSetup
{
    private const BASE = '/home/u709299470/domains/sjnrjournal.com/journal_app';

    private const PUBLIC_PATH = '/home/u709299470/domains/sjnrjournal.com/public_html';

    private const URL = 'https://sjnrjournal.com';

    private const MAILBOX = 'notifications@sjnrjournal.com';

    private const NAME = 'Singapore Journal of Nursing Research';

    public static string $stage = 'target_guard';

    /** @param list<string> $arguments */
    public static function run(array $arguments): array
    {
        $staging = self::PUBLIC_PATH.'/sjnr-mail-setup';
        $privateLocation = realpath(__DIR__) === self::BASE.'/scripts'
            && realpath(dirname(__DIR__)) === self::BASE;
        $stagedLocation = realpath(__DIR__) === $staging
            && realpath(__DIR__.'/../../journal_app') === self::BASE;
        if ((! $privateLocation && ! $stagedLocation) || realpath($staging) !== $staging
            || is_link($staging.'/.htaccess') || ! is_file($staging.'/.htaccess')
            || trim(file_get_contents($staging.'/.htaccess')) !== 'Require all denied'
            || array_diff($arguments, ['--send-test']) !== []) {
            throw new RuntimeException('The guarded staging location is unavailable.');
        }
        $environment = self::BASE.'/.env';
        $payloadPath = $staging.'/payload.dat';
        foreach ([$environment, $payloadPath] as $file) {
            if (is_link($file) || ! is_file($file) || ! is_readable($file) || filesize($file) > 200_000) {
                throw new RuntimeException('A required deployment input is unavailable.');
            }
        }
        $pause = self::BASE.'/storage/framework/mail-paused';
        if (is_link($pause) || ! is_file($pause)) {
            throw new RuntimeException('Mail must remain paused during configuration.');
        }
        $originalEnvironment = file_get_contents($environment);
        $ciphertext = trim(file_get_contents($payloadPath));
        $payloadHash = hash('sha256', $ciphertext);
        require self::BASE.'/vendor/autoload.php';

        self::$stage = 'environment_guard';
        $values = \Dotenv\Dotenv::parse($originalEnvironment);
        if (($values['APP_ENV'] ?? '') !== 'production' || ($values['JOURNAL_PROFILE'] ?? '') !== 'sjnr'
            || ($values['DB_CONNECTION'] ?? '') !== 'mysql'
            || ($values['DB_DATABASE'] ?? '') !== 'u709299470_sjnr_preview'
            || ! in_array($values['DB_HOST'] ?? '', ['localhost', '127.0.0.1'], true)
            || ($values['DB_URL'] ?? '') !== '' || ($values['MAIL_URL'] ?? '') !== ''
            || rtrim($values['APP_URL'] ?? '', '/') !== self::URL) {
            throw new RuntimeException('The environment does not identify the intended SJNR installation.');
        }
        self::$stage = 'resolved_configuration_guard';
        $app = require self::BASE.'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $connection = \Illuminate\Support\Facades\DB::connection();
        $cachedConfiguration = $app->getCachedConfigPath();
        if (! $app->isProduction() || config('journal.profile') !== 'sjnr'
            || config('database.default') !== 'mysql' || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'u709299470_sjnr_preview'
            || ! in_array($connection->getConfig('host'), ['localhost', '127.0.0.1'], true)
            || rtrim((string) config('app.url'), '/') !== self::URL
            || config('app.key') !== ($values['APP_KEY'] ?? null)
            || $cachedConfiguration !== self::BASE.'/bootstrap/cache/config.php' || is_link($cachedConfiguration)
            || realpath(self::BASE.'/storage/app/private') !== self::BASE.'/storage/app/private') {
            throw new RuntimeException('The resolved configuration targets a different installation.');
        }

        self::$stage = 'payload_validation';
        $payload = json_decode($app->make('encrypter')->decryptString($ciphertext), true, 16, JSON_THROW_ON_ERROR);
        self::validatePayload($payload, time());
        $updates = [
            'MAIL_MAILER' => 'smtp', 'MAIL_SCHEME' => 'smtps', 'MAIL_HOST' => 'smtp.hostinger.com', 'MAIL_PORT' => '465',
            'MAIL_USERNAME' => self::MAILBOX, 'MAIL_PASSWORD' => $payload['password'],
            'MAIL_FROM_ADDRESS' => self::MAILBOX, 'MAIL_FROM_NAME' => self::NAME,
            'MAIL_TIMEOUT' => '15', 'MAIL_EHLO_DOMAIN' => 'sjnrjournal.com',
            'CONTACT_NOTIFICATION_EMAIL' => self::MAILBOX, 'ACCOUNT_NOTIFICATION_EMAIL' => self::MAILBOX,
        ];
        $newEnvironment = self::replaceEnvironmentValues($originalEnvironment, $updates);
        $parsed = \Dotenv\Dotenv::parse($newEnvironment);
        foreach ($updates as $key => $value) {
            if (($parsed[$key] ?? null) !== $value) {
                throw new RuntimeException('The mail environment values did not round-trip safely.');
            }
        }

        $deployment = self::BASE.'/storage/app/private/deployment';
        if (realpath($deployment) !== $deployment) {
            throw new RuntimeException('Private deployment storage is unavailable.');
        }
        $lock = fopen($deployment.'/sjnr-mail-setup.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another mail setup is already running.');
        }
        chmod($deployment.'/sjnr-mail-setup.lock', 0600);
        if (file_get_contents($environment) !== $originalEnvironment || trim(file_get_contents($payloadPath)) !== $ciphertext) {
            throw new RuntimeException('Deployment inputs changed during preflight.');
        }
        $testMarker = $deployment.'/sjnr-mail-test-'.substr($payloadHash, 0, 24).'.json';
        $appliedMarker = $deployment.'/sjnr-mail-applied-'.$payloadHash.'.json';
        if (is_link($testMarker) || is_link($appliedMarker)) {
            throw new RuntimeException('The private test marker is invalid.');
        }
        if (is_file($appliedMarker) && $newEnvironment !== $originalEnvironment) {
            // A consumed envelope cannot undo a later administrator edit. A
            // deliberate new setup must use a freshly encrypted input envelope.
            throw new RuntimeException('The applied deployment payload cannot replace later configuration.');
        }
        $test = is_file($testMarker) ? json_decode(file_get_contents($testMarker), true, 16, JSON_THROW_ON_ERROR) : null;

        self::$stage = 'smtp_authentication';
        // Track actual successful AUTH. A server accepting EHLO without offering
        // AUTH must not be mistaken for valid mailbox credentials.
        $authenticator = new class extends \Symfony\Component\Mailer\Transport\Smtp\Auth\LoginAuthenticator
        {
            public bool $authenticated = false;

            public function authenticate(\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport $client): void
            {
                parent::authenticate($client);
                $this->authenticated = true;
            }
        };
        $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport('smtp.hostinger.com', 465, true);
        $transport->setAuthenticators([$authenticator]);
        $transport->setUsername(self::MAILBOX)->setPassword($payload['password'])->setLocalDomain('sjnrjournal.com');
        $transport->getStream()->setTimeout(15);
        $transport->getStream()->setStreamOptions(['ssl' => [
            'verify_peer' => true, 'verify_peer_name' => true,
            'peer_name' => 'smtp.hostinger.com', 'allow_self_signed' => false,
        ]]);
        $transport->start();
        if (! $authenticator->authenticated || ! $transport->getStream()->isTls()) {
            throw new RuntimeException('Verified mailbox authentication did not complete.');
        }
        if (file_get_contents($environment) !== $originalEnvironment
            || trim(file_get_contents($payloadPath)) !== $ciphertext || ! is_file($pause) || is_link($pause)) {
            throw new RuntimeException('Deployment inputs changed during authentication.');
        }

        self::$stage = 'private_backup';
        $backup = $deployment.'/before-sjnr-mail-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
        if (! mkdir($backup, 0700)) {
            throw new RuntimeException('The private rollback directory could not be created.');
        }
        self::writePrivate($backup.'/.env', $originalEnvironment);
        $hadCachedConfiguration = is_file($cachedConfiguration);
        if ($hadCachedConfiguration) {
            self::writePrivate($backup.'/config.php', file_get_contents($cachedConfiguration));
        }

        $environmentReplaced = false;
        try {
            self::$stage = 'environment_update';
            if (file_get_contents($environment) !== $originalEnvironment) {
                throw new RuntimeException('The environment changed before replacement.');
            }
            if ($newEnvironment !== $originalEnvironment) {
                self::replaceAtomically($environment, $newEnvironment);
                $environmentReplaced = true;
            }
            if (\Illuminate\Support\Facades\Artisan::call('config:clear') !== 0 || is_file($cachedConfiguration)) {
                throw new RuntimeException('The configuration cache could not be cleared.');
            }
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp' => ['transport' => 'smtp', 'scheme' => 'smtps', 'url' => null,
                    'host' => 'smtp.hostinger.com', 'port' => 465, 'username' => self::MAILBOX,
                    'password' => $payload['password'], 'timeout' => 15, 'local_domain' => 'sjnrjournal.com'],
                'mail.from' => ['address' => self::MAILBOX, 'name' => self::NAME],
                'publication.contact_email' => self::MAILBOX,
                'publication.account_notification_email' => self::MAILBOX,
            ]);
            $persisted = \Dotenv\Dotenv::parse(file_get_contents($environment));
            foreach ($updates as $key => $value) {
                if (($persisted[$key] ?? null) !== $value) {
                    throw new RuntimeException('A configured mail value did not persist.');
                }
            }

            if (in_array('--send-test', $arguments, true) && self::shouldSendTest($test, $payloadHash)) {
                self::$stage = 'self_test';
                $test = ['payload_hash' => $payloadHash, 'state' => 'sending',
                    'test_id' => bin2hex(random_bytes(8)), 'attempted_at' => gmdate('c')];
                // Persist intent before SMTP. A crash or ambiguous SMTP result
                // requires review rather than automatically sending a duplicate.
                self::writePrivate($testMarker, json_encode($test, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
                try {
                    $manager = $app->make('mail.manager');
                    $manager->purge('smtp');
                    $mailer = $manager->mailer('smtp');
                    $mailer->setSymfonyTransport($transport);
                    $sent = $mailer->raw(
                        "This is a delivery check for Singapore Journal of Nursing Research website notifications.\n"
                        ."Reference: ".$test['test_id']."\nNo manuscript or account data is included.\n",
                        static function ($message) use ($test): void {
                            $message->from(self::MAILBOX, self::NAME)->to(self::MAILBOX)
                                ->subject('SJNR notification delivery test '.$test['test_id']);
                        },
                    );
                    if (! $sent) {
                        throw new RuntimeException('The test message was not accepted for sending.');
                    }
                    $test['state'] = 'sent';
                    $test['accepted_at'] = gmdate('c');
                    self::writePrivate($testMarker, json_encode($test, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
                } catch (Throwable $exception) {
                    $test['state'] = 'uncertain';
                    self::writePrivate($testMarker, json_encode($test, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
                    throw $exception;
                }
            }
            self::$stage = 'verification';
            if (! is_file($pause) || is_link($pause)) {
                throw new RuntimeException('Mail pause changed during configuration.');
            }
            $summary = [
                'ok' => true, 'completed_at' => gmdate('c'), 'domain' => self::URL,
                'smtp_authenticated' => true, 'tls_verified' => true, 'mailbox' => self::MAILBOX,
                'password_present' => true, 'mail_paused' => true,
                'test_state' => $test['state'] ?? 'not_requested', 'test_id' => $test['test_id'] ?? null,
                'test_recipient' => self::MAILBOX, 'environment_changed' => $newEnvironment !== $originalEnvironment,
                'queued_mail_jobs' => $connection->table('jobs')->where('queue', 'mail')->count(),
                'queued_default_jobs' => $connection->table('jobs')->where('queue', 'default')->count(),
                'failed_jobs' => $connection->table('failed_jobs')->count(), 'rollback_directory' => $backup,
            ];
            self::writePrivate($appliedMarker, json_encode(['payload_hash' => $payloadHash,
                'applied_at' => gmdate('c')], JSON_THROW_ON_ERROR)."\n");
            self::writePrivate($deployment.'/sjnr-mail-configured.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");

            return $summary;
        } catch (Throwable $exception) {
            $currentEnvironment = file_get_contents($environment);
            if ($environmentReplaced && $currentEnvironment === $newEnvironment) {
                self::replaceAtomically($environment, $originalEnvironment);
                $currentEnvironment = $originalEnvironment;
            }
            // Do not overwrite an administrator's concurrent configuration edit.
            if ($currentEnvironment === $originalEnvironment && $hadCachedConfiguration && ! is_file($cachedConfiguration)) {
                self::replaceAtomically($cachedConfiguration, file_get_contents($backup.'/config.php'));
            }
            throw $exception;
        } finally {
            $transport->stop();
        }
    }

    public static function validatePayload(mixed $payload, int $now): void
    {
        if (! is_array($payload) || count($payload) !== 5
            || ($payload['purpose'] ?? null) !== 'sjnr-smtp-setup-v1'
            || ($payload['domain'] ?? null) !== self::URL || ($payload['username'] ?? null) !== self::MAILBOX
            || ! is_string($payload['password'] ?? null) || trim($payload['password']) === ''
            || strlen($payload['password']) > 2048 || preg_match('/[\x00-\x1f\x7f]/', $payload['password'])
            || ! is_string($payload['created_at'] ?? null)) {
            throw new RuntimeException('The authenticated deployment payload is invalid.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $payload['created_at']);
        $createdAt = $date ? $date->getTimestamp() : false;
        if ($createdAt === false || $date->format('Y-m-d\TH:i:sP') !== $payload['created_at']
            || $createdAt < $now - 86400 || $createdAt > $now + 300) {
            throw new RuntimeException('The authenticated deployment payload has expired.');
        }
    }

    public static function shouldSendTest(?array $marker, string $payloadHash): bool
    {
        if ($marker === null) {
            return true;
        }
        if (($marker['payload_hash'] ?? null) !== $payloadHash
            || ! in_array($marker['state'] ?? null, ['sending', 'sent', 'uncertain'], true)) {
            throw new RuntimeException('The existing delivery test requires review.');
        }

        return false;
    }

    /** @param array<string,string> $updates */
    public static function replaceEnvironmentValues(string $content, array $updates): string
    {
        $allowed = ['MAIL_MAILER', 'MAIL_SCHEME', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD',
            'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME', 'MAIL_TIMEOUT', 'MAIL_EHLO_DOMAIN',
            'CONTACT_NOTIFICATION_EMAIL', 'ACCOUNT_NOTIFICATION_EMAIL'];
        $lines = explode("\n", $content);
        foreach ($updates as $key => $value) {
            if (! in_array($key, $allowed, true) || ! is_string($value) || preg_match('/[\x00-\x1f\x7f]/', $value)) {
                throw new RuntimeException('An environment replacement is invalid.');
            }
            $quoted = '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
            $matches = [];
            foreach ($lines as $index => $line) {
                if (preg_match('/\A[ \t]*(?:export[ \t]+)?'.preg_quote($key, '/').'[ \t]*=/', $line)) {
                    $matches[] = $index;
                }
            }
            if (count($matches) > 1) {
                throw new RuntimeException('Duplicate mail environment entries require review.');
            }
            if ($matches !== []) {
                $index = $matches[0];
                $lines[$index] = $key.'='.$quoted.(str_ends_with($lines[$index], "\r") ? "\r" : '');
            } else {
                $eol = str_contains($content, "\r\n") ? "\r" : '';
                if (end($lines) === '') {
                    array_pop($lines);
                }
                $lines[] = $key.'='.$quoted.$eol;
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    private static function writePrivate(string $path, string $content): void
    {
        if (is_link($path) || file_put_contents($path, $content, LOCK_EX) !== strlen($content) || ! chmod($path, 0600)) {
            throw new RuntimeException('A private deployment file could not be saved.');
        }
    }

    private static function replaceAtomically(string $path, string $content): void
    {
        $temporary = tempnam(dirname($path), '.sjnr-mail-');
        if (! $temporary) {
            throw new RuntimeException('A deployment file could not be prepared.');
        }
        try {
            self::writePrivate($temporary, $content);
            if (! rename($temporary, $path)) {
                throw new RuntimeException('A deployment file could not be replaced.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        echo json_encode(SjnrMailSetup::run(array_slice($argv, 1)), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
    } catch (Throwable) {
        // SMTP/PDO/dotenv exception text can disclose credentials. Do not print it.
        echo json_encode(['ok' => false, 'stage' => SjnrMailSetup::$stage,
            'error' => 'The guarded SJNR mail setup did not complete.'], JSON_THROW_ON_ERROR)."\n";
        exit(1);
    }
}
