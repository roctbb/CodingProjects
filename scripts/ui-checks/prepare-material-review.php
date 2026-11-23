<?php
// Invoked only with a fresh temporary directory; never uses the application's configured database.
if (PHP_SAPI!=='cli'||empty($argv[1])||!is_dir($argv[1])) exit(1);
require __DIR__.'/prepare.php';
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$directory.'/base.sqlite','cache.default'=>'array','queue.default'=>'sync']);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\Queue::fake(); Illuminate\Support\Facades\Notification::fake(); Illuminate\Support\Facades\Http::fake();
(require database_path('migrations/2026_11_16_220000_create_material_review.php'))->up();
use Illuminate\Support\Facades\DB;
use App\MaterialReview\{Documents,Review,Resources};
DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS mr_qa_course_points ON course_student_points(course_id,student_id)');
DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS mr_qa_lesson_points ON lesson_student_stats(course_id,lesson_id,student_id)');
$admin=App\User::findOrFail(5); $review=app(Review::class);
$old=app(Resources::class)->store($admin,1,['name'=>'before.svg','mime'=>'image/svg+xml','data'=>base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="150"><rect width="400" height="150" fill="#e7eefc"/><text x="20" y="80" font-size="24">0 → 1 → 2</text></svg>')]);
$new=app(Resources::class)->store($admin,1,['name'=>'after.svg','mime'=>'image/svg+xml','data'=>base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="150"><rect width="400" height="150" fill="#d8f4e4"/><text x="20" y="80" font-size="24">a[0] → a[1] → a[2]</text></svg>')]);
$theory="## Первое знакомство с массивом\n\nМассив хранит элементы.\n\nСтарое объяснение индексации.\n\n![Схема](/material-resources/{$old['id']})\n\nРазберём обращение `a[0]` и затем решим задачу.\n\n\\(a_i = i^2\\)";
DB::table('program_steps')->where('id',1)->update(['theory'=>$theory,'is_notebook'=>false,'notes'=>'Обратите внимание на индексацию с нуля.']);
$base=$review->documents->revision(1,'qa',$admin->id);
$d=$review->create($admin,['format_version'=>1,'installation_id'=>$review->documents->installation(),'program_id'=>1,'base_revision_ids'=>[$base['id']],
    'title'=>'Точечные исправления','reason'=>'Делаем первое знакомство с массивами последовательным и понятным.']);
$proposals=[
    ['external_id'=>'qa-local','title'=>'Объяснить индексацию с нуля','reason'=>'Ученик впервые встречает индекс. Уточняем, почему первый элемент обозначается a[0].','scope'=>'local','intent'=>'learning','operations'=>[['op'=>'fragment','entity'=>'step','id'=>1,'field'=>'theory','old'=>'Старое объяснение индексации.','new'=>'Индекс — номер элемента. Первый элемент массива имеет индекс 0, поэтому записывается как `a[0]`.']]],
    ['external_id'=>'qa-structure','title'=>'Добавить самостоятельную практику','reason'=>'После объяснения нужна короткая самостоятельная проверка понимания.','scope'=>'structure','intent'=>'learning','operations'=>[['op'=>'create_step','lesson_id'=>1,'key'=>'new:practice','fields'=>['name'=>'Проверим себя','theory'=>'Запишите обращения к первому и третьему элементам массива.']]]],
    ['external_id'=>'qa-question','title'=>'Какую логику урока выбрать?','reason'=>'Можно начать с готового массива либо сначала разобрать хранение отдельных значений. Какой подход лучше подходит этой группе?','kind'=>'question','scope'=>'lesson','intent'=>'question','operations'=>[]],
    ['external_id'=>'qa-image','title'=>'Показать обращения к элементам','reason'=>'На исходной схеме видны только номера. Добавляем запись обращений к массиву, чтобы связать рисунок с кодом.','scope'=>'local','intent'=>'learning','operations'=>[['op'=>'image','entity'=>'step','id'=>1,'field'=>'theory','old'=>'/material-resources/'.$old['id'],'resource_id'=>$new['id']]]],
];
foreach ($proposals as $p) { $review->propose($admin,$d['id'],$p,$d['version']); $d=$review->get($admin,$d['id']); }
$targets=$d['targets']; $targets[0]['fields']['name']='Массивы: от задачи к обращению по индексу';
$targets[0]['steps'][0]['fields']['theory']="## Начнём с задачи\n\nНужно сохранить температуры за неделю. Объединим семь значений в массив и научимся обращаться к каждому из них.";
$alternative=$review->create($admin,['format_version'=>1,'installation_id'=>$review->documents->installation(),'program_id'=>1,'base_revision_ids'=>[$base['id']],
    'title'=>'Новая логика урока','reason'=>'Сначала показываем практическую потребность в массиве, затем вводим индексы.','targets'=>$targets]);
file_put_contents($directory.'/material-review.json',Documents::encode(['draft'=>$d['id'],'alternative'=>$alternative['id']]));
echo Documents::encode(['draft'=>$d['id'],'alternative'=>$alternative['id']])."\n";
