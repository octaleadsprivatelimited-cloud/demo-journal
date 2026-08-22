<?php

namespace App\Http\Controllers\Public;

use Illuminate\Http\Response;

class RobotsController extends PublicController
{
    public function __invoke(): Response
    {
        $content = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            'Disallow: /author/dashboard',
            'Disallow: /author/articles',
            'Disallow: /api/',
            '',
            'Sitemap: '.route('sitemap'),
            '',
        ]);

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
