<?php

namespace App\Http\Controllers;

use App\Course;
use App\CoinTransaction;
use App\CourseActivity;
use App\CourseStudentPoints;
use App\ProgramChapter;
use App\ProgramStep;
use App\Http\Controllers\Controller;
use App\Lesson;
use App\LessonEarlyAccess;
use App\LessonStudentStats;
use App\TaskDeadline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class LessonsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('teacher')->only(['createView', 'create', 'editView', 'edit', 'makeLower', 'makeUpper', 'makeDeadline', 'export', 'exportMarkdown']);

    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */

    public function createView($id)
    {
        $course = Course::with('program.chapters')->findOrFail($id);
        return view('lessons.create', compact('course'));
    }

    public function create($id, Request $request)
    {
        $program = Course::findOrFail($id)->program;
        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'required|string',
            'chapter' => 'nullable|exists:program_chapters,id',
        ]);

        if ($request->has('chapter')) {
            $chapter = ProgramChapter::findOrFail($request->chapter);
        } else {
            $chapter = $program->chapters->first();
        }


        $order = 100;
        if ($program->lessons->count() != 0)
            $order = $program->lessons->last()->sort_index + 1;

        $lesson = new Lesson();
        $lesson->name = $request->name;
        $lesson->program_id = $program->id;
        $lesson->sort_index = $order;
        $lesson->description = clean($request->description);
        $lesson->sticker = "/stickers/" . random_int(1, 40) . ".png";
        $lesson->chapter_id = $chapter->id;
        $lesson->early_access_enabled = $request->early_access_enabled == 'on';

        $lesson->save();

        $data = ['name' => 'Введение', 'theory' => '', 'notes' => ''];

        $step = ProgramStep::createStep($lesson, $data);

        return redirect('/insider/courses/' . $id . '/steps/' . $step->id);
    }

    public function editView($course_id, $id)
    {
        $course = Course::findOrFail($course_id);
        $lesson = Lesson::findOrFail($id);
        return view('lessons.edit', compact('lesson', 'course'));
    }

    public function edit($course_id, $id, Request $request)
    {
        $lesson = Lesson::findOrFail($id);
        $course = Course::findOrFail($course_id);
        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'required',
            'start_date' => 'date|nullable',
            'chapter' => 'required|exists:program_chapters,id'
        ]);
        $lesson->name = $request->name;
        $wasStarted = $lesson->isStarted($course);
        $oldStartDate = $lesson->getStartDate($course);
        $lesson->setStartDate($course, $request->start_date);
        $lesson->description = clean($request->description);
        $lesson->chapter_id = $request->chapter;
        $lesson->early_access_enabled = $request->early_access_enabled == 'on';

        // Recalculate points if start date changed (lesson became started or stopped)
        if ($oldStartDate != $request->start_date) {
            \App\Jobs\RecalculateCoursePoints::dispatch($course->id);
        }
        if ($request->open == "yes")
            $lesson->is_open = true;
        else
            $lesson->is_open = false;

        $lesson->save();
        if (!$wasStarted && $lesson->isStarted($course)) {
            CourseActivity::recordLessonOpened($course, $lesson, Auth::user());
        }

        if ($request->hasFile('import') && $request->file('import')->getClientMimeType() == 'application/json') {
            $json = file_get_contents($request->file('import')->getRealPath());
            try {
                $lesson->import($json);
            } catch (\Throwable $e) {
                return redirect()->back()
                    ->withErrors(['import' => 'Не удалось импортировать урок: ' . $e->getMessage()])
                    ->with('alert-destination', 'head')
                    ->with('alert-class', 'alert-danger')
                    ->with('alert-title', 'Импорт не выполнен')
                    ->with('alert-text', $e->getMessage())
                    ->withInput();
            }
        }

        return redirect('/insider/courses/' . $course_id . '?chapter=' . $request->chapter);
    }

    public function buyEarlyAccess($course_id, $id)
    {
        $course = Course::with('students', 'teachers')->findOrFail($course_id);
        $lesson = Lesson::with('info', 'steps')->findOrFail($id);
        $user = Auth::user();

        if ($lesson->program_id !== $course->program_id) {
            abort(404);
        }

        if (!$course->students->contains('id', $user->id)) {
            abort(403);
        }

        if (!$lesson->early_access_enabled) {
            $this->make_info_alert('Ранний доступ недоступен', 'Для этого урока покупка раннего доступа не включена.');
            return redirect()->back();
        }

        if ($lesson->isStarted($course)) {
            $this->make_info_alert('Урок уже открыт', 'Этот урок уже доступен без покупки раннего доступа.');
            return redirect()->back();
        }

        if ($lesson->hasEarlyAccess($course, $user)) {
            $this->make_info_alert('Доступ уже куплен', 'Ранний доступ к этому уроку уже активен.');
            return redirect('/insider/courses/' . $course->id . '/steps/' . $lesson->steps->first()->id);
        }

        $cost = $lesson->earlyAccessCost();

        if ($user->balance() < $cost) {
            $this->make_error_alert('Не хватает GC', 'Ранний доступ к уроку стоит ' . $cost . ' GC.');
            return redirect()->back();
        }

        $access = LessonEarlyAccess::firstOrCreate([
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
        ]);

        if ($access->wasRecentlyCreated) {
            CoinTransaction::register($user->id, -1 * $cost, 'Early access Lesson #' . $lesson->id . ' Course #' . $course->id);
            CourseActivity::recordEarlyAccessBought($course, $lesson, $user, $cost);
            CourseStudentPoints::recalculate($course->id, $user->id);
            LessonStudentStats::recalculate($course->id, $lesson->id, $user->id);
        }

        $this->make_success_alert('Ранний доступ открыт', 'Списано ' . $cost . ' GC. Урок уже можно проходить.');

        return redirect('/insider/courses/' . $course->id . '/steps/' . $lesson->steps->first()->id);
    }

    public function makeLower($course_id, $id, Request $request)
    {
        $lesson = Lesson::findOrFail($id);
        $lesson->sort_index -= 1;
        $lesson->save();
        return redirect('/insider/courses/' . $course_id . '?chapter=' . $request->chapter);
    }

    public function makeUpper($course_id, $id, Request $request)
    {
        $lesson = Lesson::findOrFail($id);
        $lesson->sort_index += 1;
        $lesson->save();
        return redirect('/insider/courses/' . $course_id . '?chapter=' . $request->chapter);
    }

    public function makeDeadline($course_id, $id, Request $request)
    {
        $course = Course::findOrFail($course_id);
        $lesson = Lesson::with('steps.tasks')->findOrFail($id);

        if ($lesson->program_id !== $course->program_id) {
            abort(404);
        }

        $this->validate($request, [
            'deadline' => 'nullable|date',
            'penalty' => 'nullable|numeric|min:0|max:1',
        ]);

        $taskIds = $lesson->steps
            ->flatMap(function ($step) {
                return $step->tasks->pluck('id');
            })
            ->values();

        if ($taskIds->isEmpty()) {
            return back();
        }

        if (!$request->deadline) {
            TaskDeadline::where('course_id', $course_id)
                ->whereIn('task_id', $taskIds)
                ->delete();

            return back();
        }

        $penalty = $request->filled('penalty') ? $request->penalty : 0;

        foreach ($taskIds as $taskId) {
            TaskDeadline::updateOrCreate(
                [
                    'course_id' => $course_id,
                    'task_id' => $taskId,
                ],
                [
                    'expiration' => $request->deadline,
                    'penalty' => $penalty,
                ]
            );
        }

        return back();
    }

    public function export($course_id, $id)
    {
        $lesson = Lesson::findOrFail($id);

        $json = $lesson->export();

        $response = \Response::make($json);
        $response->header('Content-Type', 'application/json');
        $response->header('Content-length', strlen($json));
        $response->header('Content-Disposition', 'attachment; filename=lesson-' . $id . '.json');

        return $response;

    }

    public function exportMarkdown($course_id, $id)
    {
        $lesson = Lesson::findOrFail($id);

        // Create a temporary directory for markdown files
        $tempDir = sys_get_temp_dir() . '/lesson-' . $id . '-' . time();
        mkdir($tempDir);

        // Export each step's theory, notes and tasks as separate markdown files
        foreach ($lesson->steps as $index => $step) {
            $stepNumber = $index + 1;

            // Check if this is a Jupyter notebook step
            if ($step->is_notebook && !empty($step->theory)) {
                // Export as .ipynb file
                $fileName = sprintf('%02d-%s.ipynb', $stepNumber, $this->sanitizeFileName($step->name));
                $filePath = $tempDir . '/' . $fileName;

                // The theory field contains the notebook JSON
                file_put_contents($filePath, $step->theory);
            } else {
                // Export as regular markdown file
                $fileName = sprintf('%02d-%s.md', $stepNumber, $this->sanitizeFileName($step->name));
                $filePath = $tempDir . '/' . $fileName;

                $content = "# {$step->name}\n\n";

                if (!empty($step->theory)) {
                    $content .= "## Теория\n\n";
                    $content .= $step->theory . "\n\n";
                }

                if (!empty($step->notes)) {
                    $content .= "## Заметки\n\n";
                    $content .= $step->notes . "\n\n";
                }

                // Add tasks if any
                if ($step->tasks->count() > 0) {
                    $content .= "## Задачи\n\n";

                    foreach ($step->tasks as $taskIndex => $task) {
                        $taskNumber = $taskIndex + 1;
                        $content .= "### Задача {$taskNumber}: {$task->name}\n\n";

                        if (!empty($task->text)) {
                            $content .= $task->text . "\n\n";
                        }

                        // Add task metadata
                        $metadata = [];
                        if ($task->max_mark > 0) {
                            $metadata[] = "**Максимальный балл:** {$task->max_mark}";
                        }
                        if ($task->is_star) {
                            $metadata[] = "**Звёздочка:** Да";
                        }
                        if ($task->answer) {
                            $metadata[] = "**Правильный ответ:** {$task->answer}";
                        }

                        if (!empty($metadata)) {
                            $content .= implode(" | ", $metadata) . "\n\n";
                        }

                        $content .= "---\n\n";
                    }
                }

                file_put_contents($filePath, $content);
            }
        }

        // Create ZIP archive
        $zipPath = sys_get_temp_dir() . '/lesson-' . $id . '-' . time() . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $files = scandir($tempDir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    // Use ASCII-safe filenames by converting Cyrillic to Latin
                    $localName = $this->transliterate($file);
                    $zip->addFile($tempDir . '/' . $file, $localName);
                }
            }
            $zip->close();
        }

        // Clean up temporary directory
        $this->deleteDirectory($tempDir);

        // Return ZIP file as download
        // Get lesson order number within the program
        $lessonOrder = $lesson->program->lessons->search(function($l) use ($lesson) {
            return $l->id === $lesson->id;
        }) + 1;

        $safeName = sprintf('%02d-%s', $lessonOrder, $this->sanitizeFileName($lesson->name));
        if (empty($this->sanitizeFileName($lesson->name))) {
            $safeName = sprintf('%02d-lesson-%d', $lessonOrder, $id);
        }

        $response = \Response::make(file_get_contents($zipPath));
        $response->header('Content-Type', 'application/zip');
        $response->header('Content-Disposition', 'attachment; filename="' . $safeName . '.zip"');

        // Clean up ZIP file after sending
        register_shutdown_function('unlink', $zipPath);

        return $response;
    }

    private function deleteDirectory($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    private function sanitizeFileName($name)
    {
        // Transliterate Cyrillic to Latin for better compatibility
        $name = $this->transliterate($name);
        // Remove or replace invalid characters for file names
        $name = preg_replace('/[^a-zA-Z0-9\s-]/', '', $name);
        $name = preg_replace('/\s+/', '-', $name);
        $name = trim($name, '-');
        return mb_substr($name, 0, 50); // Limit length
    }

    private function transliterate($text)
    {
        $converter = array(
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ь' => '', 'ы' => 'y', 'ъ' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',

            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch',
            'Ш' => 'Sh', 'Щ' => 'Sch', 'Ь' => '', 'Ы' => 'Y', 'Ъ' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        );

        return strtr($text, $converter);
    }


}
