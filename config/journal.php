<?php

declare(strict_types=1);

$profiles = require __DIR__.'/journal_profiles.php';
$profile = (string) env('JOURNAL_PROFILE', 'sjc');

if (! array_key_exists($profile, $profiles)) {
    throw new InvalidArgumentException('Unsupported JOURNAL_PROFILE. Choose a defined journal profile.');
}

return ['profile' => $profile, ...$profiles[$profile]];
