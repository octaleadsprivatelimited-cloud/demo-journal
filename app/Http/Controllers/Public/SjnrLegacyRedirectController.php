<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SjnrLegacyRedirectController
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(config('journal.profile') === 'sjnr', 404);

        $source = rawurldecode($request->getPathInfo());
        $mapping = Setting::value('import.sjnr.legacy_routes', []);
        $destination = is_array($mapping) ? ($mapping[$source] ?? null) : null;

        abort_unless(is_string($destination) && str_starts_with($destination, '/')
            && ! str_starts_with($destination, '//') && $destination !== $source
            && ! preg_match('/[\x00-\x20\x7f\\\\]/', $destination), 404);

        return redirect()->to($destination, 301);
    }
}
