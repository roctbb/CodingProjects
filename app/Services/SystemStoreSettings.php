<?php

namespace App\Services;

use App\SystemStoreSetting;
use App\MarketGood;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class SystemStoreSettings
{
    private ?array $loaded = null;

    public function all(): array
    {
        if ($this->loaded === null) {
            $defaults = [
                'prices' => array_map(fn ($item) => $item['price'], config('system_store.items')),
                'promotion' => ['enabled' => false, 'name' => '', 'description' => '', 'discount_percent' => 20,
                    'starts_at' => null, 'ends_at' => null, 'items' => []],
            ];
            $stored = Schema::hasTable('system_store_settings') ? SystemStoreSetting::find(1)?->settings : null;
            // The retired seasonal flag never enables a new promotion.
            $this->loaded = [
                'prices' => array_replace($defaults['prices'], array_intersect_key($stored['prices'] ?? [], $defaults['prices'])),
                'promotion' => array_replace($defaults['promotion'], array_intersect_key($stored['promotion'] ?? [], $defaults['promotion'])),
            ];
        }
        return $this->loaded;
    }

    public function price(string $key): int
    {
        return $this->quoteSystemItem($key)['price'];
    }

    public function basePrice(string $key): int
    {
        return (int) $this->all()['prices'][$key];
    }

    public function digitalTargets(): array
    {
        $items = [];
        foreach (config('system_store.items') as $key => $item) {
            if (!($item['storefront'] ?? true)) continue;
            $target = $this->systemTarget($key);
            if (isset($items[$target])) continue;
            $items[$target] = ['id' => $target, 'label' => str_starts_with($key, 'avatar_frame_custom_') ? 'Своя рамка — статичная и живая' : $item['label'],
                'group' => $item['group'], 'kind' => 'digital'];
        }
        return array_values($items);
    }

    private function systemTarget(string $key): string
    {
        return 'digital:'.(in_array($key, ['avatar_frame_custom_static', 'avatar_frame_custom_animated'], true) ? 'avatar_frame_custom' : $key);
    }

    public function promotionActive(): bool
    {
        $promo = $this->all()['promotion'];
        $now = Carbon::now('Europe/Moscow');
        return $promo['enabled'] && filled($promo['name']) && count($promo['items']) > 0
            && $promo['discount_percent'] >= 1 && $promo['discount_percent'] <= 99
            && (!$promo['starts_at'] || Carbon::parse($promo['starts_at'], 'Europe/Moscow')->lte($now))
            && (!$promo['ends_at'] || Carbon::parse($promo['ends_at'], 'Europe/Moscow')->gt($now));
    }

    public function quote(string $target, int $base, bool $eligible = true): array
    {
        $promo = $this->all()['promotion'];
        $discount = $eligible && $this->promotionActive() && in_array($target, $promo['items'], true) ? (int) $promo['discount_percent'] : 0;
        $price = $base > 0 && $discount ? max(1, (int) ceil($base * (100 - $discount) / 100)) : $base;
        return ['base_price' => $base, 'price' => $price, 'discount_percent' => $price < $base ? $discount : 0,
            'promotion_name' => $price < $base ? $promo['name'] : null];
    }

    public function quoteSystemItem(string $key): array
    {
        return $this->quote($this->systemTarget($key), $this->basePrice($key));
    }

    public function quoteGood(MarketGood $good): array
    {
        return $this->quote('good:'.$good->id, (int) $good->price, !$good->isAuction() && $good->in_stock);
    }

    public function roomCharacterPrice(): int
    {
        return $this->price('room_character');
    }

    public function save(array $settings, int $userId): void
    {
        SystemStoreSetting::updateOrCreate(['id' => 1], ['settings' => $settings, 'updated_by' => $userId]);
        $this->loaded = null;
    }
}
