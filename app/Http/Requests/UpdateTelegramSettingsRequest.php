<?php

namespace App\Http\Requests;

use App\Services\TelegramSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTelegramSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('settings.bot_username'))) {
            $this->merge(['settings' => array_replace($this->input('settings'), [
                'bot_username' => ltrim(trim($this->input('settings.bot_username')), '@'),
            ])]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'settings' => ['required', 'array:bot_username,api_url,timeout'],
            'settings.bot_username' => ['nullable', 'string', 'max:32', 'regex:/\A[a-zA-Z][a-zA-Z0-9_]{4,31}\z/'],
            'settings.timeout' => ['required', 'integer', 'between:1,120'],
            'settings.api_url' => [
                'bail', 'required', 'string', 'max:2048', 'url:http,https',
                function ($attribute, $value, $fail) {
                    $url = parse_url($value);
                    if (isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
                        || preg_match('~(?:^|/)bot[^/]*:~', $url['path'] ?? '')) {
                        $fail('Укажите базовый адрес Bot API без ключа, логина, пароля и query-параметров.');
                    }
                },
            ],
            'secrets' => ['required', 'array:' . implode(',', TelegramSettings::SECRETS)],
        ];
        foreach (TelegramSettings::SECRETS as $field) {
            $prefix = 'secrets.' . $field;
            $rules[$prefix] = ['required', 'array:action,value'];
            $rules[$prefix . '.action'] = ['required', Rule::in(['keep', 'replace', 'clear', 'inherit'])];
            $rules[$prefix . '.value'] = ['bail', 'nullable', 'required_if:' . $prefix . '.action,replace', 'string', 'max:4096'];
        }
        $rules['secrets.bot_token.value'][] = 'regex:/\A[0-9]+:[a-zA-Z0-9_-]+\z/';
        $rules['secrets.webhook_secret.value'][] = 'max:256';
        $rules['secrets.webhook_secret.value'][] = 'regex:/\A[a-zA-Z0-9_-]+\z/';
        $rules['secrets.proxy.value'][] = function ($attribute, $value, $fail) {
            $url = parse_url($value);
            if (!$url || empty($url['host'])
                || !in_array($url['scheme'] ?? '', ['http', 'https', 'socks5', 'socks5h'], true)
                || !in_array($url['path'] ?? '', ['', '/'], true)
                || isset($url['query']) || isset($url['fragment']) || preg_match('/\s/', $value)) {
                $fail('Укажите адрес HTTP(S) или SOCKS5-прокси: схема://логин:пароль@хост:порт.');
            }
        };

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'settings.bot_username' => 'имя бота',
            'settings.api_url' => 'адрес Bot API',
            'settings.timeout' => 'время ожидания',
            'secrets.bot_token.value' => 'ключ бота',
            'secrets.proxy.value' => 'адрес прокси',
            'secrets.webhook_secret.value' => 'секрет вебхука',
        ];
    }
}
