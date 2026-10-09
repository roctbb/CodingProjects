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
            ['name' => 'Младший сержант', 'from' => 150, 'to' => 300],
            ['name' => 'Сержант', 'from' => 300, 'to' => 700],
            ['name' => 'Старший сержант', 'from' => 700, 'to' => 1100],
            ['name' => 'Старшина', 'from' => 1100, 'to' => 1500],
            ['name' => 'Прапорщик', 'from' => 1500, 'to' => 1900],
            ['name' => 'Старший прапорщик', 'from' => 1900, 'to' => 2300],
            ['name' => 'Младший лейтенант', 'from' => 2300, 'to' => 2700],
            ['name' => 'Лейтенант', 'from' => 2700, 'to' => 3100],
            ['name' => 'Старший лейтенант', 'from' => 3100, 'to' => 3600],
            ['name' => 'Капитан', 'from' => 3600, 'to' => 4200],
            ['name' => 'Майор', 'from' => 4200, 'to' => 4800],
            ['name' => 'Подполковник', 'from' => 4800, 'to' => 5400],
            ['name' => 'Полковник', 'from' => 5400, 'to' => 6000],
            ['name' => 'Генерал-майор', 'from' => 6000, 'to' => 8000],
            ['name' => 'Адмирал', 'from' => 8000, 'to' => 11000],
            ['name' => 'Адмирал флота', 'from' => 11000, 'to' => 1000000],
        ];

        // Older installations use abbreviated names. Keep their IDs (including
        // manual rank references) and update all existing aliases consistently.
        $aliases = [
            'Младший сержант' => ['Мл. сержант'],
            'Старший сержант' => ['Ст. сержант'],
            'Старший прапорщик' => ['Ст. прапорщик'],
            'Младший лейтенант' => ['Мл. лейтенант'],
            'Старший лейтенант' => ['Ст. лейтенант'],
        ];

        DB::transaction(function () use ($ranks, $aliases) {
            foreach ($ranks as $rank) {
                $existing = DB::table('ranks')->whereIn('name', [
                    $rank['name'], ...($aliases[$rank['name']] ?? []),
                ]);

                if ($existing->exists()) {
                    $existing->update(['from' => $rank['from'], 'to' => $rank['to']]);
                } else {
                    DB::table('ranks')->insert($rank);
                }
            }
        });
    }
}
