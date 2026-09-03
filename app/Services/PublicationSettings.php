<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class PublicationSettings
{
    /** @return array<string, mixed> */
    public function site(): array
    {
        $defaults = $this->defaults();

        try {
            if (! Schema::hasTable('settings')) {
                return $defaults;
            }

            $settings = Setting::query()
                ->public()
                ->get(['key', 'value'])
                ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->value])
                ->all();

            $lookup = static function (string $key, mixed $fallback = null) use ($settings): mixed {
                $value = $settings[$key] ?? null;

                return $value === null || $value === '' ? $fallback : $value;
            };

            return [
                'name' => $lookup('site.name', $defaults['name']),
                'tagline' => $lookup('site.tagline', $defaults['tagline']),
                'description' => $lookup('site.description', $defaults['description']),
                'contact_email' => $lookup('contact.email', $defaults['contact_email']),
                'phone' => $lookup('contact.phone'),
                'address' => $lookup('contact.address'),
                'logo' => $defaults['logo'],
                'social' => [
                    'facebook' => $lookup('social.facebook'),
                    'instagram' => $lookup('social.instagram'),
                    'linkedin' => $lookup('social.linkedin'),
                    'x' => $lookup('social.x'),
                    'youtube' => $lookup('social.youtube'),
                ],
                'features' => [
                    'comments' => $this->boolean(
                        $lookup('publication.comments_enabled', $defaults['features']['comments']),
                        $defaults['features']['comments'],
                    ),
                    // Disabling public registration in deployment configuration is
                    // an authoritative security kill switch over the database value.
                    'author_registration' => $defaults['features']['author_registration']
                        && $this->boolean($lookup('publication.author_registration_enabled', true), true),
                    'pdf_downloads' => $this->boolean(
                        $lookup('publication.pdf_downloads_enabled', $defaults['features']['pdf_downloads']),
                        $defaults['features']['pdf_downloads'],
                    ),
                    'newsletter' => $this->boolean(
                        $lookup('publication.newsletter_enabled', $defaults['features']['newsletter']),
                        $defaults['features']['newsletter'],
                    ),
                ],
            ];
        } catch (Throwable) {
            return $defaults;
        }
    }

    public function featureEnabled(string $feature): bool
    {
        return (bool) data_get($this->site(), 'features.'.$feature, false);
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'name' => config('publication.name', config('app.name', 'octaleads Journal')),
            'tagline' => config('publication.tagline', 'Independent ideas. Enduring perspective.'),
            'description' => 'A journal of research, culture, public life, and the ideas shaping our shared future.',
            'contact_email' => config('publication.contact_email', config('mail.from.address')),
            'phone' => null,
            'address' => null,
            'logo' => null,
            'social' => [],
            'features' => [
                'comments' => (bool) config('publication.features.comments', true),
                'author_registration' => (bool) config('publication.features.author_registration', true),
                'pdf_downloads' => (bool) config('publication.features.pdf_downloads', true),
                'newsletter' => (bool) config('publication.features.newsletter', true),
            ],
        ];
    }

    private function boolean(mixed $value, bool $fallback): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $fallback;
    }
}
