<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'group' => ['required', 'string', Rule::in(array_keys(Setting::definitions()))],
            'settings' => ['required', 'array', 'min:1'],
            'settings.*' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $group = $this->input('group');
            $settings = $this->input('settings');

            if (! is_string($group) || ! is_array($settings)) {
                return;
            }

            $definitions = Setting::definitions($group);

            if ($definitions === []) {
                return;
            }

            foreach ($settings as $key => $value) {
                if (! is_string($key) || ! isset($definitions[$key])) {
                    $validator->errors()->add('settings', 'The submitted setting is not editable in this group.');

                    continue;
                }

                $definition = $definitions[$key];
                $fieldValidator = ValidatorFacade::make(
                    ['value' => $value],
                    ['value' => $definition['rules']],
                );

                if ($fieldValidator->fails()) {
                    $validator->errors()->add(
                        'settings',
                        $definition['label'].': '.$fieldValidator->errors()->first('value'),
                    );
                }
            }
        });
    }
}
