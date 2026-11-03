<?php

declare(strict_types=1);

// No application bootstrap, database, network or real credentials. Supply a
// Composer autoloader to exercise the installed dotenv/encryption libraries.
require dirname(__DIR__, 2).'/scripts/hostinger-configure-sjnr-mail.php';
$autoload = $argv[1] ?? dirname(__DIR__, 2).'/vendor/autoload.php';
if (! is_file($autoload)) {
    throw new RuntimeException('Supply the Composer autoloader for isolated deployment checks.');
}
require $autoload;

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$rejects = static function (callable $action, string $message) use ($check): void {
    try {
        $action();
    } catch (RuntimeException) {
        $check(true, $message);

        return;
    }
    $check(false, $message);
};
$source = "# preserve notes\nAPP_KEY=unrelated\nDB_PASSWORD='private-untouched'\nMAIL_PASSWORD=old\n";
$passwords = ['ordinary-value', 'a\'b"c#d$e\\f', '${APP_KEY}-literal', ' first and last ', '测试邮箱-secret'];
foreach ($passwords as $password) {
    $updated = SjnrMailSetup::replaceEnvironmentValues($source, ['MAIL_PASSWORD' => $password]);
    $parsed = \Dotenv\Dotenv::parse($updated);
    $check($parsed['MAIL_PASSWORD'] === $password, 'Special characters must round-trip without interpolation.');
    $check(str_contains($updated, "APP_KEY=unrelated\nDB_PASSWORD='private-untouched'\n"), 'Unrelated secrets must remain byte-identical.');
    $check(SjnrMailSetup::replaceEnvironmentValues($updated, ['MAIL_PASSWORD' => $password]) === $updated,
        'Repeated configuration must be idempotent.');
}
$updated = SjnrMailSetup::replaceEnvironmentValues("export MAIL_PASSWORD =old\r\nOTHER=retained\r\n", [
    'MAIL_PASSWORD' => 'new # quoted', 'MAIL_HOST' => 'smtp.hostinger.com',
]);
$check(str_contains($updated, "\r\nOTHER=retained\r\n"), 'CRLF line endings must survive.');
$check(\Dotenv\Dotenv::parse($updated)['MAIL_HOST'] === 'smtp.hostinger.com', 'Missing allowed mail values must be added.');
$rejects(static fn () => SjnrMailSetup::replaceEnvironmentValues("MAIL_PASSWORD=one\nexport MAIL_PASSWORD=two\n", ['MAIL_PASSWORD' => 'new']),
    'Duplicate mail keys require review.');
$rejects(static fn () => SjnrMailSetup::replaceEnvironmentValues($source, ['DB_PASSWORD' => 'forbidden']),
    'The helper must never edit database or other unrelated keys.');
$rejects(static fn () => SjnrMailSetup::replaceEnvironmentValues($source, ['MAIL_PASSWORD' => "bad\nAPP_DEBUG=true"]),
    'Control characters cannot create another environment entry.');

$now = 1_793_685_000;
$payload = ['purpose' => 'sjnr-smtp-setup-v1', 'domain' => 'https://sjnrjournal.com',
    'username' => 'notifications@sjnrjournal.com', 'password' => 'fictitious-password', 'created_at' => gmdate('c', $now)];
SjnrMailSetup::validatePayload($payload, $now);
$check(true, 'A valid purpose-bound recent payload must be accepted.');
foreach ([
    ['domain' => 'https://example.org'], ['username' => 'other@sjnrjournal.com'], ['purpose' => 'other-action'],
    ['password' => ''], ['password' => "bad\r\nheader"], ['created_at' => gmdate('c', $now - 86401)],
    ['created_at' => gmdate('c', $now + 301)], ['created_at' => 'invalid'], ['extra' => 'unapproved'],
] as $changes) {
    $rejects(static fn () => SjnrMailSetup::validatePayload(array_replace($payload, $changes), $now),
        'Wrong identity, malformed or expired input must be refused.');
}

$key = random_bytes(32);
$encrypter = new \Illuminate\Encryption\Encrypter($key, 'AES-256-CBC');
$ciphertext = $encrypter->encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
$check(! str_contains($ciphertext, $payload['password']), 'The staged envelope must contain only ciphertext.');
$check(json_decode($encrypter->decryptString($ciphertext), true, 16, JSON_THROW_ON_ERROR) === $payload,
    'Authenticated encryption must preserve the complete payload.');
try {
    (new \Illuminate\Encryption\Encrypter(random_bytes(32), 'AES-256-CBC'))->decryptString($ciphertext);
    $check(false, 'Another application key must not decrypt the envelope.');
} catch (\Illuminate\Contracts\Encryption\DecryptException) {
    $check(true, 'Another application key cannot decrypt the envelope.');
}
$hash = hash('sha256', $ciphertext);
$check(SjnrMailSetup::shouldSendTest(null, $hash), 'An explicit first test may be sent.');
foreach (['sending', 'sent', 'uncertain'] as $state) {
    $check(! SjnrMailSetup::shouldSendTest(['payload_hash' => $hash, 'state' => $state], $hash),
        'Sent or uncertain tests must never be retried automatically.');
}
$rejects(static fn () => SjnrMailSetup::shouldSendTest(['payload_hash' => 'different', 'state' => 'sent'], $hash),
    'A mismatched test receipt requires review.');
$rejects(static fn () => SjnrMailSetup::run([]), 'The local checkout must refuse before autoload or network access.');

echo json_encode(['ok' => true, 'fixture_checks' => $checks], JSON_THROW_ON_ERROR)."\n";
