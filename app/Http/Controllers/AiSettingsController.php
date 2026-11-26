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
            'tutorLimits' => AiSettings::TUTOR_LIMITS,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function update(UpdateAiSettingsRequest $request, AiSettings $settings)
    {
        $data = $request->validated();
        if (isset($data['settings']['submission_hourly_limit'])) {
            $data['settings']['submission_hourly_limit'] = (int) $data['settings']['submission_hourly_limit'];
        }
        if (isset($data['settings']['tutor_limits'])) {
            $data['settings']['tutor_limits'] = array_map('intval', $data['settings']['tutor_limits']);
        }
        foreach (['defaults', 'commands'] as $group) {
            if (!isset($data['settings'][$group])) continue;
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
