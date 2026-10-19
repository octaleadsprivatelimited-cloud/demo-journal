<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class EditorialMember extends Model
{
    protected $fillable = ['name', 'group', 'role', 'institution', 'department', 'country', 'credentials', 'biography', 'orcid', 'photo_path', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function getNameAttribute(?string $value): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $value ?? ''));

        $name = preg_replace('/\b(Dr|Prof)\s*\.?\s+/iu', '$1. ', $name);

        // Correct the duplicated surname in the legacy public directory.
        return $name === 'Dr. Tahera Nazrin Nazrin' ? 'Dr. Tahera Nazrin' : $name;
    }

    public function getInstitutionAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_replace('/\s*,\s*/u', ', ', str_ireplace('universty', 'university', trim($value)));
    }

    /** Keep one public profile per person without deleting editorial records. */
    public static function publicDirectory(?string $group = null): Collection
    {
        return static::query()->where('is_active', true)
            ->when($group, fn ($query) => $query->where('group', $group))
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')->get()
            ->unique(fn ($member) => mb_strtolower(preg_replace('/[^\pL\pN]/u', '', preg_replace('/^(Dr|Prof)\.\s*/iu', '', $member->name))))
            ->values();
    }

    public function getDesignationAttribute(): string
    {
        return $this->role;
    }

    public function getOrganizationAttribute(): ?string
    {
        return $this->institution;
    }

    public function getAvatarPathAttribute(): ?string
    {
        return $this->photo_path;
    }

    public function getIsVerifiedAttribute(): bool
    {
        return true;
    }

    public function getSlugAttribute(): string
    {
        return str($this->name)->slug()->toString();
    }
}
