<?php

namespace App\Http\Controllers;

use App\Services\SystemStoreSettings;
use App\MarketGood;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'promotionItems' => $this->selectedItems(array_values(array_filter((array) old('settings.promotion.items', $settings->all()['promotion']['items']), fn ($target) => is_string($target) && strlen($target) <= 80)), $settings),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request, SystemStoreSettings $settings)
    {
        $rules = [
            'settings' => 'required|array:prices,promotion',
            'settings.prices' => 'required|array:'.implode(',', array_keys(config('system_store.items'))),
            'settings.promotion' => 'required|array:enabled,name,description,discount_percent,starts_at,ends_at,items',
            'settings.promotion.enabled' => 'required|boolean',
            'settings.promotion.name' => 'nullable|required_if:settings.promotion.enabled,1|string|max:120',
            'settings.promotion.description' => 'nullable|string|max:1000',
            'settings.promotion.discount_percent' => 'required|integer|min:1|max:99',
            'settings.promotion.starts_at' => 'nullable|date_format:Y-m-d\TH:i',
            'settings.promotion.ends_at' => 'nullable|date_format:Y-m-d\TH:i',
            'settings.promotion.items' => 'sometimes|array|max:1000',
            'settings.promotion.items.*' => 'required|string|max:80|distinct',
        ];
        if ($request->filled('settings.promotion.starts_at')) $rules['settings.promotion.ends_at'] .= '|after:settings.promotion.starts_at';
        foreach (array_keys(config('system_store.items')) as $key) {
            $rules['settings.prices.'.$key] = 'required|integer|min:1|max:100000';
        }
        $data = $request->validate($rules, [
            'settings.promotion.name.required_if' => 'Укажите название акции.',
            'settings.promotion.name.max' => 'Название акции должно быть не длиннее 120 символов.',
            'settings.promotion.description.max' => 'Сообщение должно быть не длиннее 1000 символов.',
            'settings.promotion.discount_percent.*' => 'Укажите целую скидку от 1 до 99%.',
            'settings.promotion.starts_at.date_format' => 'Укажите дату и время начала акции.',
            'settings.promotion.ends_at.date_format' => 'Укажите дату и время окончания акции.',
            'settings.promotion.ends_at.after' => 'Окончание акции должно быть позже начала.',
            'settings.promotion.items.*.distinct' => 'Товар не должен повторяться в акции.',
        ])['settings'];
        $data['prices'] = array_map('intval', $data['prices']);
        $promotion = &$data['promotion'];
        $promotion['enabled'] = (bool) $promotion['enabled'];
        $promotion['discount_percent'] = (int) $promotion['discount_percent'];
        $promotion['name'] = trim($promotion['name'] ?? '');
        $promotion['description'] = trim($promotion['description'] ?? '');
        $promotion['items'] = array_values($promotion['items'] ?? []);
        if ($promotion['enabled'] && ($promotion['name'] === '' || !$promotion['items'])) {
            throw ValidationException::withMessages(['settings.promotion.items' => 'Укажите название акции и выберите хотя бы один товар.']);
        }
        if (count($this->selectedItems($promotion['items'], $settings)) !== count($promotion['items'])) {
            throw ValidationException::withMessages(['settings.promotion.items' => 'Один из выбранных товаров недоступен. Обновите список товаров акции.']);
        }
        $settings->save($data, (int) $request->user()->id);
        return redirect()->route('settings.system-store.edit')->with('status', 'Цены и акция сохранены.');
    }

    public function targets(Request $request, SystemStoreSettings $settings)
    {
        $data = $request->validate(['type' => ['required', Rule::in(['digital', 'goods'])], 'q' => 'nullable|string|max:200', 'page' => 'sometimes|integer|min:1|max:100000']);
        $query = trim($data['q'] ?? '');
        $page = (int) ($data['page'] ?? 1);
        if ($data['type'] === 'digital') {
            $items = collect($settings->digitalTargets())->filter(fn ($item) => $query === '' || mb_stripos($item['label'].' '.$item['group'], $query) !== false)->values();
            return response()->json(['items' => $items->forPage($page, 25)->values(), 'page' => $page, 'has_more' => $items->count() > $page * 25])->header('Cache-Control', 'no-store, private');
        }
        $goodsQuery = MarketGood::where(fn ($q) => $q->where('sale_type', '!=', MarketGood::SALE_TYPE_AUCTION)->orWhereNull('sale_type'));
        if ($query !== '') {
            $connection = $goodsQuery->getConnection();
            $column = 'LOWER(name)';
            if ($connection->getDriverName() === 'sqlite') {
                $connection->getPdo()->sqliteCreateFunction('cp_lower', fn ($value) => mb_strtolower((string) $value), 1);
                $column = 'cp_lower(name)';
            }
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($query)).'%';
            $goodsQuery->whereRaw($column." LIKE ? ESCAPE '!'", [$pattern]);
        }
        $goods = $goodsQuery->orderBy('name')->orderBy('id')->simplePaginate(25);
        return response()->json(['items' => $goods->map(fn ($good) => ['id' => 'good:'.$good->id, 'label' => $good->name,
            'group' => $good->in_stock ? 'Обычные товары' : 'Архив', 'kind' => 'goods']), 'page' => $page, 'has_more' => $goods->hasMorePages()])->header('Cache-Control', 'no-store, private');
    }

    private function selectedItems(array $targets, SystemStoreSettings $settings): array
    {
        $digital = collect($settings->digitalTargets())->keyBy('id');
        $ids = collect($targets)->filter(fn ($target) => is_string($target) && preg_match('/^good:[1-9][0-9]*$/D', $target))->map(fn ($target) => substr($target, 5));
        $goods = $ids->isEmpty() ? collect() : MarketGood::whereIn('id', $ids)->get()->filter(fn ($good) => !$good->isAuction())->keyBy('id');
        return collect($targets)->map(function ($target) use ($digital, $goods) {
            if ($digital->has($target)) return $digital[$target];
            $good = str_starts_with($target, 'good:') ? $goods->get(substr($target, 5)) : null;
            return $good ? ['id' => $target, 'label' => $good->name, 'kind' => 'goods', 'group' => 'Обычные товары'] : null;
        })->filter()->values()->all();
    }
}
