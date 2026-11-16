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

    public function test_every_store_pet_has_its_own_sixteen_frame_animation(): void
    {
        foreach (array_keys(User::learningAvatarPetAbilities()) as $key) {
            $this->assertNull(ScreenPet::forUser($this->userWithPet([], $key)));
            $pet = ScreenPet::forUser($this->userWithPet([$key], $key));
            $this->assertSame($key, $pet['key']);
            $this->assertCount(16, $pet['frames']);
            $this->assertFileExists(public_path($pet['atlas']));
            $this->assertSame($key === 'cheremsha_pet' ? 'hop' : 'walk', $pet['motion']);
        }
    }

    public function test_pet_component_renders_only_on_the_home_screen(): void
    {
        $this->be($this->userWithPet(['cheremsha_pet'], 'cheremsha_pet'));
        foreach (['/insider/courses' => true, '/insider/courses/1' => false, '/insider/profile' => false] as $path => $visible) {
            $this->app->instance('request', Request::create($path));
            Facade::clearResolvedInstance('request');
            $html = view('components.gc-screen-pet')->render();
            $this->assertSame($visible, str_contains($html, 'data-screen-pet='));
        }
    }
}
