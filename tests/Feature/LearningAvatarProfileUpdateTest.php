<?php

namespace Tests\Feature;

use App\CourseActivity;
use App\User;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearningAvatarProfileUpdateTest extends TestCase
{
    use WithoutMiddleware;

    private $originalDefaultConnection;
    private $originalSqliteDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = config('database.default');
        $this->originalSqliteDatabase = config('database.connections.sqlite.database');

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('role')->default('student');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('avatar_frame')->nullable();
            $table->json('avatar_frame_config')->nullable();
            $table->timestamp('avatar_frame_expires_at')->nullable();
            $table->json('learning_avatar_config')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('coin_transactions', function ($table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('price');
            $table->string('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('achievements', function ($table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->string('status')->default('published');
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('courses', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('state')->default('started');
            $table->timestamps();
        });

        Schema::create('course_students', function ($table) {
            $table->integer('user_id')->unsigned();
            $table->integer('course_id')->unsigned();
        });

        Schema::create('course_activities', function ($table) {
            $table->increments('id');
            $table->integer('course_id')->unsigned();
            $table->integer('lesson_id')->unsigned()->nullable();
            $table->integer('step_id')->unsigned()->nullable();
            $table->integer('task_id')->unsigned()->nullable();
            $table->integer('solution_id')->unsigned()->nullable();
            $table->integer('user_id')->unsigned()->nullable();
            $table->string('type', 64);
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('course_activities');
        Schema::dropIfExists('course_students');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('coin_transactions');
        Schema::dropIfExists('users');
        DB::disconnect('sqlite');

        config([
            'database.default' => $this->originalDefaultConnection,
            'database.connections.sqlite.database' => $this->originalSqliteDatabase,
        ]);

        parent::tearDown();
    }

    public function testLearningAvatarUpdateWithoutManifestKeepsRoomSystemDefault()
    {
        $user = $this->createUser();
        $this->be($user);

        $this->post('/insider/profile/' . $user->id . '/learning-avatar', [
            'appearance' => [
                'gender' => 'girl',
                'grade' => 8,
            ],
            'equipped' => [
                'desk_center' => 'basic_laptop',
            ],
        ])->assertRedirect('/insider/profile/' . $user->id . '#learning-avatar');

        $config = $user->fresh()->learning_avatar_config;

        $this->assertSame('room-system', $config['manifest']);
        $this->assertSame('girl', $config['appearance']['gender']);
        $this->assertSame(8, $config['appearance']['grade']);
        $this->assertSame('basic_laptop', $config['equipped']['desk_center']);
    }

    public function testLearningAvatarUpdateRejectsUnknownManifest()
    {
        $user = $this->createUser([
            'learning_avatar_config' => [
                'manifest' => 'room-system',
                'owned' => ['basic_laptop'],
                'appearance' => ['gender' => 'boy'],
                'equipped' => ['desk_center' => 'basic_laptop'],
            ],
        ]);
        $this->be($user);

        $this->from('/insider/profile/' . $user->id . '#learning-avatar')
            ->post('/insider/profile/' . $user->id . '/learning-avatar', [
                'manifest' => 'missing-manifest',
                'appearance' => [
                    'gender' => 'girl',
                    'grade' => 10,
                ],
                'equipped' => [
                    'desk_center' => 'basic_laptop',
                ],
            ])
            ->assertRedirect('/insider/profile/' . $user->id . '#learning-avatar');

        $config = $user->fresh()->learning_avatar_config;

        $this->assertSame('room-system', $config['manifest']);
        $this->assertSame('boy', $config['appearance']['gender']);
    }

    public function testLearningAvatarUpdateDoesNotBuyLockedItemsFromProfile()
    {
        $user = $this->createUser([
            'learning_avatar_config' => [
                'manifest' => 'room-system',
                'owned' => ['basic_laptop'],
                'equipped' => ['desk_center' => 'basic_laptop'],
            ],
        ]);
        $this->be($user);

        DB::table('coin_transactions')->insert([
            'user_id' => $user->id,
            'price' => 1000,
            'comment' => 'Initial balance',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/insider/profile/' . $user->id . '/learning-avatar', [
            'manifest' => 'room-system',
            'equipped' => [
                'desk_center' => 'premium_keyboard',
                'pet_right' => 'learning_bot_pet',
            ],
        ])->assertRedirect('/insider/profile/' . $user->id . '#learning-avatar');

        $config = $user->fresh()->learning_avatar_config;

        $this->assertNotContains('premium_keyboard', $config['owned']);
        $this->assertNotContains('learning_bot_pet', $config['owned']);
        $this->assertSame('basic_laptop', $config['equipped']['desk_center']);
        $this->assertArrayNotHasKey('pet_right', $config['equipped']);
        $this->assertSame(1, DB::table('coin_transactions')->where('user_id', $user->id)->count());
    }

    public function testCustomAvatarFrameEditorOpensFromMarketModal()
    {
        $user = $this->createUser([
            'learning_avatar_config' => [
                'manifest' => 'room-system',
            ],
        ]);

        DB::table('coin_transactions')->insert([
            'user_id' => $user->id,
            'price' => 1000,
            'comment' => 'Initial balance',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $items = $user->digitalStoreItems()
            ->where('type', 'custom_avatar_frame')
            ->values();

        $html = view('market.partials.digital_goods_grid', [
            'items' => $items,
            'gridId' => 'market-digital-grid',
            'user' => $user,
        ])->render();

        $this->assertStringContainsString('data-market-frame-modal-open', $html);
        $this->assertStringContainsString('Настроить', $html);
        $this->assertStringContainsString('data-market-frame-modal hidden', $html);
        $this->assertStringContainsString('market-digital-frame-modal__footer', $html);
        $this->assertStringContainsString('data-frame-static-cost="' . $user->customAvatarFrameCost(false) . '"', $html);
        $this->assertStringContainsString('data-frame-animated-cost="' . $user->customAvatarFrameCost(true) . '"', $html);
        $this->assertStringContainsString('data-frame-price>' . $user->customAvatarFrameCost(true) . '</span>', $html);
        $this->assertStringContainsString('name="avatar_frame_config[type]"', $html);
    }

    public function testCustomAvatarFrameStaticAndAnimatedPricesDiffer()
    {
        $user = $this->createUser();
        $this->be($user);

        DB::table('coin_transactions')->insert([
            'user_id' => $user->id,
            'price' => 120,
            'comment' => 'Initial balance',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/insider/profile/' . $user->id . '/avatar-frame', [
            'avatar_frame' => 'custom',
            'avatar_frame_config' => [
                'animated' => '0',
                'colors' => ['#22d3ee', '#8b5cf6'],
            ],
        ])->assertRedirect('/insider/profile/' . $user->id);

        $this->assertSame(20, $user->fresh()->balance());
        $this->assertFalse((bool) $user->fresh()->avatar_frame_config['animated']);

        $animatedUser = $this->createUser();
        $this->be($animatedUser);

        DB::table('coin_transactions')->insert([
            'user_id' => $animatedUser->id,
            'price' => 120,
            'comment' => 'Initial balance',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->from('/insider/market')
            ->post('/insider/profile/' . $animatedUser->id . '/avatar-frame', [
                'avatar_frame' => 'custom',
                'avatar_frame_config' => [
                    'animated' => '1',
                    'colors' => ['#22d3ee', '#8b5cf6'],
                ],
            ])->assertRedirect('/insider/market');

        $this->assertNull($animatedUser->fresh()->avatar_frame);
        $this->assertSame(120, $animatedUser->fresh()->balance());
    }

    public function testTungTungTungSahurDailyAutoBoosterIsConsumedOncePerDay()
    {
        $user = $this->createUser([
            'learning_avatar_config' => [
                'manifest' => 'room-system',
                'owned' => ['tung_tung_tung_sahur_pet'],
                'equipped' => [
                    'pet_right' => 'tung_tung_tung_sahur_pet',
                ],
            ],
        ]);

        $this->assertTrue($user->canUseDailyAutoXpBoosterToday());
        $this->assertTrue($user->consumeDailyAutoXpBoosterToday());
        $this->assertFalse($user->fresh()->canUseDailyAutoXpBoosterToday());
    }

    public function testDailyPetPulseActionIsRecordedOnlyOnceForPrimaryActiveCourse()
    {
        $user = $this->createUser();
        DB::table('courses')->insert([
            ['id' => 1, 'name' => 'Old course', 'state' => 'started', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Draft course', 'state' => 'draft', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Fresh course', 'state' => 'started', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('course_students')->insert([
            ['user_id' => $user->id, 'course_id' => 1],
            ['user_id' => $user->id, 'course_id' => 2],
            ['user_id' => $user->id, 'course_id' => 3],
        ]);

        CourseActivity::recordPetActionForActiveCourse($user, 'daily_coin_gift', [
            'pet_key' => 'cheremsha_pet',
            'pet_name' => 'Черемша',
            'amount' => 3,
        ]);

        $activities = CourseActivity::where('type', CourseActivity::TYPE_PET_ACTION)->get();

        $this->assertCount(1, $activities);
        $this->assertSame(3, (int) $activities->first()->course_id);
        $this->assertSame('Fresh course', $activities->first()->payload['course_name']);
    }

    private function createUser(array $attributes = [])
    {
        $user = new User(array_merge([
            'name' => 'Student',
            'role' => 'student',
            'email' => uniqid('student_', true) . '@example.test',
            'password' => bcrypt('secret'),
        ], $attributes));
        $user->save();

        return $user;
    }
}
