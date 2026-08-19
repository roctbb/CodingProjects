<?php

namespace Tests\Unit;

use App\User;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class UserExperienceLeaderboardTest extends TestCase
{
    #[DataProvider('eligibilityProvider')]
    public function testOnlyVisibleLearnersAreEligible(
        string $role,
        bool $isTeacher,
        bool $isHidden,
        bool $expected
    ): void {
        $user = (new User())->forceFill([
            'role' => $role,
            'is_teacher' => $isTeacher,
            'is_hidden' => $isHidden,
        ]);

        $this->assertSame($expected, $user->isExperienceLeaderboardEligible());
    }

    public static function eligibilityProvider(): array
    {
        return [
            'student' => ['student', false, false, true],
            'novice' => ['novice', false, false, true],
            'teacher role' => ['teacher', false, false, false],
            'legacy teacher flag' => ['student', true, false, false],
            'admin' => ['admin', false, false, false],
            'hidden learner' => ['student', false, true, false],
        ];
    }
}
