<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RanksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $ranks = [
            ['name' => 'Рядовой', 'from' => 0, 'to' => 50],
            ['name' => 'Ефрейтор', 'from' => 50, 'to' => 150],
            ['name' => 'Младший сержант', 'from' => 100, 'to' => 300],
            ['name' => 'Сержант', 'from' => 300, 'to' => 700],
            ['name' => 'Старший сержант', 'from' => 700, 'to' => 1500],
            ['name' => 'Старшина', 'from' => 1500, 'to' => 2500],
            ['name' => 'Прапорщик', 'from' => 2500, 'to' => 3500],
            ['name' => 'Старший прапорщик', 'from' => 3500, 'to' => 4500],
            ['name' => 'Младший лейтенант', 'from' => 4500, 'to' => 5500],
            ['name' => 'Лейтенант', 'from' => 5500, 'to' => 6500],
            ['name' => 'Старший лейтенант', 'from' => 6500, 'to' => 7500],
            ['name' => 'Капитан', 'from' => 7500, 'to' => 10500],
            ['name' => 'Майор', 'from' => 10500, 'to' => 13500],
            ['name' => 'Подполковник', 'from' => 13500, 'to' => 16500],
            ['name' => 'Полковник', 'from' => 16500, 'to' => 20500],
            ['name' => 'Генерал-майор', 'from' => 20500, 'to' => 25500],
            ['name' => 'Адмирал', 'from' => 25500, 'to' => 30500],
            ['name' => 'Адмирал флота', 'from' => 30500, 'to' => 1000000],
        ];

        foreach ($ranks as $rank) {
            DB::table('ranks')->updateOrInsert(
                ['name' => $rank['name']],
                ['from' => $rank['from'], 'to' => $rank['to']]
            );
        }
    }
}
