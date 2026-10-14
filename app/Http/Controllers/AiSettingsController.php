<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAiSettingsRequest;
use App\Services\Ai\AiSettings;

class AiSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function edit(AiSettings $settings)
    {
        return response()->view('settings.ai', $settings->forForm() + [
            'providers' => AiSettings::PROVIDERS,
            'commands' => AiSettings::COMMANDS,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function update(UpdateAiSettingsRequest $request, AiSettings $settings)
    {
        $data = $request->validated();
        foreach (['defaults', 'commands'] as $group) {
            foreach ($data['settings'][$group] as &$item) {
                $item['timeout'] = isset($item['timeout']) ? (int) $item['timeout'] : null;
            }
            unset($item);
        }
        $settings->save($data['settings'], $data['secrets'], (int) $request->user()->id);

        return redirect()->route('settings.ai.edit')->with('status', 'Настройки ИИ сохранены. Следующие запросы используют новые параметры.');
    }

    public function reset(AiSettings $settings)
    {
        $settings->reset();

        return redirect()->route('settings.ai.edit')->with('status', 'Восстановлены серверные настройки ИИ.');
    }
}
