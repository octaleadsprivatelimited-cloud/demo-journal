<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function authenticateFor(string $portal): User
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $this->user()?->isActive()) {
            $status = (string) $this->user()?->status;
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => match ($status) {
                    'pending' => 'Your application is awaiting Super Admin approval.',
                    'rejected' => 'Your application was not approved. Contact the editorial office for assistance.',
                    default => 'This account is currently inactive. Please contact the editorial office.',
                },
            ]);
        }

        $user = $this->user();

        if (! $user instanceof User || ! $this->canAccessPortal($user, $portal)) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    private function canAccessPortal(User $user, string $portal): bool
    {
        return match ($portal) {
            'author' => $user->hasAnyRole('author', 'contributor'),
            'contributor' => $user->hasRole('contributor'),
            'editor' => $user->hasRole('editor'),
            'reviewer' => $user->hasRole('reviewer'),
            'admin' => $user->hasAnyRole('admin', 'super-admin'),
            default => false,
        };
    }

    private function ensureIsNotRateLimited(): void
    {
        $maximumAttempts = max(1, (int) config('publication.rate_limits.login', 5));

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $maximumAttempts)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('email')).'|'.$this->ip());
    }
}
