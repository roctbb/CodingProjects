<?php

namespace Tests\Feature;

use App\CoinTransaction;
use App\Jobs\GenerateRoomCharacter;
use App\RoomCharacterGeneration;
use App\Services\RoomCharacterGenerator;
use App\User;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoomCharacterGenerationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'filesystems.default' => 'local', 'learning_avatar.weather.enabled' => false,
            'ai.provider' => 'gateway', 'ai.commands.room_character.provider' => null,
            'ai.commands.room_character.model' => 'character-model',
            'ai.providers.gateway' => ['endpoint' => 'https://gateway.example/responses', 'key' => 'gateway-key'],
            'ai.providers.openai' => ['endpoint' => 'https://openai.example/responses', 'key' => 'openai-key'],
        ]);
        DB::purge('sqlite');
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default('student');
            $table->boolean('birthday_hidden')->default(false);
            $table->string('image')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('gender')->nullable();
            $table->json('learning_avatar_config')->nullable();
            $table->string('custom_title')->nullable();
            $table->timestamp('custom_title_expires_at')->nullable();
            $table->string('avatar_frame')->nullable();
            $table->json('avatar_frame_config')->nullable();
            $table->timestamp('avatar_frame_expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('coin_transactions', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->integer('price');
            $table->string('comment')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_14_140000_create_room_character_generations_table.php'))->up();
        Storage::fake('local');
        Storage::fake('room_character_sources');
        Notification::fake();
        Bus::fake([GenerateRoomCharacter::class]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        RoomCharacterGeneration::flushEventListeners();
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function user(int $balance = 200, bool $avatar = true): User
    {
        $user = new User();
        $user->forceFill(['name' => 'Student', 'email' => Str::uuid() . '@example.com', 'email_verified_at' => now(),
            'learning_avatar_config' => ['manifest' => 'room-system', 'appearance' => ['grade' => 7]]])->save();
        if ($avatar) {
            $user->image = 'user_avatars/' . $user->id . '.png';
            Storage::put($user->image, $this->png());
            $user->save();
        }
        CoinTransaction::register($user->id, $balance, 'Initial balance');
        return $user;
    }

    private function png(bool $transparent = true, bool $empty = false): string
    {
        $image = imagecreatetruecolor(120, 180);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, $transparent ? 127 : 0));
        if (!$empty) {
            imagefilledrectangle($image, 30, 10, 90, 160, imagecolorallocatealpha($image, 40, 100, 230, 0));
        }
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        return $bytes;
    }

    private function purchase(User $user, ?string $key = null): RoomCharacterGeneration
    {
        return app(RoomCharacterGenerator::class)->purchase($user->id, $key ?: (string) Str::uuid());
    }

    public function testLargeProfilePhotoIsResizedBeforePurchaseWithoutChangingTheOriginal(): void
    {
        $user = $this->user();
        $image = imagecreatetruecolor(3693, 5540);
        imagefill($image, 0, 0, imagecolorallocate($image, 80, 120, 160));
        ob_start();
        imagejpeg($image, null, 85);
        $original = ob_get_clean();
        unset($image);
        Storage::put($user->image, $original);

        $this->actingAs($user)->postJson($this->url($user), ['request_id' => (string) Str::uuid()])
            ->assertStatus(202)->assertJsonPath('balance', 100);

        $generation = RoomCharacterGeneration::firstOrFail();
        $snapshot = Storage::disk('room_character_sources')->get($generation->source_path);
        $info = getimagesizefromstring($snapshot);
        $this->assertSame([683, 1024, IMAGETYPE_PNG], array_slice($info, 0, 3));
        $this->assertSame($original, Storage::get($user->image));
        Bus::assertDispatchedTimes(GenerateRoomCharacter::class, 1);
        Http::assertNothingSent();
    }

    private function url(User $user): string
    {
        return '/insider/profile/' . $user->id . '/room-character';
    }

    private function fakeImage(string $provider = 'gateway', ?string $bytes = null): void
    {
        Http::swap(new Factory());
        Http::preventStrayRequests();
        $body = ['status' => 'completed', 'output' => [['type' => 'image_generation_call', 'result' => base64_encode($bytes ?? $this->png())]]];
        Http::fake(fn () => Http::response($provider === 'gateway' ? ['result' => $body] : $body));
    }

    public function testImagesApiInstallsCharacterUsingAvatarAndStaticReferenceWithOneCharge(): void
    {
        config(['ai.provider' => 'openai', 'ai.providers.openai.image_api' => 'images',
            'ai.providers.openai.images_url' => 'https://openai.example/v1/images',
            'ai.commands.room_character.model' => 'gpt-image-2']);
        $user = $this->user();
        $generation = $this->purchase($user);
        $sourcePath = $generation->source_path;
        $avatar = Storage::disk('room_character_sources')->get($sourcePath);
        $reference = file_get_contents(public_path($generation->reference_path));
        Http::fake(function (Request $request) use ($avatar, $reference) {
            $this->assertSame('https://openai.example/v1/images/edits', $request->url());
            $this->assertTrue($request->hasFile('image[]', $avatar, 'reference-1.png'));
            $this->assertTrue($request->hasFile('image[]', $reference, 'reference-2.png'));
            return Http::response(['data' => [['b64_json' => base64_encode($this->png())]]]);
        });
        app(RoomCharacterGenerator::class)->generate($generation->id);
        app(RoomCharacterGenerator::class)->generate($generation->id);
        $generation->refresh();
        $this->assertSame('succeeded', $generation->status);
        $this->assertSame($generation->id, $user->fresh()->room_character_generation_id);
        $this->assertSame(100, (int) $user->balance());
        $this->assertNull($generation->refund_transaction_id);
        Storage::assertExists($generation->result_path);
        $this->assertSame('public', Storage::getVisibility($generation->result_path));
        $this->assertSame(0755, fileperms(Storage::path('room-characters')) & 0777);
        Storage::disk('room_character_sources')->assertMissing($sourcePath);
        Http::assertSentCount(1);
    }

    public function testSucceededGenerationReportsUnavailableImageWithoutAnotherCharge(): void
    {
        $user = $this->user();
        $generation = $this->purchase($user);
        $this->fakeImage();
        app(RoomCharacterGenerator::class)->generate($generation->id);
        $generation->refresh();
        $url = route('room-character.show', [$user->id, $generation->id]);
        $this->actingAs($user)->getJson($url)->assertOk()
            ->assertJsonPath('message', 'Персонаж готов и установлен в комнате.');
        $bytes = Storage::get($generation->result_path);
        Storage::delete($generation->result_path);
        $this->getJson($url)->assertOk()->assertJsonPath('status', 'succeeded')
            ->assertJsonPath('image_url', null)
            ->assertJsonPath('message', 'Персонаж создан, но изображение недоступно. Обратитесь к администратору; повторная генерация не нужна.');
        Storage::put($generation->result_path, $bytes, ['visibility' => 'public', 'directory_visibility' => 'public']);
        $this->getJson($url)->assertOk()->assertJsonPath('image_url', $generation->imageUrl());
        $this->assertSame(100, (int) $user->balance());
        $this->assertNull($generation->fresh()->refund_transaction_id);
        Http::assertSentCount(1);
    }

    public function testPurchaseCharges100AndQueuesOnceWithoutCallingAi(): void
    {
        $user = $this->user();
        $key = (string) Str::uuid();
        $response = $this->actingAs($user)->postJson($this->url($user), ['request_id' => $key, 'cost' => 1]);
        $response->assertStatus(202)->assertJsonPath('balance', 100)->assertJsonPath('cost', 100)->assertJsonPath('status', 'pending');
        $generation = RoomCharacterGeneration::firstOrFail();
        $this->assertSame($key, $generation->request_key);
        $this->assertSame(-100, CoinTransaction::find($generation->charge_transaction_id)->price);
        Storage::disk('room_character_sources')->assertExists($generation->source_path);
        $this->assertStringContainsString('characters/boy/class_07.png', $generation->reference_path);
        $this->assertNull($user->fresh()->room_character_generation_id);
        Bus::assertDispatched(GenerateRoomCharacter::class, fn ($job) => $job->generationId === $generation->id && $job->connection === 'database');
        Bus::assertDispatchedTimes(GenerateRoomCharacter::class, 1);
        Http::assertNothingSent();

        $this->postJson($this->url($user), ['request_id' => $key])->assertStatus(202)->assertJsonPath('id', $generation->id);
        $this->postJson($this->url($user), ['request_id' => (string) Str::uuid()])->assertStatus(202)->assertJsonPath('id', $generation->id);
        $this->assertSame(100, (int) $user->balance());
        $this->assertSame(1, RoomCharacterGeneration::count());
        Bus::assertDispatchedTimes(GenerateRoomCharacter::class, 1);
    }

    public function testInvalidAvatarOrInsufficientBalanceNeverCharges(): void
    {
        $user = $this->user(99);
        $this->actingAs($user)->postJson($this->url($user), ['request_id' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('room_character');
        $this->assertSame(99, (int) $user->balance());
        $missing = $this->user(200, false);
        $this->actingAs($missing)->postJson($this->url($missing), ['request_id' => (string) Str::uuid()])->assertUnprocessable();
        $broken = $this->user();
        Storage::put($broken->image, '<svg>not a raster image</svg>');
        $this->actingAs($broken)->postJson($this->url($broken), ['request_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame(200, (int) $broken->balance());
        $this->assertSame(0, RoomCharacterGeneration::count());
        $this->assertSame([], Storage::disk('room_character_sources')->allFiles());
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function testSecondTabCanRetryItsOwnKeyAfterTheSharedGenerationFinishes(): void
    {
        $user = $this->user(300);
        $firstKey = (string) Str::uuid();
        $secondKey = (string) Str::uuid();
        $generation = $this->purchase($user, $firstKey);
        $this->assertSame($generation->id, $this->purchase($user, $secondKey)->id);
        $this->assertSame($generation->id, $this->purchase($user, strtoupper($secondKey))->id);
        $this->fakeImage();
        app(RoomCharacterGenerator::class)->generate($generation->id);
        $this->assertSame('succeeded', $generation->fresh()->status);
        $this->assertSame($generation->id, $this->purchase($user, $secondKey)->id);
        $this->assertSame(200, (int) $user->balance());
        $this->assertSame(1, RoomCharacterGeneration::count());
        Bus::assertDispatchedTimes(GenerateRoomCharacter::class, 1);
        Http::assertSentCount(1);
    }

    public function testOnlyVerifiedOwnerCanPurchaseAndReadStatus(): void
    {
        $owner = $this->user();
        $this->postJson($this->url($owner), ['request_id' => (string) Str::uuid()])->assertUnauthorized();
        $generation = $this->purchase($owner);
        foreach (['student', 'teacher', 'admin'] as $role) {
            $other = $this->user();
            $other->role = $role;
            $other->save();
            $this->actingAs($other)->postJson($this->url($owner), ['request_id' => (string) Str::uuid()])->assertForbidden();
            $this->getJson($this->url($owner) . '/' . $generation->id)->assertForbidden();
            $this->getJson($this->url($other) . '/' . $generation->id)->assertNotFound();
        }
        $owner->email_verified_at = null;
        $owner->save();
        $this->actingAs($owner)->postJson($this->url($owner), ['request_id' => (string) Str::uuid()])->assertForbidden();
    }

    public function testRequestKeyIsRequiredAndStatusDoesNotExposePrivatePaths(): void
    {
        $user = $this->user();
        $this->actingAs($user)->postJson($this->url($user), [])->assertUnprocessable();
        $this->postJson($this->url($user), ['request_id' => '../file'])->assertUnprocessable();
        $generation = $this->purchase($user);
        $response = $this->getJson($this->url($user) . '/' . $generation->id)->assertOk();
        $response->assertJsonMissingPath('source_path')->assertJsonMissingPath('reference_path')->assertJsonMissingPath('request_key');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get('/media/room-character-sources/' . $generation->source_path)->assertNotFound();
    }

    public static function providers(): array
    {
        return [['gateway'], ['openai']];
    }

    #[DataProvider('providers')]
    public function testGenerationSendsTwoReferencesInOneRequestAndInstallsPng(string $provider): void
    {
        config(['ai.commands.room_character.provider' => $provider]);
        $user = $this->user();
        $generation = $this->purchase($user);
        $sourcePath = $generation->source_path;
        $snapshot = Storage::disk('room_character_sources')->get($sourcePath);
        Storage::put($user->image, $this->png(false));
        $this->fakeImage($provider);

        app(RoomCharacterGenerator::class)->generate($generation->id);
        app(RoomCharacterGenerator::class)->generate($generation->id);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($provider, $snapshot) {
            $this->assertSame('https://' . $provider . '.example/responses', $request->url());
            $this->assertSame('character-model', $request['model']);
            $input = $request['input'][0]['content'];
            $this->assertSame('input_text', $input[0]['type']);
            $this->assertSame('data:image/png;base64,' . base64_encode($snapshot), $input[1]['image_url']);
            $this->assertSame('input_image', $input[2]['type']);
            $this->assertCount(3, $input);
            $params = $provider === 'gateway' ? $request['params'] : $request->data();
            $this->assertSame('transparent', $params['tools'][0]['background']);
            $this->assertSame('png', $params['tools'][0]['output_format']);
            return true;
        });
        $generation->refresh();
        $this->assertSame('succeeded', $generation->status);
        $this->assertSame($generation->id, $user->fresh()->room_character_generation_id);
        $this->assertNull($generation->refund_transaction_id);
        $this->assertSame(100, (int) $user->balance());
        Storage::assertExists($generation->result_path);
        Storage::disk('room_character_sources')->assertMissing($sourcePath);
        $info = getimagesizefromstring(Storage::get($generation->result_path));
        $this->assertSame([1024, 1536], [$info[0], $info[1]]);
        $this->get('/media/' . $generation->result_path)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($user)->getJson($this->url($user) . '/' . $generation->id)->assertJsonPath('image_url', $generation->imageUrl());
    }

    public function testFailureRefundsOnceAndDoesNotReplacePreviousCharacter(): void
    {
        $user = $this->user(300);
        $first = $this->purchase($user);
        $this->fakeImage();
        $generator = app(RoomCharacterGenerator::class);
        $generator->generate($first->id);
        $first->refresh();
        $second = $this->purchase($user);
        $this->assertSame($first->imageUrl(), $user->fresh()->roomCharacterImageUrl());
        Http::swap(new Factory());
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response(['error' => 'secret provider details'], 500));
        $generator->generate($second->id);
        $generator->fail($second->id, 'worker_failed');
        (new GenerateRoomCharacter($second->id))->failed(new \RuntimeException('timeout'));
        $second->refresh();
        $this->assertSame('failed', $second->status);
        $this->assertNotNull($second->refund_transaction_id);
        $this->assertSame(1, CoinTransaction::where('comment', 'Room character refund #' . $second->id)->count());
        $this->assertSame(200, (int) $user->balance());
        $this->assertSame($first->id, $user->fresh()->room_character_generation_id);
        Storage::assertExists($first->result_path);
        $this->assertSame($second->id, $this->purchase($user, $second->request_key)->id);
        $this->assertSame(200, (int) $user->balance());
    }

    public function testSuccessfulRegenerationUsesNewUrlAndRemovesOldPng(): void
    {
        $user = $this->user(300);
        $this->fakeImage();
        $first = $this->purchase($user);
        app(RoomCharacterGenerator::class)->generate($first->id);
        $first->refresh();
        $second = $this->purchase($user);
        Storage::assertExists($first->result_path);
        app(RoomCharacterGenerator::class)->generate($second->id);
        $second->refresh();
        $this->assertNotSame($first->result_path, $second->result_path);
        $this->assertSame($second->id, $user->fresh()->room_character_generation_id);
        Storage::assertMissing($first->result_path);
        Storage::assertExists($second->result_path);
        $this->assertSame(100, (int) $user->balance());
    }

    public function testOpaqueAndEmptyImagesAreRejectedWithRefund(): void
    {
        foreach ([$this->png(false), $this->png(true, true), 'not a png'] as $bytes) {
            $user = $this->user();
            $generation = $this->purchase($user);
            $this->fakeImage('gateway', $bytes);
            app(RoomCharacterGenerator::class)->generate($generation->id);
            $this->assertSame('failed', $generation->fresh()->status);
            $this->assertSame(200, (int) $user->balance());
            $this->assertNull($user->fresh()->room_character_generation_id);
        }
        $this->assertSame([], Storage::allFiles('room-characters'));
    }

    public function testLateResponseAfterRefundCannotInstallOrChargeAgain(): void
    {
        $user = $this->user();
        $generation = $this->purchase($user);
        $generator = app(RoomCharacterGenerator::class);
        Http::fake(function () use ($generation, $generator) {
            $generator->fail($generation->id, 'expired');
            return Http::response(['result' => ['output' => [['type' => 'image_generation_call', 'result' => base64_encode($this->png())]]]]);
        });
        $generator->generate($generation->id);
        $this->assertSame('failed', $generation->fresh()->status);
        $this->assertNull($user->fresh()->room_character_generation_id);
        $this->assertSame(200, (int) $user->balance());
        $this->assertSame([], Storage::allFiles('room-characters'));
        Http::assertSentCount(1);
    }

    public function testDispatchFailureRefundsAndRecoveryRefundsStaleJobsOnce(): void
    {
        $user = $this->user();
        $dispatcher = app(Dispatcher::class);
        $this->mock(Dispatcher::class)->shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('queue offline'));
        $generation = $this->purchase($user);
        $this->assertSame('failed', $generation->status);
        $this->assertSame('dispatch_failed', $generation->error_code);
        $this->assertSame(200, (int) $user->balance());
        $this->app->instance(Dispatcher::class, $dispatcher);

        $pending = $this->purchase($user);
        $pending->update(['created_at' => now()->subMinutes(31)]);
        $other = $this->user();
        $processing = $this->purchase($other);
        $processing->update(['status' => 'processing', 'started_at' => now()->subMinutes(11), 'created_at' => now()->subMinutes(12)]);
        $fresh = $this->purchase($this->user());
        $this->artisan('room-characters:recover')->assertSuccessful();
        $this->artisan('room-characters:recover')->assertSuccessful();
        $this->assertSame('failed', $pending->fresh()->status);
        $this->assertSame('failed', $processing->fresh()->status);
        $this->assertSame('pending', $fresh->fresh()->status);
        $this->assertSame(200, (int) $user->balance());
        $this->assertSame(200, (int) $other->balance());
        $this->assertSame(1, CoinTransaction::where('comment', 'Room character refund #' . $pending->id)->count());
    }

    public function testQueueDispatchWaitsForOuterCommitAndPurchaseRollsBackTogether(): void
    {
        $user = $this->user();
        DB::beginTransaction();
        $generation = $this->purchase($user);
        Bus::assertNothingDispatched();
        DB::rollBack();
        $this->assertSame(200, (int) $user->balance());
        $this->assertNull(RoomCharacterGeneration::find($generation->id));
        Bus::assertNothingDispatched();

        DB::beginTransaction();
        $generation = $this->purchase($user);
        Bus::assertNothingDispatched();
        DB::commit();
        Bus::assertDispatchedTimes(GenerateRoomCharacter::class, 1);
    }

    public function testDatabaseFailureRollsBackChargeAndDeletesSnapshot(): void
    {
        $user = $this->user();
        RoomCharacterGeneration::saving(function ($generation) {
            if ($generation->charge_transaction_id) throw new \RuntimeException('database failure');
        });
        try {
            $this->purchase($user);
            $this->fail('Expected database failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('database failure', $e->getMessage());
        }
        $this->assertSame(200, (int) $user->balance());
        $this->assertSame(0, RoomCharacterGeneration::count());
        $this->assertSame([], Storage::disk('room_character_sources')->allFiles());
        Bus::assertNothingDispatched();
    }

    public function testCustomCharacterKeepsRoomGeometryAndFallsBackWhenMissing(): void
    {
        $user = $this->user();
        $this->fakeImage();
        $generation = $this->purchase($user);
        app(RoomCharacterGenerator::class)->generate($generation->id);
        $user->refresh();
        $user->setRelation('courses', collect())->setRelation('achievements', collect());
        foreach ([0, 700, 3500, 16500] as $score) {
            $user->setComputedScore($score);
            $data = $user->learningAvatarRenderData();
            $layer = collect($data['layers'])->firstWhere('slot', 'character');
            $this->assertSame($generation->fresh()->imageUrl(), $layer['src']);
            $this->assertSame('cover', $layer['fit']);
            $this->assertSame('center bottom', $layer['objectPosition']);
            $this->assertNotEmpty($layer['slotGeometry']);
            $this->assertSame($layer['src'], $data['characterPreviewByGradeAndGender'][5]['girl']['src']);
        }
        Storage::delete($generation->fresh()->result_path);
        $data = $user->learningAvatarRenderData();
        $this->assertStringContainsString('/characters/boy/class_07.png', collect($data['layers'])->firstWhere('slot', 'character')['src']);
    }

    public function testSnapshotPreparationFailureDoesNotCharge(): void
    {
        $user = $this->user();
        $disk = Storage::disk('room_character_sources');
        $this->partialMock(\App\Services\RoomCharacterImages::class, function ($mock) {
            $mock->shouldReceive('snapshot')->andThrow(new \RuntimeException('storage offline'));
        });
        try {
            $this->purchase($user);
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame(200, (int) $user->balance());
        }
        $this->assertSame([], $disk->allFiles());
    }

    public function testResultWriteFailureRefundsAndLeavesNoInstalledCharacter(): void
    {
        $user = $this->user();
        $generation = $this->purchase($user);
        $sourceDisk = Storage::disk('room_character_sources');
        $outputDisk = Storage::disk('local');
        $this->fakeImage();
        Storage::shouldReceive('disk')->with('room_character_sources')->andReturn($sourceDisk);
        Storage::shouldReceive('put')->once()->withArgs(fn ($path, $bytes) => str_starts_with($path, 'room-characters/') && is_string($bytes))->andReturn(false);
        Storage::shouldReceive('delete')->andReturn(true);
        app(RoomCharacterGenerator::class)->generate($generation->id);
        $this->assertSame('failed', $generation->fresh()->status);
        $this->assertSame(200, (int) $user->balance());
        $this->assertNull($user->fresh()->room_character_generation_id);
        $this->assertSame([], $sourceDisk->allFiles());
        $this->assertSame([], $outputDisk->allFiles('room-characters'));
    }

    public function testPruningDeletesOldOrphansAndPreservesCurrentAndPendingFiles(): void
    {
        $user = $this->user(300);
        $generation = $this->purchase($user);
        $this->fakeImage();
        app(RoomCharacterGenerator::class)->generate($generation->id);
        $activePath = $generation->fresh()->result_path;
        $pending = $this->purchase($user);
        Storage::put('room-characters/user-77-orphan.png', $this->png());
        Storage::put('room-characters/user-77-new.png', $this->png());
        Storage::disk('room_character_sources')->put('orphan.png', $this->png());
        foreach ([$activePath, 'room-characters/user-77-orphan.png'] as $path) {
            touch(Storage::path($path), now()->subDays(2)->timestamp);
        }
        foreach (['orphan.png', $pending->source_path] as $path) {
            touch(Storage::disk('room_character_sources')->path($path), now()->subDays(2)->timestamp);
        }
        $this->artisan('room-characters:recover --prune')->assertSuccessful();
        Storage::assertExists($activePath);
        Storage::assertExists('room-characters/user-77-new.png');
        Storage::assertMissing('room-characters/user-77-orphan.png');
        Storage::disk('room_character_sources')->assertExists($pending->source_path);
        Storage::disk('room_character_sources')->assertMissing('orphan.png');
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function testCharacterPurchaseSharesBalanceWithFramesAndTitles(): void
    {
        $user = $this->user(120);
        $this->purchase($user);
        $this->actingAs($user)->post('/insider/profile/' . $user->id . '/custom-title', ['custom_title' => 'Developer'])->assertRedirect();
        $this->assertNull($user->fresh()->custom_title);
        $this->assertSame(20, (int) $user->balance());
        $this->post('/insider/profile/' . $user->id . '/avatar-frame', ['avatar_frame' => 'custom'])->assertRedirect();
        $this->assertNull($user->fresh()->avatar_frame);
        $this->assertSame(20, (int) $user->balance());
    }
}
