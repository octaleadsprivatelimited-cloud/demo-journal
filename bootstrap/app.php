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
    ->withEvents(discover: false)
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
        $middleware->web(append: [\App\Http\Middleware\OptimizeImageUploads::class]);
        $middleware->api(append: [\App\Http\Middleware\OptimizeImageUploads::class]);
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
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $error, Request $request) {
            $message = 'The upload is too large. Keep the complete submission below 32 MB and each document below 20 MB. Submit supplementary files in smaller batches.';
            return $request->is('api/*') || $request->expectsJson()
                ? response()->json(['message' => $message, 'code' => 'upload_too_large'], 413)
                : response()->view('errors.413', [], 413);
        });
        $exceptions->render(function (\League\Flysystem\FilesystemException $error, Request $request) {
            $message = 'File storage is temporarily unavailable. Keep your original files and retry when storage is available.';
            return $request->is('api/*') || $request->expectsJson()
                ? response()->json(['message' => $message, 'code' => 'storage_unavailable'], 503)
                : response()->view('errors.storage', [], 503)->header('X-Error-Code', 'storage_unavailable');
        });

        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, Request $request) {
            $status = $response->getStatusCode();
            if ($status < 400) {
                return $response;
            }
            $code = $response->headers->get('X-Error-Code') ?: \App\Support\ErrorCodes::forStatus($status);
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $data = $response->getData(true);
                $data = is_array($data) ? $data : [];
                $code = $data['code'] ?? $code;
                if ($status >= 500 && ! isset($data['code'])) {
                    $data = ['message' => 'The service could not complete this request. Please try again shortly. If it continues, contact the journal administrator.'];
                }
                $data['code'] = $code;
                if (isset($data['errors']) && is_array($data['errors'])) {
                    $data['error_codes'] = collect($data['errors'])->map(fn ($messages) => array_map(
                        fn ($message) => \App\Support\ErrorCodes::fromMessage($message), (array) $messages,
                    ))->all();
                }
                $response->setData($data);
            } elseif ($status >= 500 && $code !== 'storage_unavailable') {
                $response = response()->view($status === 503 ? 'errors.503' : 'errors.500', [], $status);
            }
            $response->headers->set('X-Error-Code', $code);
            return $response;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
