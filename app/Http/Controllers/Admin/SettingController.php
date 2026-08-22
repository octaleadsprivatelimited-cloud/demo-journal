<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class SettingController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manageSettings');

        return view('admin.settings.index', [
            'definitions' => Setting::definitions(),
            'values' => Setting::query()->pluck('value', 'key'),
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        Gate::authorize('manageSettings');
        $group = $request->string('group')->toString();

        foreach ($request->input('settings') as $key => $value) {
            $definition = Setting::definition((string) $key);
            abort_unless($definition !== null && $definition['group'] === $group, 422, 'Invalid setting key.');

            Setting::putDefined(
                (string) $key,
                $this->normalize($value, (string) $definition['type']),
            );
        }

        return back()->with('success', str($group)->headline().' settings saved.');
    }

    private function normalize(mixed $value, string $type): mixed
    {
        if ($type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' ? null : $value;
    }
}
