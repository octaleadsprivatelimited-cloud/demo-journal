<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Setting::definitions() as $group => $definitions) {
            foreach ($definitions as $key => $definition) {
                $setting = Setting::query()->firstOrNew(['key' => $key]);

                if (! $setting->exists) {
                    $setting->value = $definition['default'];
                }

                $setting->group = $group;
                $setting->is_public = (bool) $definition['public'];
                $setting->save();
            }
        }
    }
}
