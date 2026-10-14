<?php

namespace App\Http\Requests;

use App\Services\Ai\AiSettings;
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
        $model = ['string', 'max:200', 'regex:/\A[a-zA-Z0-9][a-zA-Z0-9._:\/-]*\z/'];
        $rules = [
            'settings' => ['required', 'array:provider,providers,defaults,commands'],
            'settings.provider' => ['required', Rule::in($providers)],
            'settings.providers' => ['required', 'array:' . implode(',', $providers)],
            'settings.defaults' => ['required', 'array:text,image'],
            'settings.commands' => ['required', 'array:' . implode(',', array_keys(AiSettings::COMMANDS))],
            'secrets' => ['required', 'array:' . implode(',', $providers)],
        ];
        foreach ($providers as $provider) {
            $rules['settings.providers.' . $provider] = ['required', 'array:endpoint'];
            $rules['settings.providers.' . $provider . '.endpoint'] = [
                'bail', 'required', 'string', 'max:2048', 'url:http,https',
                function ($attribute, $value, $fail) {
                    if (parse_url($value, PHP_URL_USER) !== null || parse_url($value, PHP_URL_PASS) !== null
                        || parse_url($value, PHP_URL_FRAGMENT) !== null) {
                        $fail('Эндпоинт не должен содержать логин, пароль или фрагмент URL.');
                    }
                },
            ];
            $rules['secrets.' . $provider] = ['required', 'array:key,proxy'];
            foreach (['key', 'proxy'] as $field) {
                $prefix = 'secrets.' . $provider . '.' . $field;
                $rules[$prefix] = ['required', 'array:action,value'];
                $rules[$prefix . '.action'] = ['required', Rule::in(['keep', 'replace', 'clear', 'inherit'])];
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
        foreach (['text', 'image'] as $type) {
            $rules['settings.defaults.' . $type] = ['required', 'array:model,timeout'];
            $rules['settings.defaults.' . $type . '.model'] = ['required', ...$model];
            $rules['settings.defaults.' . $type . '.timeout'] = ['required', 'integer', 'between:1,180'];
        }
        foreach (array_keys(AiSettings::COMMANDS) as $command) {
            $prefix = 'settings.commands.' . $command;
            $rules[$prefix] = ['required', 'array:provider,model,timeout'];
            $rules[$prefix . '.provider'] = ['nullable', Rule::in($providers)];
            $rules[$prefix . '.model'] = ['nullable', ...$model];
            $rules[$prefix . '.timeout'] = ['nullable', 'integer', 'between:1,180'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = ['settings.provider' => 'провайдер по умолчанию'];
        foreach (AiSettings::PROVIDERS as $provider => $label) {
            $attributes['settings.providers.' . $provider . '.endpoint'] = 'эндпоинт «' . $label . '»';
            $attributes['secrets.' . $provider . '.key.value'] = 'новый ключ «' . $label . '»';
            $attributes['secrets.' . $provider . '.proxy.value'] = 'новый прокси «' . $label . '»';
        }

        return $attributes;
    }
}
