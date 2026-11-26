<?php

namespace App\Http\Requests;

use App\Services\Ai\AiSettings;
use App\Services\Ai\Connection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $providers = array_keys(AiSettings::PROVIDERS);
        $selected = $this->input('settings.provider');
        $model = ['string', 'max:200', 'regex:/\A[a-zA-Z0-9][a-zA-Z0-9._:\/-]*\z/'];
        $rules = [
            'settings' => ['required', 'array:provider,providers,defaults,commands,shared_models,tutor_limits,submission_hourly_limit'],
            'settings.provider' => ['required', Rule::in($providers)],
            'settings.shared_models' => ['sometimes', 'boolean'],
            'settings.submission_hourly_limit' => ['sometimes', 'nullable', 'integer', 'between:1,1000'],
            'settings.providers' => ['required', 'array:' . implode(',', $providers)],
            'settings.defaults' => ['required', 'array:text,image,recheck'],
            'settings.commands' => ['sometimes', 'array:' . implode(',', array_keys(AiSettings::COMMANDS))],
            'secrets' => ['required', 'array:' . implode(',', $providers)],
        ];
        $rules['settings.tutor_limits'] = ['sometimes', 'array:'.implode(',', array_keys(AiSettings::TUTOR_LIMITS))];
        foreach (array_keys(AiSettings::TUTOR_LIMITS) as $key) {
            $rules['settings.tutor_limits.'.$key] = ['required_with:settings.tutor_limits', 'integer', 'between:0,2147483647'];
        }
        foreach ($providers as $provider) {
            $rules['settings.providers.' . $provider] = [$provider === $selected ? 'required' : 'sometimes', 'array:base_url,endpoint,image_api,images_url'];
            $rules['settings.providers.' . $provider . '.endpoint'] = [
                'bail', 'nullable', Rule::requiredIf($provider === $selected && !$this->filled('settings.providers.openai.base_url')), 'string', 'max:2048', 'url:http,https',
                function ($attribute, $value, $fail) {
                    if (parse_url($value, PHP_URL_USER) !== null || parse_url($value, PHP_URL_PASS) !== null
                        || parse_url($value, PHP_URL_FRAGMENT) !== null) {
                        $fail('Эндпоинт не должен содержать логин, пароль или фрагмент URL.');
                    }
                },
            ];
            $rules['settings.providers.'.$provider.'.base_url'] = ['bail', 'nullable', 'string', 'max:2048', function ($attribute, $value, $fail) {
                try { Connection::validateBaseUrl($value); }
                catch (\InvalidArgumentException) { $fail('Укажите базовый HTTP(S)-адрес API без /responses, /images, логина, пароля, параметров и фрагмента.'); }
            }];
            $rules['settings.providers.'.$provider.'.image_api'] = ['sometimes', 'required', Rule::in(['responses', 'images'])];
            $rules['secrets.' . $provider] = [$provider === $selected ? 'required' : 'sometimes', 'array:key,proxy'];
            foreach (['key', 'proxy'] as $field) {
                $prefix = 'secrets.' . $provider . '.' . $field;
                $rules[$prefix] = ['required_with:secrets.'.$provider, 'array:action,value'];
                $rules[$prefix . '.action'] = ['required_with:'.$prefix, Rule::in(['keep', 'replace', 'clear', 'inherit'])];
                $rules[$prefix . '.value'] = ['bail', 'nullable', 'required_if:' . $prefix . '.action,replace', 'string', 'max:4096'];
                if ($field === 'proxy') {
                    $rules[$prefix . '.value'][] = function ($attribute, $value, $fail) {
                        $url = parse_url($value);
                        if (!$url || empty($url['host'])
                            || !in_array($url['scheme'] ?? '', ['http', 'https', 'socks5', 'socks5h'], true)
                            || !in_array($url['path'] ?? '', ['', '/'], true)
                            || isset($url['query']) || isset($url['fragment']) || preg_match('/\s/', $value)) {
                            $fail('Укажите адрес HTTP(S) или SOCKS5-прокси: схема://логин:пароль@хост:порт.');
                        }
                    };
                } else {
                    $rules[$prefix . '.value'][] = 'regex:/\A[^\s]+\z/';
                }
            }
        }
        $rules['settings.providers.openai.images_url'] = [
            'bail', 'nullable', Rule::requiredIf($this->input('settings.providers.openai.image_api') === 'images' && !$this->filled('settings.providers.openai.base_url')), 'string', 'max:2048', 'url:http,https',
            function ($attribute, $value, $fail) {
                if (parse_url($value, PHP_URL_USER) !== null || parse_url($value, PHP_URL_PASS) !== null
                    || parse_url($value, PHP_URL_QUERY) !== null || parse_url($value, PHP_URL_FRAGMENT) !== null
                    || !str_ends_with(rtrim(parse_url($value, PHP_URL_PATH) ?? '', '/'), '/images')) {
                    $fail('Адрес Images API должен заканчиваться на /images, без логина, пароля, query-параметров и фрагмента.');
                }
            },
        ];
        foreach (['text', 'image'] as $type) {
            $rules['settings.defaults.' . $type] = ['required', 'array:model,timeout'];
            $rules['settings.defaults.' . $type . '.model'] = ['required', ...$model];
            $rules['settings.defaults.' . $type . '.timeout'] = ['required', 'integer', 'between:1,180'];
        }
        $rules['settings.defaults.recheck'] = ['sometimes', 'array:model,timeout'];
        $rules['settings.defaults.recheck.model'] = ['nullable', ...$model];
        $rules['settings.defaults.recheck.timeout'] = ['required_with:settings.defaults.recheck', 'integer', 'between:1,180'];
        foreach (array_keys(AiSettings::COMMANDS) as $command) {
            $prefix = 'settings.commands.' . $command;
            $rules[$prefix] = ['required_with:settings.commands', 'array:provider,model,timeout'];
            $rules[$prefix . '.provider'] = ['nullable', Rule::in($providers)];
            $rules[$prefix . '.model'] = ['nullable', ...$model];
            $rules[$prefix . '.timeout'] = ['nullable', 'integer', 'between:1,180'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = [
            'settings.provider' => 'провайдер по умолчанию',
            'settings.submission_hourly_limit' => 'лимит попыток ИИ-проверки в час',
        ];
        foreach (AiSettings::TUTOR_LIMITS as $key => $label) {
            $attributes['settings.tutor_limits.'.$key] = $label;
        }
        $attributes['settings.providers.openai.image_api'] = 'формат генерации изображений';
        $attributes['settings.providers.openai.images_url'] = 'адрес Images API';
        foreach (AiSettings::PROVIDERS as $provider => $label) {
            $attributes['settings.providers.'.$provider.'.base_url'] = 'базовый адрес «'.$label.'»';
            $attributes['settings.providers.'.$provider.'.image_api'] = 'формат изображений «'.$label.'»';
            $attributes['settings.providers.' . $provider . '.endpoint'] = 'эндпоинт «' . $label . '»';
            $attributes['secrets.' . $provider . '.key.value'] = 'новый ключ «' . $label . '»';
            $attributes['secrets.' . $provider . '.proxy.value'] = 'новый прокси «' . $label . '»';
        }

        return $attributes;
    }
}
