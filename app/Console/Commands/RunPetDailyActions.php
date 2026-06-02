<?php

namespace App\Console\Commands;

use App\CoinTransaction;
use App\CourseActivity;
use App\Notifications\PetEvent;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunPetDailyActions extends Command
{
    protected $signature = 'pets:daily-actions';

    protected $description = 'Roll daily learning avatar pet abilities';

    public function handle()
    {
        $date = Carbon::now()->toDateString();
        $processed = 0;
        $triggered = 0;

        User::whereIn('role', ['student', 'novice'])->chunkById(100, function ($users) use ($date, &$processed, &$triggered) {
            foreach ($users as $user) {
                $petKey = $user->activeLearningAvatarPetKey();
                if (!$petKey || $user->hasLearningAvatarPetDailyRoll($date, $petKey)) {
                    continue;
                }

                $processed++;
                $user->markLearningAvatarPetDailyRoll($date, $petKey);

                if (random_int(1, 100) > User::learningAvatarPetDailyActionChance($petKey)) {
                    continue;
                }

                if ($this->runPetAction($user, $petKey, $date)) {
                    $triggered++;
                }
            }
        });

        $this->info('Processed pet rolls: ' . $processed . ', triggered actions: ' . $triggered . '.');

        return 0;
    }

    private function runPetAction(User $user, string $petKey, string $date): bool
    {
        $abilities = User::learningAvatarPetAbilities()[$petKey] ?? [];
        $petName = User::learningAvatarItemCatalog()[$petKey]['name'] ?? 'питомец';

        if (in_array('daily_coin_gift', $abilities, true)) {
            CoinTransaction::registerOnce(
                $user->id,
                3,
                'Pet coin gift ' . $petKey . ' ' . $date . ' User #' . $user->id,
                'Ваш питомец ' . $petName . ' принес вам 3 монеты',
                'success',
                'fas fa-paw'
            );
            CourseActivity::recordPetActionForActiveCourse($user, 'daily_coin_gift', [
                'pet_key' => $petKey,
                'pet_name' => $petName,
                'amount' => 3,
            ]);

            return true;
        }

        if (in_array('daily_big_coin_gift', $abilities, true)) {
            CoinTransaction::registerOnce(
                $user->id,
                7,
                'Pet big coin gift ' . $petKey . ' ' . $date . ' User #' . $user->id,
                'Ваш питомец ' . $petName . ' принес вам 7 монет',
                'success',
                'fas fa-paw'
            );
            CourseActivity::recordPetActionForActiveCourse($user, 'daily_big_coin_gift', [
                'pet_key' => $petKey,
                'pet_name' => $petName,
                'amount' => 7,
            ]);

            return true;
        }

        if (in_array('free_xp_booster_gift', $abilities, true)) {
            $user->grantFreeXpBooster();
            $user->notify(new PetEvent('Ваш питомец ' . $petName . ' подарил вам бесплатный XP-бустер.', 'success'));
            CourseActivity::recordPetActionForActiveCourse($user, 'free_xp_booster_gift', [
                'pet_key' => $petKey,
                'pet_name' => $petName,
            ]);

            return true;
        }

        return false;
    }
}
