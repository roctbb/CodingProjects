<?php

namespace Tests\Unit;

use App\Services\SilaederOidcProfile;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SilaederOidcProfileTest extends TestCase
{
    public static function invalidProfiles(): array
    {
        return [
            [[]],
            [['gender' => null, 'birthdate' => null, 'grade' => null]],
            [['gender' => [], 'birthdate' => [], 'grade' => []]],
            [['gender' => 'unknown', 'birthdate' => '2011-02-29', 'grade' => true]],
            [['birthdate' => '2012-02-30', 'grade' => 0]],
            [['birthdate' => '2012-00-01', 'grade' => 12]],
            [['birthdate' => '0000-01-01', 'grade' => 8.5]],
            [['birthdate' => '2012-01', 'grade' => '8']],
        ];
    }

    #[DataProvider('invalidProfiles')]
    public function testInvalidOrMissingClaimsDoNotOverwriteLocalData(array $profile): void
    {
        $this->assertSame([], SilaederOidcProfile::attributes($profile));
    }

    public static function schoolDates(): array
    {
        return [['2026-05-31', 2018], ['2026-06-01', 2019], ['2026-10-09', 2019]];
    }

    #[DataProvider('schoolDates')]
    public function testGradeUsesTheLocalSchoolYearBoundary(string $date, int $expectedYear): void
    {
        Carbon::setTestNow($date);
        try {
            $this->assertSame([
                'gender' => 'girl', 'birthday' => '2012-02-29', 'grade_year' => $expectedYear,
            ], SilaederOidcProfile::attributes([
                'gender' => 'female', 'birthdate' => '2012-02-29', 'grade' => 8,
            ]));
        } finally {
            Carbon::setTestNow();
        }
    }
}
