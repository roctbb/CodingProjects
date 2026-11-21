<?php
// php services/tutor/experiment.php /path/to/codingprojects_lessons > /tmp/tutor-context.json
use App\ProgramStep;
use App\Services\Tutor\Reading;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$dir = $argv[1] ?? throw new InvalidArgumentException('Specify the teaching materials checkout.');
$map = json_decode(file_get_contents($dir.'/reports/courses_2026_publication_map_2026-10-31.json'), true, flags: JSON_THROW_ON_ERROR);
// Expected evidence fixed by reading the published source, independently of ranking.
$cases = [
    [4273, 'Двуцветный мир', 'Как прочитать цвет пикселя по координатам в Pillow и узнать три компоненты RGB?', [4272], '/getpixel|три значения/iu'],
    [4273, 'Плёночный мир', 'Почему размер файла PNG отличается от объёма данных пикселей без сжатия?', [4271], '/сжати/iu'],
    [4452, 'Переворот на месте', 'Почему в функцию для массива приходится передавать длину отдельным параметром?', [4452], '/длин.{0,70}отдельн|отдельн.{0,20}параметр/iu'],
    [4452, 'Красивые бусы', 'Где перечитать, проверяет ли C++ границы индекса встроенного массива?', [4451], '/границ|пределы массива/iu'],
    [4673, 'Бостонские разборки 1', 'Почему порядок столбцов признаков новой квартиры должен совпадать при прогнозе?', [4672], '/поряд.{0,30}признак|порядке.{0,30}площад/iu'],
    [4670, 'Сколько ошиблась модель?', 'Где перечитать, как метрика качества регрессии сравнивает прогнозы с правильными ответами?', [4670], '/метрика качества/iu'],
    [4027, 'Гауссово размытие', 'Почему постоянное заполнение нулями даёт тёмную рамку на краю изображения?', [4026], '/тёмную рамку|постоянное заполнение/iu'],
    [4026, 'Усредняющее размытие 3 × 3', 'Как связаны веса ядра с клетками окна соседей?', [4025], '/таблица весов|коэффициент ядра/iu'],
];
$out = [];
$diagnostics = in_array('--diagnostics', $argv, true)
    ? json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR)
    : null;
$theoryQuestions = in_array('--with-theory', $argv, true) ? [
    4271 => 'У картинки 100 на 100 пикселей, у каждого три канала по одному байту. Почему PNG с этой картинкой может весить меньше? Объясни на примере одноцветной картинки.',
    4452 => 'Если функция C++ получает встроенный массив и меняет первый элемент, почему я вижу изменение после вызова, а длину массива всё равно приходится передавать отдельно?',
] : [];
if (in_array('--basic-help', $argv, true)) {
    $basic = json_decode(file_get_contents(__DIR__.'/basic-help-cases.json'), true, flags: JSON_THROW_ON_ERROR);
    $theoryQuestions = array_map(fn ($probe) => $probe[0], $basic['theory']);
    $diagnostics = [];
    foreach ($basic['hints'] as $name => [$question, $expected]) {
        $diagnostics[$name] = ['mode'=>'hint', 'question'=>$question, 'review'=>null, 'history'=>[],
            'latest_submission'=>null, 'previous_results'=>[], 'diagnosis'=>$expected];
    }
    // A simple hint remains a hint even when an older submission is available.
    $diagnostics['Гауссово размытие']['latest_submission'] = ['mode'=>'code','language'=>'python','source_status'=>'ready',
        'text'=>"from PIL import Image\nimg = Image.open(input()).convert('L')", 'files'=>[],
        'result'=>['method'=>'gpt','state'=>'completed','comment'=>'Загружен исходник, обработка отсутствует.']];
    $diagnostics['Красивые бусы']['history'] = [['question'=>'Что проверить вначале?',
        'answer'=>'Попробуй массив из одной бусины. Какой ответ ты ожидаешь?']];
}
if ($diagnostics !== null || in_array('--full-lesson', $argv, true)) {
    config(['cache.default'=>'array', 'tutor.context_chars'=>1800]);
    foreach ($map['courses'] as $course) {
        $lessons = array_merge([], ...array_column($course['chapters'], 'lessons'));
        foreach ($lessons as $position => $lesson) {
            $ids = array_column($lesson['steps'], 'id');
            $active = array_filter($cases, fn ($c) => in_array($c[0], $ids, true));
            if (!$active) continue;
            $groups = $raw = [];
            foreach (['current'=>$position,'previous'=>$position-1,'next'=>$position+1] as $side => $index) {
                if (!isset($lessons[$index])) continue;
                $item = $lessons[$index];
                $groups[$side] = ['title'=>$item['name'],'steps'=>[]];
                foreach ($item['steps'] as $s) {
                    $body = file_get_contents($dir.'/конспекты/grade'.$course['grade'].'_2026/'.$s['source']);
                    if ($side === 'current' && !$s['is_notebook']) $raw[] = $body;
                    $step = new ProgramStep();
                    $groups[$side]['steps'][] = $step->forceFill(['id'=>$s['id'],'name'=>$s['name'],'is_notebook'=>$s['is_notebook'],
                        'theory'=>$s['is_notebook'] ? $body : preg_split('/^## (?:Задачи|Задания)\s*$/mu',$body,2)[0]]);
                }
            }
            foreach (array_intersect_key($theoryQuestions, array_flip($ids)) as $id => $question) {
                $materials = \App\Services\Tutor\Context::assemble($groups, $id, $question, '');
                unset($materials['sources']);
                $topic = collect($materials['lesson']['steps'])->firstWhere('current', true)['title'];
                $out[] = $materials + ['grade'=>$course['grade'], 'task'=>$topic, 'topic'=>$topic, 'budget'=>1800,
                    'question'=>$question, 'mode'=>'theory', 'assignment'=>'', 'excerpt'=>'', 'history'=>[],
                    'review'=>null, 'latest_submission'=>null, 'previous_results'=>[]];
            }
            foreach ($active as [$id,$name,$question,$expected,$pattern]) {
                $statement = null;
                foreach ($raw as $body) {
                    if (preg_match('/^### [^\n]*'.preg_quote($name,'/').'[ \t]*\R+(.*?)(?=^### |\z)/msu',$body,$matches)) {
                        $statement = $name."\n".trim($matches[1]);
                        break;
                    }
                }
                if ($statement === null) throw new RuntimeException('Missing task: '.$name);
                if ($diagnostics !== null) {
                    $case = $diagnostics[$name] ?? throw new RuntimeException('Missing diagnostic case: '.$name);
                    $materials = \App\Services\Tutor\Context::assemble($groups, $id,
                        $case['question'].' '.($case['review']['comment'] ?? ''), $statement);
                    unset($materials['sources']);
                    $out[] = $materials + $case + ['grade'=>$course['grade'], 'task'=>$name, 'budget'=>1800,
                        'topic'=>collect($materials['lesson']['steps'])->firstWhere('current', true)['title'],
                        'assignment'=>$statement, 'excerpt'=>'', 'history'=>[]];
                    continue;
                }
                $m = \App\Services\Tutor\Context::assemble($groups,$id,$question,$statement);
                $gold = [];
                foreach ($m['sources'] as $ref=>$part) {
                    if (in_array($part['step'],$expected,true) && (str_starts_with($ref,'S') || preg_match($pattern,$part['text']))) $gold[] = $ref;
                }
                unset($m['sources']);
                $cpp = $course['grade'] == 8;
                // Synthetic submissions only. No student records are used in the benchmark.
                $work = ['language'=>$cpp ? 'cpp' : 'python','mode'=>'code','source_status'=>'ready','text'=>'',
                    'files'=>[['name'=>$cpp ? 'main.cpp' : 'main.py','kind'=>'text','text'=>$cpp ? "int main() {\n    int values[] = {1, 2, 3};\n    return 0;\n}\n" : "values = [1, 2, 3]\nprint(values[0])\n"]],
                    'result'=>['method'=>'tests','state'=>'completed','passed'=>1,'total'=>2]];
                $payload = $m + ['topic'=>collect($m['lesson']['steps'])->firstWhere('current',true)['title'],'question'=>$question,
                    'assignment'=>$statement,'mode'=>'hint','excerpt'=>'','history'=>[],'review'=>null,'latest_submission'=>$work,
                    'previous_results'=>[['method'=>'tests','state'=>'completed','passed'=>0,'total'=>2],
                        ['method'=>'gpt','state'=>'completed','points'=>2,'maximum'=>5,'comment'=>'Проверь крайний случай.']]];
                $out[] = ['grade'=>$course['grade'],'task'=>$name,'budget'=>1800,'gold'=>$gold]+$payload;
            }
        }
    }
    if ($diagnostics !== null && count($out) !== count($diagnostics) + count($theoryQuestions)) throw new RuntimeException('Incomplete export');
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
foreach ($map['courses'] as $course) foreach ($course['chapters'] as $chapter) foreach ($chapter['lessons'] as $lesson) {
    $steps = array_column($lesson['steps'], null, 'id');
    $active = array_filter($cases, fn ($case) => isset($steps[$case[0]]));
    if (!$active) continue;
    $blocks = $raw = [];
    foreach ($steps as $id => $source) {
        $path = $dir.'/конспекты/grade'.$course['grade'].'_2026/'.$source['source'];
        $raw[$id] = file_get_contents($path);
        $step = new ProgramStep();
        $step->forceFill(['id'=>$id, 'name'=>$source['name'], 'is_notebook'=>$source['is_notebook'],
            'theory'=>$source['is_notebook'] ? $raw[$id] : preg_split('/^## (?:Задачи|Задания)\s*$/mu', $raw[$id], 2)[0]]);
        array_push($blocks, ...Reading::fragments($step));
    }
    foreach ($active as [$id,$task,$question,$expected,$pattern]) {
        $statement = '';
        foreach ($raw as $text) {
            if (preg_match('/^### [^\n]*'.preg_quote($task,'/').'\s*\R+\s*([^\n]+)/mu', $text, $match)) {
                $statement = $match[1];
                break;
            }
        }
        if (!$statement) throw new RuntimeException('Missing task statement: '.$task);
        foreach ([600, 1800, 3600, 7200] as $budget) {
            $parts = Reading::select($blocks,$question,$task."\n".$statement,$id,$budget);
            $readings = $gold = [];
            foreach ($parts as $i => $part) {
                $ref = 'R'.($i+1);
                $readings[] = ['id'=>$ref,'title'=>$part['title'],'heading'=>$part['heading'],'text'=>$part['text']];
                if (in_array($part['step'],$expected,true) && preg_match($pattern,$part['text'])) $gold[] = $ref;
            }
            $out[] = ['grade'=>$course['grade'],'task'=>$task,'budget'=>$budget,'gold'=>$gold,'readings'=>$readings,'question'=>$question,
                'topic'=>$steps[$id]['name'],'assignment'=>$task."\n".$statement];
        }
    }
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

