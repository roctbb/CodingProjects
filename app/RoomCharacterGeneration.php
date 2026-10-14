<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RoomCharacterGeneration extends Model
{
    public const COST = 100;
    public const ACTIVE_STATUSES = ['pending', 'processing'];

    protected $guarded = ['id'];
    protected $casts = [
        'cost' => 'integer', 'started_at' => 'datetime', 'finished_at' => 'datetime',
        'request_aliases' => 'array',
    ];

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function imageUrl(): ?string
    {
        if ($this->status !== 'succeeded' || !$this->result_path || !Storage::exists($this->result_path)) {
            return null;
        }

        return url('/media/' . $this->result_path);
    }

    public function statusMessage(): string
    {
        return match ($this->status) {
            'pending' => 'Ждём начала генерации. Можно закрыть страницу — работа продолжится.',
            'processing' => 'Создаём персонажа по вашей аватарке. Обычно это занимает несколько минут.',
            'succeeded' => $this->imageUrl()
                ? 'Персонаж готов и установлен в комнате.'
                : 'Персонаж создан, но изображение недоступно. Обратитесь к администратору; повторная генерация не нужна.',
            default => 'Создать персонажа не удалось. ' . $this->cost . ' GC возвращены на баланс.',
        };
    }
}
