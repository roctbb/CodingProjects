<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTelegramSettingsRequest;
use App\Services\TelegramSettings;

class TelegramSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function edit(TelegramSettings $settings)
    {
        return response()->view('settings.telegram', $settings->forForm())
            ->header('Cache-Control', 'no-store, private');
    }

    public function update(UpdateTelegramSettingsRequest $request, TelegramSettings $settings)
    {
        $data = $request->validated();
        $data['settings']['timeout'] = (int) $data['settings']['timeout'];
        $data['settings']['api_url'] = rtrim($data['settings']['api_url'], '/');
        $settings->save($data['settings'], $data['secrets'], (int) $request->user()->id);

        return redirect()->route('settings.telegram.edit')->with('status', 'Настройки Telegram сохранены. Следующие запросы используют новые параметры.');
    }

    public function reset(TelegramSettings $settings)
    {
        $settings->reset();

        return redirect()->route('settings.telegram.edit')->with('status', 'Восстановлены серверные настройки Telegram.');
    }
}
