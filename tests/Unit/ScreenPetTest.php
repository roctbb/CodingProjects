<?php

namespace Tests\Unit;

use App\Support\ScreenPet;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Tests\TestCase;

class ScreenPetTest extends TestCase
{
    private function userWithPet(array $owned, ?string $equipped): User
    {
        $user = new User();
        $user->id = 42;
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'owned' => $owned,
            'equipped' => ['pet_right' => $equipped],
        ];
        return $user;
    }

    public function test_only_the_owned_equipped_pet_can_appear(): void
    {
        $this->assertNull(ScreenPet::forUser(null));
        $this->assertNull(ScreenPet::forUser($this->userWithPet([], 'cheremsha_pet')));
        $this->assertNull(ScreenPet::forUser($this->userWithPet(['cheremsha_pet'], null)));
        $this->assertSame('learning_bot_pet', ScreenPet::forUser($this->userWithPet(['cheremsha_pet', 'learning_bot_pet'], 'learning_bot_pet'))['key']);

        $pet = ScreenPet::forUser($this->userWithPet(['cheremsha_pet'], 'cheremsha_pet'));
        $this->assertSame('cheremsha_pet', $pet['key']);
        $this->assertSame(42, $pet['userId']);
        $this->assertSame('Черемша', $pet['name']);
        $this->assertCount(16, $pet['frames']);
        $this->assertFileExists(public_path($pet['atlas']));
    }

    public function test_missing_animation_does_not_break_the_page(): void
    {
        config(['screen_pets.cheremsha_pet' => 'images/screen-pets/missing.json']);
        $this->assertNull(ScreenPet::forUser($this->userWithPet(['cheremsha_pet'], 'cheremsha_pet')));
    }

    public function test_every_store_pet_has_an_animation_matching_its_anatomy(): void
    {
        $legs = ['learning_bot_pet' => 4, 'brr_brr_patapim_pet' => 2,
            'tralalelo_tralala_pet' => 3, 'tung_tung_tung_sahur_pet' => 2];
        foreach (array_keys(User::learningAvatarPetAbilities()) as $key) {
            $this->assertNull(ScreenPet::forUser($this->userWithPet([], $key)));
            $pet = ScreenPet::forUser($this->userWithPet([$key], $key));
            $this->assertSame($key, $pet['key']);
            $this->assertFileExists(public_path($pet['atlas']));
            if ($key === 'cheremsha_pet') {
                $this->assertSame('hop', $pet['motion']);
                $this->assertCount(16, $pet['frames']);
                $this->assertSame(1040, $pet['duration']);
                $this->assertSame(400, $pet['rest']);
                $this->assertNotEmpty($pet['turn']['right']);
            } else {
                $this->assertSame('rig', $pet['motion']);
                $this->assertSame(1, $pet['rig']['version']);
                $this->assertCount($legs[$key], $pet['rig']['legs']);
                $this->assertNotSame($pet['rig']['body']['right'], $pet['rig']['body']['left']);
                foreach (['right', 'front', 'left'] as $view) {
                    $this->assertArrayHasKey($pet['rig']['body'][$view], $pet['frames']);
                }
            }
        }
    }

    public function test_equipped_pet_is_available_on_every_page_and_its_setting_is_private(): void
    {
        $this->be($this->userWithPet(['cheremsha_pet'], 'cheremsha_pet'));
        foreach (['/insider/courses', '/insider/courses/1', '/insider/courses/1/steps/1', '/insider/profile', '/insider/market', '/courses'] as $path) {
            $this->app->instance('request', Request::create($path));
            Facade::clearResolvedInstance('request');
            $html = view('components.gc-screen-pet')->render();
            $this->assertStringContainsString('data-screen-pet=', $html);
        }
        $this->assertStringContainsString('data-screen-pet-toggle', view('layouts.partials.screen-pet-toggle', ['isOwner' => true])->render());
        $this->assertStringNotContainsString('data-screen-pet-toggle', view('layouts.partials.screen-pet-toggle', ['isOwner' => false])->render());
        $this->app['auth']->forgetGuards();
        $this->assertStringNotContainsString('data-screen-pet=', view('components.gc-screen-pet')->render());
        $this->assertStringNotContainsString('data-screen-pet-toggle', view('layouts.partials.screen-pet-toggle', ['isOwner' => true])->render());
    }

    public function test_payload_selects_the_articulated_renderer_without_a_production_preview_bypass(): void
    {
        $user = $this->userWithPet(['learning_bot_pet'], 'learning_bot_pet');
        foreach (['student', 'teacher'] as $role) {
            $user->role = $role;
            $this->be($user);
            $this->app->instance('request', Request::create('/insider/courses?pet_test=1'));
            Facade::clearResolvedInstance('request');
            $html = view('components.gc-screen-pet')->render();
            $this->assertStringContainsString('"motion":"rig"', $html);
            $this->assertStringContainsString('learning_bot-rig-v1.webp', $html);
            $this->assertStringContainsString('"preview":false', $html);
        }
    }
}
