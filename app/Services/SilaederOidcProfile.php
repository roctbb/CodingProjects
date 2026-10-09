<?php

namespace App\Services;

use App\User;

class SilaederOidcProfile
{
    public static function attributes(array $userinfo): array
    {
        $attributes = [];
        $gender = $userinfo['gender'] ?? null;
        if (is_string($gender) && in_array($gender, ['male', 'female', 'boy', 'girl'], true)) {
            $attributes['gender'] = ['male' => 'boy', 'female' => 'girl'][$gender] ?? $gender;
        }

        $birthday = $userinfo['birthdate'] ?? null;
        if (is_string($birthday) && preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $birthday, $parts)
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            $attributes['birthday'] = $birthday;
        }

        $grade = $userinfo['grade'] ?? null;
        if (is_int($grade) && $grade >= 1 && $grade <= 11) {
            $user = new User();
            $user->setGrade($grade);
            $attributes['grade_year'] = $user->grade_year;
        }

        return $attributes;
    }
}
