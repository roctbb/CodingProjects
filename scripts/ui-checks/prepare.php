<?php
// This fixture never connects to the configured application database.
if (PHP_SAPI !== 'cli' || !isset($argv[1]) || !is_dir($argv[1])) exit(1);
$directory = realpath($argv[1]);
mkdir($directory.'/sessions');
$database = new PDO('sqlite:'.$directory.'/base.sqlite');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec(file_get_contents(__DIR__.'/fixture.sql'));
unset($database);
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$directory.'/base.sqlite']);
(require $root.'/database/migrations/2026_11_21_230000_create_stickers_catalog.php')->up();
\Illuminate\Support\Facades\DB::disconnect('sqlite');
$theory = <<<'MD'
# Исследуем циклы

## Пример программы

Команды выполняются последовательно. Попробуйте изменить число повторений.

```python
for number in range(4):
    print(number)
```

## Как устроен цикл

Операция `print(number)` выполняется для каждого значения.

| Шаг | Значение |
| --- | --- |
| Первый | 0 |
| Второй | 1 |

$$ a^2 + b^2 = c^2 $$

## Практика

![Учебная платформа](/images/silaeder-logo.png)

Объясните результат программы и решите задания.
MD;
$notebook = json_encode(['nbformat' => 4, 'nbformat_minor' => 5,
    'metadata' => ['kernelspec' => ['language' => 'python']],
    'cells' => [
        ['cell_type' => 'markdown', 'metadata' => (object) [], 'source' => ["## Исследование\n", "Текст и формула: \$a^2\$.\n", "## Вывод программы\n"]],
        ['cell_type' => 'code', 'metadata' => (object) [], 'execution_count' => 1,
            'source' => ["for number in range(4):\n", "    print(number)\n"],
            'outputs' => [['output_type' => 'stream', 'name' => 'stdout', 'text' => ["0\n1\n2\n3\n"]]]],
    ]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
foreach (['reading' => [$theory, 0], 'notebook' => [$notebook, 1]] as $variant => [$content, $isNotebook]) {
    copy($directory.'/base.sqlite', $directory.'/'.$variant.'.sqlite');
    $db = new PDO('sqlite:'.$directory.'/'.$variant.'.sqlite');
    $db->prepare('UPDATE program_steps SET theory=?, is_notebook=? WHERE id=1')->execute([$content, $isNotebook]);
}
foreach (['catalog-desktop', 'catalog-phone'] as $variant) {
    copy($directory.'/base.sqlite', $directory.'/'.$variant.'.sqlite');
    $db = new PDO('sqlite:'.$directory.'/'.$variant.'.sqlite');
    $insert = $db->prepare('INSERT INTO lessons (id, name, description, is_open, sort_index, program_id, chapter_id) VALUES (?, ?, ?, 1, ?, 1, 1)');
    for ($i = 1; $i <= 30; $i++) {
        $insert->execute([500 + $i, sprintf('QA материал %02d', $i), 'Материал для проверки страниц каталога', 500 + $i]);
    }
}
foreach (['desktop', 'phone'] as $device) {
    copy($directory.'/catalog-'.$device.'.sqlite', $directory.'/planner-chapters-'.$device.'.sqlite');
    $db = new PDO('sqlite:'.$directory.'/planner-chapters-'.$device.'.sqlite');
    $db->exec(<<<'SQL'
        INSERT INTO program_chapters (id, program_id, name, sort_index) VALUES
            (501, 1, 'Следующая глава', 2), (502, 1, 'Глава без материалов', 3);
        INSERT INTO lessons (id, name, description, is_open, sort_index, program_id, chapter_id) VALUES
            (601, 'Я — первый по программе', 'Первый урок следующей главы', 1, 1, 1, 501),
            (602, 'А — второй по программе', 'Второй урок следующей главы', 1, 2, 1, 501),
            (603, 'Материал без главы', 'Урок без главы', 1, 3, 1, NULL);
        UPDATE courses SET current_lesson_id = 601 WHERE id = 1;
        SQL);
}
require __DIR__.'/prepare-code-checking.php';
require __DIR__.'/prepare-deadlines.php';
require __DIR__.'/prepare-task-workspace.php';
require __DIR__.'/prepare-lesson-statistics.php';
require __DIR__.'/prepare-subgroups.php';
