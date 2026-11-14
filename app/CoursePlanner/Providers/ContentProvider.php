<?php

namespace App\CoursePlanner\Providers;

use App\Course;
use App\User;

interface ContentProvider
{
    public function manifest(Course $course, User $actor): array;
    public function catalog(Course $course, User $actor, array $query): array;
    public function resource(Course $course, User $actor, string $type, string $externalId): array;
}
