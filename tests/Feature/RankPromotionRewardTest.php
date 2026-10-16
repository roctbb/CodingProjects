<?php

namespace Tests\Feature;

use App\CoinTransaction;
use App\Notifications\NewCoinTransaction;
use App\User;
use Database\Seeders\RanksSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RankPromotionRewardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
        Notification::fake();

        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, rank_id INTEGER);
            CREATE TABLE ranks (id INTEGER PRIMARY KEY, name TEXT, "from" INTEGER, "to" INTEGER);
            CREATE TABLE solutions (id INTEGER PRIMARY KEY, user_id INTEGER, task_id INTEGER, mark INTEGER);
            CREATE TABLE coin_transactions (id INTEGER PRIMARY KEY, user_id INTEGER, price INTEGER, comment TEXT, created_at TEXT, updated_at TEXT);
            INSERT INTO users VALUES (1, 'Student', NULL);
            INSERT INTO solutions VALUES (1, 1, 1, 0);
            SQL);
        (new RanksSeeder())->run();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public static function promotionRewards(): array
    {
        return [
            'Ефрейтор' => [50, 4],
            'Младший сержант' => [150, 6],
            'Капитан' => [3600, 24],
            'Адмирал флота' => [11000, 36],
        ];
    }

    #[DataProvider('promotionRewards')]
    public function testPromotionAwardsReducedRewardAndNotifiesTheCorrectAmount(int $threshold, int $coins): void
    {
        DB::table('solutions')->where('id', 1)->update(['mark' => $threshold - 1]);
        $user = User::findOrFail(1);

        $this->changeScore($user, $threshold);

        $this->assertDatabaseCount('coin_transactions', 1);
        $this->assertDatabaseHas('coin_transactions', [
            'user_id' => $user->id,
            'price' => $coins,
            'comment' => 'Rank bonus #' . $user->rank()->id,
        ]);
        Notification::assertSentTo($user, NewCoinTransaction::class, function ($notification) use ($user, $coins) {
            return $notification->toArray($user)['text'] === '🌟 Бонус за новое звание: +' . $coins . ' GC';
        });
    }

    public function testDuplicateRankAliasesDoNotInflateRewards(): void
    {
        DB::table('ranks')->insert([
            ['id' => 19, 'name' => 'Мл. сержант', 'from' => 150, 'to' => 300],
            ['id' => 20, 'name' => 'Ст. сержант', 'from' => 700, 'to' => 1100],
            ['id' => 21, 'name' => 'Ст. прапорщик', 'from' => 1900, 'to' => 2300],
            ['id' => 22, 'name' => 'Мл. лейтенант', 'from' => 2300, 'to' => 2700],
            ['id' => 23, 'name' => 'Ст. лейтенант', 'from' => 3100, 'to' => 3600],
        ]);
        $user = User::findOrFail(1);

        $this->changeScore($user, 3100);
        $this->assertSame(22, CoinTransaction::firstOrFail()->price);

        $this->changeScore($user, 11000);
        $this->assertSame(36, CoinTransaction::orderByDesc('id')->firstOrFail()->price);
        $this->assertDatabaseCount('coin_transactions', 2);
    }

    public function testJumpingSeveralRanksAwardsOnlyTheFinalRank(): void
    {
        $user = User::findOrFail(1);

        $this->changeScore($user, 700);

        $this->assertDatabaseCount('coin_transactions', 1);
        $this->assertSame(10, $user->balance());
        $this->assertSame('Rank bonus #5', CoinTransaction::firstOrFail()->comment);
    }

    public function testExistingRewardKeepsItsOriginalAmountAndIsNotPaidAgain(): void
    {
        DB::table('coin_transactions')->insert([
            'user_id' => 1,
            'price' => 15,
            'comment' => 'Rank bonus #3',
        ]);
        $user = User::findOrFail(1);

        $this->changeScore($user, 150);

        $this->assertDatabaseCount('coin_transactions', 1);
        $this->assertSame(15, $user->balance());
        Notification::assertNotSentTo($user, NewCoinTransaction::class);
    }

    public function testUnchangedRankAndDemotionDoNotPayAndRegainingRankDoesNotPayTwice(): void
    {
        $user = User::findOrFail(1);

        $this->changeScore($user, 150);
        $this->changeScore($user, 151);
        $this->changeScore($user, 149);
        $this->changeScore($user, 150);

        $this->assertDatabaseCount('coin_transactions', 1);
        $this->assertSame(6, $user->balance());
        Notification::assertSentToTimes($user, NewCoinTransaction::class, 1);
    }

    private function changeScore(User $user, int $score): void
    {
        $oldRank = $user->rank();
        DB::table('solutions')->where('id', 1)->update(['mark' => $score]);
        $user->rescore();
        $user->awardRankPromotionIfNeeded($oldRank);
    }
}
