<?php

namespace Tests\Feature;

use App\RoomCharacterGeneration;
use App\Services\RoomCharacterImages;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecentRoomCharactersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'filesystems.default' => 'local']);
        DB::purge('sqlite');
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->boolean('birthday_hidden')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_14_140000_create_room_character_generations_table.php'))->up();
        Storage::fake('local');
    }

    private function character(array $attributes = [], ?User $user = null): RoomCharacterGeneration
    {
        $user ??= User::create(['name' => 'Character owner']);
        $generation = RoomCharacterGeneration::create(array_merge([
            'user_id' => $user->id, 'request_key' => (string) Str::uuid(), 'cost' => 50,
            'reference_path' => 'reference.png', 'status' => 'succeeded', 'finished_at' => now(),
            'result_path' => 'room-characters/' . Str::uuid() . '.png',
        ], $attributes));
        $user->forceFill(['room_character_generation_id' => $generation->id])->save();
        if ($generation->result_path) {
            Storage::put($generation->result_path, 'image fixture');
        }
        return $generation;
    }

    public function test_showcase_uses_latest_installed_characters_with_existing_images_and_visible_owners(): void
    {
        $old = $this->character();
        $replacement = $this->character(['finished_at' => now()->subHour()], $old->user);
        $hidden = $this->character();
        $hidden->user->forceFill(['is_hidden' => true])->save();
        $this->character(['status' => 'processing']);
        $this->character(['status' => 'failed']);
        $this->character(['result_path' => null]);
        $missing = $this->character();
        Storage::delete($missing->result_path);
        $orphan = $this->character();
        $orphan->user->delete();
        $latest = $this->character();

        $characters = RoomCharacterGeneration::recentForShowcase();
        $this->assertSame([$latest->id, $replacement->id], $characters->pluck('id')->all());
        $this->assertTrue($characters->every(fn ($character) => $character->relationLoaded('user')));
        $this->assertSame([], Storage::allFiles('room-character-previews'));
    }

    public function test_showcase_is_limited_to_ten_characters_sorted_by_completion_time(): void
    {
        $expected = [];
        for ($i = 0; $i < 12; $i++) {
            $expected[] = $this->character(['finished_at' => now()->addMinutes($i)])->id;
        }
        $this->assertSame(array_slice(array_reverse($expected), 0, 10), RoomCharacterGeneration::recentForShowcase()->pluck('id')->all());
    }

    public function test_preview_is_small_transparent_and_reused_after_the_first_request(): void
    {
        $generation = $this->character();
        $source = imagecreatetruecolor(512, 768);
        imagealphablending($source, false);
        imagesavealpha($source, true);
        imagefill($source, 0, 0, imagecolorallocatealpha($source, 0, 0, 0, 127));
        imagefilledrectangle($source, 150, 60, 360, 720, imagecolorallocatealpha($source, 40, 100, 230, 0));
        ob_start();
        imagepng($source);
        Storage::put($generation->result_path, ob_get_clean());
        imagedestroy($source);

        $url = route('room-character.preview', $generation->id);
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('max-age=10800', $response->headers->get('Cache-Control'));
        $preview = imagecreatefromstring($response->getContent());
        $this->assertSame([128, 192], [imagesx($preview), imagesy($preview)]);
        $this->assertSame(127, (imagecolorat($preview, 0, 0) >> 24) & 0x7f);
        $this->assertSame(0, (imagecolorat($preview, 64, 100) >> 24) & 0x7f);
        imagedestroy($preview);
        Storage::assertExists($generation->previewPath());

        // A repeated request must serve the saved file without decoding the original again.
        $this->mock(RoomCharacterImages::class)->shouldNotReceive('preview');
        $this->get($url)->assertOk()->assertContent($response->getContent());
    }

    public function test_preview_does_not_expose_hidden_unfinished_replaced_or_missing_characters(): void
    {
        $old = $this->character();
        $this->character([], $old->user);
        $hidden = $this->character();
        $hidden->user->forceFill(['is_hidden' => true])->save();
        $pending = $this->character(['status' => 'pending']);
        $missing = $this->character();
        Storage::delete($missing->result_path);
        foreach ([$old, $hidden, $pending, $missing] as $generation) {
            $this->get(route('room-character.preview', $generation->id))->assertNotFound();
        }
    }
}
