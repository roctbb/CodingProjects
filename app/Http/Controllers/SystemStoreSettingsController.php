<?php

namespace App\Http\Controllers;

use App\Services\SystemStoreSettings;
use Illuminate\Http\Request;

class SystemStoreSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function edit(SystemStoreSettings $settings)
    {
        return response()->view('settings.system-store', [
            'settings' => $settings->all(), 'items' => config('system_store.items'),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request, SystemStoreSettings $settings)
    {
        $rules = [
            'settings' => 'required|array:prices,pumpkin_promo',
            'settings.prices' => 'required|array:'.implode(',', array_keys(config('system_store.items'))),
            'settings.pumpkin_promo' => 'required|array:enabled,ends_at',
            'settings.pumpkin_promo.enabled' => 'required|boolean',
            'settings.pumpkin_promo.ends_at' => 'nullable|date_format:Y-m-d\TH:i',
        ];
        foreach (array_keys(config('system_store.items')) as $key) {
            $rules['settings.prices.'.$key] = 'required|integer|min:1|max:100000';
        }
        $data = $request->validate($rules)['settings'];
        $data['prices'] = array_map('intval', $data['prices']);
        $data['pumpkin_promo']['enabled'] = (bool) $data['pumpkin_promo']['enabled'];
        $settings->save($data, (int) $request->user()->id);
        return redirect()->route('settings.system-store.edit')->with('status', 'Цены и акция сохранены.');
    }
}
