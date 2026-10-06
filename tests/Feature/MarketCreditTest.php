<?php

namespace Tests\Feature;

use App\CoinTransaction;
use App\Notifications\NewCoinTransaction;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarketCreditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('student');
            $table->boolean('birthday_hidden')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('coin_transactions', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->integer('price');
            $table->string('comment')->nullable();
            $table->timestamps();
        });
        Schema::create('courses', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('state');
        });

        Notification::fake();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function testAdminCanOpenFormWithSearchableRecipients(): void
    {
        $admin = $this->createUser('admin');
        $recipient = $this->createUser();

        $this->actingAs($admin)->get('/insider/market/credit')
            ->assertOk()
            ->assertSee('Массовое начисление GC')
            ->assertSee('data-enhanced-multiselect', false)
            ->assertSee($recipient->email);
    }

    public function testAdminCreditsEachSelectedRecipientAndPreservesExistingBalance(): void
    {
        $admin = $this->createUser('admin');
        $first = $this->createUser();
        $second = $this->createUser();
        CoinTransaction::register($first->id, 30, 'Начальный баланс');
        Notification::fake();

        $this->actingAs($admin)->post('/insider/market/credit', [
            'amount' => 50,
            'recipients' => [$first->id, $second->id],
            'comment' => 'За участие в олимпиаде',
        ])->assertRedirect('/insider/market');

        $this->assertEquals(80, $first->balance());
        $this->assertEquals(50, $second->balance());
        $this->assertEquals(0, $admin->balance());
        foreach ([$first, $second] as $recipient) {
            $this->assertDatabaseHas('coin_transactions', [
                'user_id' => $recipient->id,
                'price' => 50,
                'comment' => 'За участие в олимпиаде',
            ]);
            Notification::assertSentTo($recipient, NewCoinTransaction::class);
        }
        Notification::assertCount(2);
    }

    public function testNonAdminsCannotOpenFormOrCreditUsers(): void
    {
        foreach (['student', 'teacher', 'novice'] as $role) {
            $user = $this->createUser($role);
            $this->actingAs($user)->get('/insider/market/credit')->assertForbidden();
            $this->post('/insider/market/credit', [
                'amount' => 50,
                'recipients' => [$user->id],
                'comment' => 'Бонус',
            ])->assertForbidden();
        }

        $this->assertDatabaseCount('coin_transactions', 0);
        Notification::assertNothingSent();
    }

    public function testGuestsMustLogIn(): void
    {
        $this->get('/insider/market/credit')->assertRedirect('/login');
        $this->post('/insider/market/credit')->assertRedirect('/login');
    }

    public function testInvalidBatchesDoNotCreditAnyone(): void
    {
        $admin = $this->createUser('admin');
        $recipient = $this->createUser();
        $this->actingAs($admin);
        $valid = ['amount' => 50, 'recipients' => [$recipient->id], 'comment' => 'Бонус'];

        $invalid = [
            ['amount' => 0],
            ['amount' => -10],
            ['amount' => 1.5],
            ['amount' => 10001],
            ['amount' => null],
            ['recipients' => []],
            ['recipients' => 'invalid'],
            ['recipients' => [$recipient->id, 99999]],
            ['recipients' => [$recipient->id, (string) $recipient->id]],
            ['comment' => ''],
            ['comment' => str_repeat('a', 256)],
        ];

        foreach ($invalid as $overrides) {
            $this->from('/insider/market/credit')
                ->post('/insider/market/credit', array_merge($valid, $overrides))
                ->assertRedirect('/insider/market/credit')
                ->assertSessionHasErrors()
                ->assertSessionHas('_old_input');
            $this->assertDatabaseCount('coin_transactions', 0);
        }

        Notification::assertNothingSent();
    }

    public function testDatabaseFailureRollsBackEntireBatchBeforeNotifications(): void
    {
        $admin = $this->createUser('admin');
        $first = $this->createUser();
        $second = $this->createUser();
        DB::statement('CREATE TRIGGER fail_second_credit BEFORE INSERT ON coin_transactions '
            . 'WHEN NEW.user_id = ' . (int) $second->id . " BEGIN SELECT RAISE(ABORT, 'Test failure'); END");

        $this->actingAs($admin)->post('/insider/market/credit', [
            'amount' => 50,
            'recipients' => [$first->id, $second->id],
            'comment' => 'Бонус',
        ])->assertStatus(500);

        $this->assertDatabaseCount('coin_transactions', 0);
        Notification::assertNothingSent();
    }

    public function testNotificationFailureDoesNotUndoCreditsOrFailTheRequest(): void
    {
        $admin = $this->createUser('admin');
        $first = $this->createUser();
        $second = $this->createUser();
        Notification::shouldReceive('send')->twice()->andThrow(new \RuntimeException('Delivery failed'));

        $this->actingAs($admin)->post('/insider/market/credit', [
            'amount' => 50,
            'recipients' => [$first->id, $second->id],
            'comment' => 'Бонус',
        ])->assertRedirect('/insider/market');

        $this->assertEquals(50, $first->balance());
        $this->assertEquals(50, $second->balance());
    }

    public function testMarketCreditButtonIsOnlyVisibleToAdmins(): void
    {
        foreach (['admin', 'teacher', 'student'] as $role) {
            $user = $this->createUser($role);
            $this->actingAs($user);
            $html = view('market.index', [
                'user' => $user,
                'goods' => collect(),
                'digitalGoods' => collect(),
                'auctions' => collect(),
                'archive' => collect(),
                'active_orders' => collect(),
                'shipped_orders' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25),
                'canManageMarket' => $role !== 'student',
            ])->render();

            if ($role === 'admin') {
                $this->assertStringContainsString('/insider/market/credit', $html);
            } else {
                $this->assertStringNotContainsString('/insider/market/credit', $html);
            }
        }
    }

    private function createUser(string $role = 'student'): User
    {
        $user = User::create([
            'name' => 'Получатель',
            'email' => uniqid('recipient_') . '@example.test',
            'password' => 'unused',
            'role' => $role,
            'email_verified_at' => now(),
        ]);
        $user->setRelation('courses', collect());
        $user->setRelation('managed_courses', collect());

        return $user;
    }
}
