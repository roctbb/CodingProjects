<?php

namespace App\Services;

use App\SystemStoreSetting;
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
                'pumpkin_promo' => ['enabled' => false, 'ends_at' => null],
            ];
            $stored = Schema::hasTable('system_store_settings') ? SystemStoreSetting::find(1)?->settings : null;
            $this->loaded = array_replace_recursive($defaults, $stored ?? []);
        }
        return $this->loaded;
    }

    public function price(string $key): int
    {
        return (int) $this->all()['prices'][$key];
    }

    public function promotionActive(): bool
    {
        $promo = $this->all()['pumpkin_promo'];
        return $promo['enabled'] && $this->price('room_character') > 1
            && (!$promo['ends_at'] || Carbon::parse($promo['ends_at'], 'Europe/Moscow')->isFuture());
    }

    public function roomCharacterPrice(): int
    {
        $base = $this->price('room_character');
        return $this->promotionActive() ? (int) ceil($base / 2) : $base;
    }

    public function save(array $settings, int $userId): void
    {
        SystemStoreSetting::updateOrCreate(['id' => 1], ['settings' => $settings, 'updated_by' => $userId]);
        $this->loaded = null;
    }
}
