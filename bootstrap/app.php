<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureAuthorRegistrationIsEnabled;
use App\Http\Middleware\LocalhostAdminBypass;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = env('TRUSTED_PROXIES');

        if ($trustedProxies) {
            $middleware->trustProxies(
                at: $trustedProxies === '*'
                    ? '*'
                    : array_map('trim', explode(',', $trustedProxies)),
            );
        }

        $middleware->statefulApi();
        $middleware->append(SecurityHeaders::class);
        $middleware->redirectGuestsTo(static function (Request $request): string {
            return match (true) {
                $request->is('author/*') => route('author.login'),
                $request->is('editor/*') => route('editor.login'),
                $request->is('reviewer/*') => route('reviewer.login'),
                $request->is('admin'), $request->is('admin/*') => route('admin.login'),
                default => route('login'),
            };
        });
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'active' => EnsureAccountIsActive::class,
            'author-registration' => EnsureAuthorRegistrationIsEnabled::class,
            'local-admin-bypass' => LocalhostAdminBypass::class,
            'role' => RoleMiddleware::class,
        ]);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, LocalhostAdminBypass::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
