<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommunityPaginationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (
                id INTEGER PRIMARY KEY, name TEXT, email TEXT, password TEXT, rank_id INTEGER,
                role TEXT DEFAULT 'student', is_hidden BOOLEAN DEFAULT 0, is_teacher BOOLEAN DEFAULT 0,
                is_trainee BOOLEAN DEFAULT 0, birthday_hidden BOOLEAN DEFAULT 0,
                custom_title TEXT, custom_title_expires_at TEXT, email_verified_at TEXT
            );
            CREATE TABLE ranks (id INTEGER PRIMARY KEY, name TEXT, "from" INTEGER, "to" INTEGER);
            CREATE TABLE solutions (id INTEGER PRIMARY KEY, user_id INTEGER, task_id INTEGER, mark INTEGER);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, name TEXT, state TEXT);
            CREATE TABLE course_students (course_id INTEGER, user_id INTEGER);
            CREATE TABLE course_teachers (course_id INTEGER, user_id INTEGER);
            INSERT INTO ranks VALUES (1, 'First', 0, 100), (2, 'Second', 100, 200), (3, 'Third', 200, 10000);
            SQL);
        $actor = $this->person('Viewer', ['is_hidden' => true, 'email_verified_at' => now()]);
        $this->actingAs(User::findOrFail($actor));
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function testOnlyRequestedPageIsLoadedAndRendered(): void
    {
        $ids = [];
        for ($i = 1; $i <= 25; $i++) $ids[] = $this->person(sprintf('Student %03d', $i));
        $retrieved = [];
        User::retrieved(function ($user) use (&$retrieved) { $retrieved[] = $user->id; });

        $response = $this->get('/insider/community?page=2')->assertOk()->assertSee('21–25 из 25');
        $users = $response->viewData('users');
        $this->assertSame(20, $users->perPage());
        $this->assertSame(25, $users->total());
        $this->assertSame(array_slice($ids, 20, 20), $users->pluck('id')->all());
        $this->assertSame($users->pluck('id')->all(), $retrieved);
        $this->assertSame(5, substr_count($response->getContent(), 'data-community-user'));
        $this->get('/insider/community?page=3')->assertRedirect('/insider/community?page=2');
    }

    public function testSearchFindsPeopleOutsideFirstPageAndKeepsFiltersInLinks(): void
    {
        for ($i = 1; $i <= 25; $i++) $this->person(sprintf('Common student %03d', $i));
        $target = $this->person('Zoe Faraway');
        $this->get('/insider/community?q=faraway')->assertOk()->assertViewHas('users', fn ($users) => $users->total() === 1 && $users->first()->id === $target);

        $response = $this->get('/insider/community?q=Common&role=student')->assertOk();
        parse_str(parse_url($response->viewData('users')->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame(['q' => 'Common', 'role' => 'student', 'page' => '2'], $query);
        $this->get('/insider/community?'.http_build_query($query))->assertOk()
            ->assertViewHas('search', 'Common')->assertViewHas('role', 'student')
            ->assertViewHas('users', fn ($users) => $users->currentPage() === 2 && $users->total() === 25);
    }

    public function testXpOrderingUsesBestAttemptsAndManualRankBeforePagination(): void
    {
        $zero = $this->person('A zero');
        $manual = $this->person('B manual', ['rank_id' => 2]);
        $best = $this->person('C best');
        $hidden = $this->person('Hidden', ['is_hidden' => true]);
        DB::table('solutions')->insert([
            ['user_id' => $best, 'task_id' => 1, 'mark' => 100],
            ['user_id' => $best, 'task_id' => 1, 'mark' => 150],
            ['user_id' => $best, 'task_id' => 2, 'mark' => 100],
            ['user_id' => $manual, 'task_id' => 1, 'mark' => 1],
            ['user_id' => $hidden, 'task_id' => 1, 'mark' => 9999],
        ]);
        $users = $this->get('/insider/community')->assertOk()->viewData('users');
        $this->assertSame([$best, $manual, $zero], $users->pluck('id')->all());
        $this->assertSame([250, 199, 0], $users->map(fn ($user) => $user->score())->all());
        $this->assertSame([3, 2, 1], $users->map(fn ($user) => $user->rank()->id)->all());
    }

    public function testRoleFilterIncludesFlaggedTeachersAndCombinesWithSearch(): void
    {
        $teachers = [
            $this->person('Instructor teacher', ['role' => 'teacher']),
            $this->person('Instructor admin', ['role' => 'admin']),
            $this->person('Instructor flag', ['is_teacher' => true]),
        ];
        $student = $this->person('Instructor student');
        $this->person('Instructor hidden', ['is_hidden' => true, 'role' => 'teacher']);
        $users = $this->get('/insider/community?q=Instructor&role=teacher')->assertOk()->viewData('users');
        $this->assertEqualsCanonicalizing($teachers, $users->pluck('id')->all());
        $this->get('/insider/community?q=Instructor&role=student')->assertOk()
            ->assertViewHas('users', fn ($users) => $users->total() === 1 && $users->first()->id === $student);
        $this->get('/insider/community?q=Missing&role=teacher')->assertOk()->assertSee('Ничего не найдено')
            ->assertViewHas('users', fn ($users) => $users->total() === 0);
    }

    public function testRankXpAndActiveTitlesAreSearchable(): void
    {
        $manual = $this->person('Manual', ['rank_id' => 2]);
        $automatic = $this->person('Automatic');
        DB::table('solutions')->insert(['user_id' => $automatic, 'task_id' => 1, 'mark' => 120]);
        $active = $this->person('Active', ['custom_title' => 'Puzzle Wizard', 'custom_title_expires_at' => now()->addDay()]);
        $this->person('Expired', ['custom_title' => 'Puzzle Wizard', 'custom_title_expires_at' => now()->subDay()]);
        $this->assertEqualsCanonicalizing([$manual, $automatic], $this->get('/insider/community?q=second')->assertOk()->viewData('users')->pluck('id')->all());
        $this->get('/insider/community?q=199%20XP')->assertOk()->assertViewHas('users', fn ($users) => $users->pluck('id')->all() === [$manual]);
        $this->get('/insider/community?q=wizard')->assertOk()->assertViewHas('users', fn ($users) => $users->pluck('id')->all() === [$active]);
    }

    public function testSearchRecognizesBothSpellingsOfDisplayedTraineeRole(): void
    {
        $trainee = $this->person('New colleague', ['is_trainee' => true]);
        $this->person('Hidden colleague', ['is_trainee' => true, 'is_hidden' => true]);
        $this->person('Other colleague');
        foreach (['стажер', 'Стажёр'] as $search) {
            $this->get('/insider/community?q='.urlencode($search))->assertOk()
                ->assertViewHas('users', fn ($users) => $users->pluck('id')->all() === [$trainee]);
        }
    }

    public function testSearchTreatsWildcardsLiterallyAndDoesNotExposeHiddenPeople(): void
    {
        $literal = $this->person('A%_!person');
        $this->person('Another person');
        $this->person('Hidden%_!person', ['is_hidden' => true]);
        $this->get('/insider/community?q='.urlencode('%_!'))->assertOk()
            ->assertViewHas('users', fn ($users) => $users->pluck('id')->all() === [$literal]);
        $this->get('/insider/community?q='.urlencode("%' OR 1=1 --"))->assertOk()
            ->assertViewHas('users', fn ($users) => $users->total() === 0);
    }

    public function testLeaderBadgesStayGlobalAcrossPagesAndFilters(): void
    {
        $leaders = [];
        for ($i = 1; $i <= 24; $i++) {
            $id = $this->person(sprintf('Student %02d', $i));
            DB::table('solutions')->insert(['user_id' => $id, 'task_id' => 1, 'mark' => 100 - $i]);
            if ($i <= 3) $leaders[] = $id;
        }
        foreach ([['is_hidden' => true], ['role' => 'teacher'], ['is_teacher' => true]] as $attributes) {
            $id = $this->person('Excluded leader', $attributes);
            DB::table('solutions')->insert(['user_id' => $id, 'task_id' => 1, 'mark' => 9999]);
        }
        foreach (['/insider/community?page=2', '/insider/community?q=Student%2024'] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertSame($leaders, $response->viewData('topUserIds')->all());
            $this->assertStringNotContainsString('title="Место по опыту среди учеников"', $response->getContent());
        }
    }

    public function testComputedZeroDoesNotReadAnOldXpCache(): void
    {
        $id = $this->person('Zero score');
        Cache::put("user:{$id}:score", 999, 3600);
        $user = $this->get('/insider/community')->assertOk()->viewData('users')->first();
        $this->assertSame(0, $user->score());
        $this->assertSame(1, $user->rank()->id);
    }

    public function testOutOfRangePageRedirectsToLastPageWithFilters(): void
    {
        $this->person('Common student');
        $this->get('/insider/community?q=Common&role=student&page=999')
            ->assertRedirect('/insider/community?q=Common&role=student&page=1');
    }

    private function person(string $name, array $attributes = []): int
    {
        return DB::table('users')->insertGetId(array_merge(['name' => $name, 'email' => uniqid('person_').'@example.test'], $attributes));
    }
}
