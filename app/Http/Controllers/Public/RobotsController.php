<?php

namespace App\Http\Controllers\Public;

use Illuminate\Http\Response;

class RobotsController extends PublicController
{
    public function __invoke(): Response
    {
        if (! config('publication.indexing_enabled', true)) {
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $content = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            'Disallow: /author/dashboard',
            'Disallow: /author/articles',
            'Disallow: /api/',
            'Disallow: /editor/',
            'Disallow: /reviewer/',
            'Disallow: /author/submissions',
            'Disallow: /author/profile',
            'Disallow: /author/settings',
            'Disallow: /author/notifications',
            'Disallow: /author/register',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            'Disallow: /verify-email',
            '',
            'Sitemap: '.config('publication.sitemap_base_url').'/sitemap.xml',
            '',
        ]);

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
