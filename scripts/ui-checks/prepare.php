<?php
// This fixture never connects to the configured application database.
if (PHP_SAPI !== 'cli' || !isset($argv[1]) || !is_dir($argv[1])) exit(1);
$directory = realpath($argv[1]);
mkdir($directory.'/sessions');
$database = new PDO('sqlite:'.$directory.'/base.sqlite');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec(file_get_contents(__DIR__.'/fixture.sql'));
unset($database);
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
require __DIR__.'/prepare-code-checking.php';
require __DIR__.'/prepare-deadlines.php';
