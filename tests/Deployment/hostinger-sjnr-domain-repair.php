<?php

declare(strict_types=1);

// Standalone fixture checks: no Laravel bootstrap, database or local server.
require dirname(__DIR__, 2).'/scripts/hostinger-repair-sjnr-domain.php';

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$source = "# retain deployment notes\nAPP_URL=old\nMAIL_PASSWORD='private-$\\value#unchanged'\nDB_PASSWORD=unchanged\n";
$repaired = SjnrDomainRepair::replaceEnvironmentValues($source, [
    'APP_URL' => 'https://sjnrjournal.com',
    'PUBLIC_DISK_ROOT' => '/home/example/public_html/storage',
]);
$check(str_contains($repaired, "APP_URL=https://sjnrjournal.com\n"), 'The requested URL must change.');
$check(str_contains($repaired, "MAIL_PASSWORD='private-$\\value#unchanged'\nDB_PASSWORD=unchanged\n"), 'Unrelated secrets must be byte-preserved.');
$check(str_contains($repaired, "PUBLIC_DISK_ROOT=/home/example/public_html/storage\n"), 'An absent deployment key must be added.');
$check(SjnrDomainRepair::replaceEnvironmentValues($repaired, ['APP_URL' => 'https://sjnrjournal.com']) === $repaired, 'Repeated repairs must preserve the same environment.');
$check(SjnrDomainRepair::replaceEnvironmentValues("export APP_URL =old\r\nOTHER=value\r\n", ['APP_URL' => 'https://sjnrjournal.com']) === "APP_URL=https://sjnrjournal.com\r\nOTHER=value\r\n", 'CRLF files must retain their line endings.');

try {
    SjnrDomainRepair::replaceEnvironmentValues("APP_URL=first\nexport APP_URL=second\n", ['APP_URL' => 'https://sjnrjournal.com']);
    $check(false, 'Duplicate deployment keys must be refused.');
} catch (RuntimeException $error) {
    $check(str_contains($error->getMessage(), 'Duplicate'), 'Duplicate rejection must occur before any mutation.');
}
$block = "<IfModule mod_headers.c>\n    Header always set X-Robots-Tag \"noindex, nofollow\"\n</IfModule>";
$rules = "DirectoryIndex index.php\n# preserve security rules\nHeader always set X-Frame-Options SAMEORIGIN\n".$block."\n";
$updated = SjnrDomainRepair::removePreviewNoindex($rules);
$check(! str_contains($updated, 'noindex'), 'Only the known preview noindex block must be removed.');
$check(str_contains($updated, "Header always set X-Frame-Options SAMEORIGIN\n"), 'Other security headers must survive.');
$check(SjnrDomainRepair::removePreviewNoindex($updated) === $updated, 'Repeated indexing activation must be idempotent.');
$check(! str_contains(SjnrDomainRepair::removePreviewNoindex(str_replace("\n", "\r\n", $rules)), 'noindex'), 'The exact CRLF preview block must also be recognized.');
try {
    SjnrDomainRepair::removePreviewNoindex("Header set X-Robots-Tag noindex\n");
    $check(false, 'Unknown noindex rules must be refused for review.');
} catch (RuntimeException $error) {
    $check(str_contains($error->getMessage(), 'unrecognized'), 'Unrecognized rules must not be broadly rewritten.');
}
try {
    SjnrDomainRepair::run([]);
    $check(false, 'A local checkout must never repair production.');
} catch (RuntimeException $error) {
    $check(str_contains($error->getMessage(), 'private application path'), 'Actual-path refusal must precede autoload or database access.');
}
echo json_encode(['ok' => true, 'fixture_checks' => $checks], JSON_THROW_ON_ERROR)."\n";
