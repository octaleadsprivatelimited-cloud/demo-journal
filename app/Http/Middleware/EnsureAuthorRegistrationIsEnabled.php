<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\PublicationSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAuthorRegistrationIsEnabled
{
    public function __construct(private readonly PublicationSettings $publicationSettings) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $this->publicationSettings->featureEnabled('author_registration'),
            Response::HTTP_NOT_FOUND,
        );

        return $next($request);
    }
}
