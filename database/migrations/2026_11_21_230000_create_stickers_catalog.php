<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stickers', function (Blueprint $table) {
            $table->id();
            $table->string('image')->unique();
            $table->string('name');
            $table->string('collection', 100)->index();
            $table->timestamps();
        });
        $labels = require __DIR__.'/../data/sticker_catalog.php';
        $images = collect(range(1, 40))->map(fn ($number) => "/stickers/$number.png")
            ->merge(DB::table('lessons')->whereNotNull('sticker')->distinct()->pluck('sticker'))->filter()->unique();
        $now = now();
        foreach ($images as $image) {
            $key = basename(parse_url($image, PHP_URL_PATH) ?: $image);
            $key = preg_replace('/-[0-9a-f]{8,16}\.png$/', '.png', $key);
            $local = preg_match('~^/stickers/(\d+)\.png$~', $image, $match);
            [$collection, $name] = ($local ? $labels[$image] ?? null : $labels[$key] ?? null)
                ?? ['Из уроков', $local ? 'Наклейка № '.$match[1] : mb_substr(pathinfo($key, PATHINFO_FILENAME) ?: 'Наклейка урока', 0, 240)];
            DB::table('stickers')->insert(compact('image','name','collection') + ['created_at'=>$now, 'updated_at'=>$now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stickers');
    }
};
