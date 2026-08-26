<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class Setting extends Model
{
    /**
     * Settings editable in the administration portal.
     *
     * Keys are deliberately canonical and map one-to-one to runtime consumers.
     * Mail transport, authentication limits, CAPTCHA and session settings stay in
     * deployment configuration because changing them safely requires a restart.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    private const DEFINITIONS = [
        'general' => [
            'site.name' => [
                'label' => 'Website name',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:120'],
                'default' => 'octaleads Journal',
                'public' => true,
            ],
            'site.tagline' => [
                'label' => 'Tagline',
                'type' => 'text',
                'rules' => ['nullable', 'string', 'max:160'],
                'default' => 'Independent ideas. Enduring perspective.',
                'public' => true,
            ],
            'site.description' => [
                'label' => 'Website and default SEO description',
                'type' => 'textarea',
                'rules' => ['nullable', 'string', 'max:500'],
                'default' => 'Independent research, criticism, and ideas for a changing world.',
                'public' => true,
            ],
            'contact.email' => [
                'label' => 'Public contact email',
                'type' => 'email',
                'rules' => ['nullable', 'email:rfc', 'max:254'],
                'default' => null,
                'public' => true,
            ],
            'contact.phone' => [
                'label' => 'Public phone number',
                'type' => 'text',
                'rules' => ['nullable', 'string', 'max:50'],
                'default' => null,
                'public' => true,
            ],
            'contact.address' => [
                'label' => 'Public address',
                'type' => 'textarea',
                'rules' => ['nullable', 'string', 'max:500'],
                'default' => null,
                'public' => true,
            ],
        ],
        'social' => [
            'social.facebook' => [
                'label' => 'Facebook URL',
                'type' => 'url',
                'rules' => ['nullable', 'url:http,https', 'max:2048'],
                'default' => null,
                'public' => true,
            ],
            'social.instagram' => [
                'label' => 'Instagram URL',
                'type' => 'url',
                'rules' => ['nullable', 'url:http,https', 'max:2048'],
                'default' => null,
                'public' => true,
            ],
            'social.linkedin' => [
                'label' => 'LinkedIn URL',
                'type' => 'url',
                'rules' => ['nullable', 'url:http,https', 'max:2048'],
                'default' => null,
                'public' => true,
            ],
            'social.x' => [
                'label' => 'X / Twitter URL',
                'type' => 'url',
                'rules' => ['nullable', 'url:http,https', 'max:2048'],
                'default' => null,
                'public' => true,
            ],
            'social.youtube' => [
                'label' => 'YouTube URL',
                'type' => 'url',
                'rules' => ['nullable', 'url:http,https', 'max:2048'],
                'default' => null,
                'public' => true,
            ],
        ],
        'publication' => [
            'journal.abbreviation' => ['label' => 'Standard journal abbreviation', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:80'], 'default' => null, 'public' => true],
            'journal.issn' => ['label' => 'Print ISSN (CLIENT INPUT REQUIRED)', 'type' => 'text', 'rules' => ['nullable', 'regex:/^\d{4}-\d{3}[\dXx]$/'], 'default' => null, 'public' => true],
            'journal.eissn' => ['label' => 'Electronic ISSN (CLIENT INPUT REQUIRED)', 'type' => 'text', 'rules' => ['nullable', 'regex:/^\d{4}-\d{3}[\dXx]$/'], 'default' => null, 'public' => true],
            'journal.publisher_name' => ['label' => 'Publisher name (CLIENT INPUT REQUIRED)', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:180'], 'default' => null, 'public' => true],
            'journal.publisher_address' => ['label' => 'Publisher address (CLIENT INPUT REQUIRED)', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:1000'], 'default' => null, 'public' => true],
            'journal.country' => ['label' => 'Country (CLIENT INPUT REQUIRED)', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:100'], 'default' => null, 'public' => true],
            'journal.subject_areas' => ['label' => 'Subject areas', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:1000'], 'default' => null, 'public' => true],
            'journal.frequency' => ['label' => 'Publication frequency (CLIENT INPUT REQUIRED)', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:120'], 'default' => null, 'public' => true],
            'journal.publication_history' => ['label' => 'Publication history', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000'], 'default' => null, 'public' => true],
            'publication.comments_enabled' => [
                'label' => 'Enable reader comments',
                'type' => 'boolean',
                'rules' => ['required', 'boolean'],
                'default' => true,
                'public' => true,
            ],
            'publication.author_registration_enabled' => [
                'label' => 'Enable author registration',
                'type' => 'boolean',
                'rules' => ['required', 'boolean'],
                'default' => true,
                'public' => true,
            ],
            'publication.pdf_downloads_enabled' => [
                'label' => 'Enable published PDF downloads',
                'type' => 'boolean',
                'rules' => ['required', 'boolean'],
                'default' => true,
                'public' => true,
            ],
            'publication.newsletter_enabled' => [
                'label' => 'Enable newsletter subscriptions',
                'type' => 'boolean',
                'rules' => ['required', 'boolean'],
                'default' => true,
                'public' => true,
            ],
        ],
        'policies' => [
            'policy.aims_scope' => ['label' => 'Aims & Scope (CLIENT INPUT REQUIRED)', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:50000'], 'default' => null, 'public' => true],
            'policy.peer_review' => ['label' => 'Peer Review Policy (CLIENT INPUT REQUIRED)', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:50000'], 'default' => null, 'public' => true],
            'policy.publication_ethics' => ['label' => 'Publication Ethics (CLIENT INPUT REQUIRED)', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:50000'], 'default' => null, 'public' => true],
            'policy.author_guidelines' => ['label' => 'Author Guidelines (CLIENT INPUT REQUIRED)', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:50000'], 'default' => null, 'public' => true],
            'policy.copyright' => ['label' => 'Copyright Policy (CLIENT INPUT REQUIRED)', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:50000'], 'default' => null, 'public' => true],
            'policy.open_access' => ['label' => 'Open Access Policy (CLIENT INPUT REQUIRED)', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:50000'], 'default' => null, 'public' => true],
            'policy.fees' => ['label' => 'Fees / APC / Waivers (CLIENT INPUT REQUIRED)', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:50000'], 'default' => null, 'public' => true],
            'policy.indexing' => ['label' => 'Indexing & Abstracting (verified entries only)', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:50000'], 'default' => null, 'public' => true],
            'policy.archiving' => ['label' => 'Archiving Policy (CLIENT INPUT REQUIRED)', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:50000'], 'default' => null, 'public' => true],
            'policy.privacy' => ['label' => 'Privacy Policy (CLIENT INPUT REQUIRED)', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:50000'], 'default' => null, 'public' => true],
            'policy.terms' => ['label' => 'Terms (CLIENT INPUT REQUIRED)', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:50000'], 'default' => null, 'public' => true],
        ],
    ];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'group', 'is_public'];

    protected function casts(): array
    {
        return ['value' => 'json', 'is_public' => 'boolean'];
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public static function value(string $key, mixed $default = null): mixed
    {
        return static::query()->whereKey($key)->value('value') ?? $default;
    }

    public static function put(string $key, mixed $value, string $group = 'general', bool $public = false): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group, 'is_public' => $public],
        );
    }

    /** @return array<string, array<string, array<string, mixed>>>|array<string, array<string, mixed>> */
    public static function definitions(?string $group = null): array
    {
        return $group === null ? self::DEFINITIONS : (self::DEFINITIONS[$group] ?? []);
    }

    /** @return array<string, mixed>|null */
    public static function definition(string $key): ?array
    {
        foreach (self::DEFINITIONS as $group => $definitions) {
            if (isset($definitions[$key])) {
                return ['group' => $group, ...$definitions[$key]];
            }
        }

        return null;
    }

    public static function putDefined(string $key, mixed $value): self
    {
        $definition = self::definition($key);

        if ($definition === null) {
            throw new InvalidArgumentException("Unknown editable setting [{$key}].");
        }

        return self::put(
            $key,
            $value,
            (string) $definition['group'],
            (bool) $definition['public'],
        );
    }
}
