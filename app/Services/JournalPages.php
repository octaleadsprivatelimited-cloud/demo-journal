<?php

namespace App\Services;

use App\Models\Setting;

class JournalPages
{
    public static function groups(): array
    {
        return ['journal'=>'About the journal', 'authors'=>'For authors', 'review'=>'Peer review', 'ethics'=>'Research & publication ethics', 'policies'=>'Publishing & website policies', 'records'=>'Publication records'];
    }

    public static function all(): array { return config('journal_pages'); }

    public static function url(string $slug): string
    {
        $page=self::all()[$slug];
        return $page['route'] ? route($page['route']) : route('policies.show', ['page'=>$slug]);
    }

    public static function state(string $slug): array
    {
        $page=self::all()[$slug];
        $saved=Setting::value('journal_page.'.$slug, []);
        $legacy=$page['legacy_key'] ? Setting::value($page['legacy_key']) : null;
        return array_replace([
            'draft'=>$legacy ?: $page['draft'],
            'content'=>$legacy,
            'published'=>filled($legacy),
            'updated_at'=>null,
            'version'=>0,
        ], is_array($saved) ? $saved : []);
    }
}
