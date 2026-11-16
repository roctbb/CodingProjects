<?php

namespace App\Support;

use App\User;

class ScreenPet
{
    public static function forUser(?User $user): ?array
    {
        // Most course visitors have no equipped pet. Avoid loading unrelated achievements for them.
        if (!$user || empty($user->learning_avatar_config['equipped']['pet_right'])) return null;
        $key = $user ? $user->activeLearningAvatarPetKey() : null;
        $manifest = $key ? config('screen_pets.' . $key) : null;
        if (!$manifest || !is_file(public_path($manifest))) {
            return null;
        }

        $animation = json_decode(file_get_contents(public_path($manifest)), true);
        if (!is_array($animation) || empty($animation['frames']) || empty($animation['atlas'])) {
            return null;
        }

        return array_merge($animation, [
            'key' => $key,
            'userId' => (int) $user->id,
            'url' => asset($animation['atlas']),
        ]);
    }
}
