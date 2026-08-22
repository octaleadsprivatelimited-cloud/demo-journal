<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Contracts\ArticleSearch;
use App\Services\DatabaseArticleSearch;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->preventUnsafeTestExecution();

        $this->app->bind(ArticleSearch::class, DatabaseArticleSearch::class);
    }

    /**
     * Refuse to boot Laravel's Artisan test runner against a live database.
     */
    private function preventUnsafeTestExecution(): void
    {
        if (! $this->app->runningInConsole() || ! in_array('test', $_SERVER['argv'] ?? [], true)) {
            return;
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $isolatedDatabase = ($connection === 'sqlite' && $database === ':memory:')
            || Str::contains(Str::lower($database), 'test');

        if (! $this->app->environment('testing') || ! $isolatedDatabase) {
            throw new RuntimeException(
                'Refusing to run tests outside an isolated testing database. Use `docker compose run --rm test`.',
            );
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        if (config('security.force_https')) {
            URL::forceScheme('https');
        }

        Gate::before(static function (User $user): ?bool {
            return method_exists($user, 'hasRole') && $user->hasRole('super-admin') ? true : null;
        });

        Gate::define('viewAdminDashboard', static fn (User $user): bool => $user->hasAnyRole('admin', 'editor')
        );
        Gate::define('viewAuditLogs', static fn (User $user): bool => $user->hasPermission('audit.view')
        );
        Gate::define('manageSettings', static fn (User $user): bool => $user->hasPermission('settings.manage')
        );
        Gate::define('moderateComments', static fn (User $user): bool => $user->hasPermission('comments.moderate')
        );

        Blade::if('role', static fn (string ...$roles): bool => auth()->check() && auth()->user()->hasAnyRole(...$roles)
        );

        RateLimiter::for('login', static function (Request $request): Limit {
            $identity = Str::transliterate(Str::lower((string) $request->input('email'))).'|'.$request->ip();

            return Limit::perMinute((int) config('publication.rate_limits.login', 5))->by($identity);
        });

        RateLimiter::for('contact', static fn (Request $request): Limit => Limit::perMinute((int) config('publication.rate_limits.contact', 5))->by($request->ip())
        );

        RateLimiter::for('search', static fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip())
        );

        RateLimiter::for('uploads', static fn (Request $request): Limit => Limit::perMinute(20)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()))
        );

        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(120)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()))
        );
    }
}
