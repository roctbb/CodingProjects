<?php

namespace App\Console\Commands;

use App\Course;
use App\Lesson;
use App\ProgramStep;
use App\Task;
use App\TextBackup;
use App\Http\Controllers\YandexGPTController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FixTyposCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'course:fix-typos {course_id} {--restore=} {--list-backups}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix typos in course content using YandexGPT with backup functionality';

    /**
     * YandexGPT Controller instance
     *
     * @var YandexGPTController
     */
    protected $yandexGPT;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->yandexGPT = new YandexGPTController();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $courseId = $this->argument('course_id');
        $restoreBackup = $this->option('restore');
        $listBackups = $this->option('list-backups');

        // List backups if requested
        if ($listBackups) {
            return $this->listBackups($courseId);
        }

        // Restore from backup if requested
        if ($restoreBackup) {
            return $this->restoreFromBackup($courseId, $restoreBackup);
        }

        // Find the course
        $course = Course::find($courseId);
        if (!$course) {
            $this->error("Course with ID {$courseId} not found.");
            return 1;
        }

        $this->info("Starting typo correction for course: {$course->name}");

        // Create backup
        $backupId = $this->createBackup($course);
        if (!$backupId) {
            $this->error("Failed to create backup. Aborting.");
            return 1;
        }

        $this->info("Backup created with ID: {$backupId}");

        // Process typo corrections
        try {
            $this->processTypoCorrections($course);
            $this->info("Typo correction completed successfully!");
            $this->info("To restore from backup, use: php artisan course:fix-typos {$courseId} --restore={$backupId}");
        } catch (\Exception $e) {
            $this->error("Error during typo correction: " . $e->getMessage());
            $this->info("Your data is safe. To restore from backup, use: php artisan course:fix-typos {$courseId} --restore={$backupId}");
            return 1;
        }

        return 0;
    }

    /**
     * Create a backup of all course content
     *
     * @param Course $course
     * @return string|false
     */
    protected function createBackup(Course $course)
    {
        try {
            $backupId = TextBackup::createBackupId($course->id);

            // Backup course data
            if (isset($course->name)) {
                TextBackup::storeFieldBackup($backupId, $course->id, 'course', $course->id, 'name', $course->name);
            }
            if (isset($course->description)) {
                TextBackup::storeFieldBackup($backupId, $course->id, 'course', $course->id, 'description', $course->description);
            }

            // Backup lessons
            foreach ($course->lessons as $lesson) {
                TextBackup::storeFieldBackup($backupId, $course->id, 'lesson', $lesson->id, 'name', $lesson->name);
                TextBackup::storeFieldBackup($backupId, $course->id, 'lesson', $lesson->id, 'description', $lesson->description);
            }

            // Backup steps
            foreach ($course->lessons as $lesson) {
                foreach ($lesson->steps as $step) {
                    TextBackup::storeFieldBackup($backupId, $course->id, 'step', $step->id, 'name', $step->name);
                    TextBackup::storeFieldBackup($backupId, $course->id, 'step', $step->id, 'description', $step->description);
                    TextBackup::storeFieldBackup($backupId, $course->id, 'step', $step->id, 'notes', $step->notes);
                    TextBackup::storeFieldBackup($backupId, $course->id, 'step', $step->id, 'theory', $step->theory);
                }
            }

            // Backup tasks
            foreach ($course->lessons as $lesson) {
                foreach ($lesson->steps as $step) {
                    foreach ($step->tasks as $task) {
                        TextBackup::storeFieldBackup($backupId, $course->id, 'task', $task->id, 'name', $task->name);
                        TextBackup::storeFieldBackup($backupId, $course->id, 'task', $task->id, 'text', $task->text);
                        if (isset($task->solution)) {
                            TextBackup::storeFieldBackup($backupId, $course->id, 'task', $task->id, 'solution', $task->solution);
                        }
                    }
                }
            }

            return $backupId;
        } catch (\Exception $e) {
            Log::error("Failed to create backup: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process typo corrections for the course
     *
     * @param Course $course
     */
    protected function processTypoCorrections(Course $course)
    {
        $totalItems = 0;
        $processedItems = 0;

        // Count total items
        foreach ($course->lessons as $lesson) {
            $totalItems += 2; // name + description
            foreach ($lesson->steps as $step) {
                $totalItems += 4; // name + description + notes + theory
                foreach ($step->tasks as $task) {
                    $totalItems += 2; // name + text
                    if ($task->solution) $totalItems += 1; // solution
                }
            }
        }

        $this->info("Processing {$totalItems} text fields...");
        $progressBar = $this->output->createProgressBar($totalItems);

        // Process lessons
        foreach ($course->lessons as $lesson) {
            $this->correctTextField($lesson, 'name');
            $progressBar->advance();
            $processedItems++;

            $this->correctTextField($lesson, 'description');
            $progressBar->advance();
            $processedItems++;

            // Process steps
            foreach ($lesson->steps as $step) {
                $this->correctTextField($step, 'name');
                $progressBar->advance();
                $processedItems++;

                $this->correctTextField($step, 'description');
                $progressBar->advance();
                $processedItems++;

                $this->correctTextField($step, 'notes');
                $progressBar->advance();
                $processedItems++;

                $this->correctTextField($step, 'theory');
                $progressBar->advance();
                $processedItems++;

                // Process tasks
                foreach ($step->tasks as $task) {
                    $this->correctTextField($task, 'name');
                    $progressBar->advance();
                    $processedItems++;

                    $this->correctTextField($task, 'text');
                    $progressBar->advance();
                    $processedItems++;

                    if ($task->solution) {
                        $this->correctTextField($task, 'solution');
                        $progressBar->advance();
                        $processedItems++;
                    }
                }
            }
        }

        $progressBar->finish();
        $this->line('');
        $this->info("Processed {$processedItems} text fields.");
    }

    /**
     * Correct typos in a specific field of a model
     *
     * @param mixed $model
     * @param string $field
     */
    protected function correctTextField($model, $field)
    {
        $originalText = $model->$field;

        if (empty($originalText) || strlen(trim($originalText)) < 10) {
            return; // Skip very short or empty texts
        }

        try {
            // Use reflection to call the private method
            $reflection = new \ReflectionClass($this->yandexGPT);
            $method = $reflection->getMethod('callYandexGPT');
            $method->setAccessible(true);

            $correctedText = $method->invoke($this->yandexGPT, $originalText, 'fix_typos');

            if ($correctedText && $correctedText !== $originalText) {
                $model->$field = $correctedText;
                $model->save();
            }
        } catch (\Exception $e) {
            Log::warning("Failed to correct typos in {$field} for " . get_class($model) . " ID {$model->id}: " . $e->getMessage());
            // Continue processing other fields even if one fails
        }
    }

    /**
     * Restore course content from backup
     *
     * @param int $courseId
     * @param string $backupId
     * @return int
     */
    protected function restoreFromBackup($courseId, $backupId)
    {
        try {
            if (!TextBackup::backupExists($backupId)) {
                $this->error("Backup {$backupId} not found.");
                return 1;
            }

            // Verify backup belongs to the correct course
            $backupEntries = TextBackup::getBackupEntries($backupId);
            if ($backupEntries->isEmpty()) {
                $this->error("Backup {$backupId} is empty or corrupted.");
                return 1;
            }

            $backupCourseId = $backupEntries->first()->course_id;
            if ($backupCourseId != $courseId) {
                $this->error("Backup {$backupId} is not for course {$courseId}.");
                return 1;
            }

            $this->info("Restoring course content from backup {$backupId}...");

            // Group backup entries by entity type and ID
            $groupedEntries = $backupEntries->groupBy(function ($entry) {
                return $entry->entity_type . '_' . $entry->entity_id;
            });

            $restoredCount = 0;

            foreach ($groupedEntries as $entityKey => $entries) {
                $firstEntry = $entries->first();
                $entityType = $firstEntry->entity_type;
                $entityId = $firstEntry->entity_id;

                // Find the entity to restore
                $entity = null;
                switch ($entityType) {
                    case 'course':
                        $entity = Course::find($entityId);
                        break;
                    case 'lesson':
                        $entity = Lesson::find($entityId);
                        break;
                    case 'step':
                        $entity = ProgramStep::find($entityId);
                        break;
                    case 'task':
                        $entity = Task::find($entityId);
                        break;
                }

                if ($entity) {
                    // Restore each field
                    foreach ($entries as $entry) {
                        $fieldName = $entry->field_name;
                        $originalText = $entry->original_text;

                        if (property_exists($entity, $fieldName) || $entity->isFillable($fieldName)) {
                            $entity->$fieldName = $originalText;
                        }
                    }
                    $entity->save();
                    $restoredCount++;
                }
            }

            $this->info("Course content restored successfully from backup {$backupId}!");
            $this->info("Restored {$restoredCount} entities.");
            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to restore from backup: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * List available backups for a course
     *
     * @param int $courseId
     * @return int
     */
    protected function listBackups($courseId)
    {
        try {
            $courseBackups = TextBackup::getCourseBackups($courseId);

            if ($courseBackups->isEmpty()) {
                $this->info("No backups found for course {$courseId}.");
                return 0;
            }

            // Get course name for display
            $course = Course::find($courseId);
            $courseName = $course ? $course->name : 'Unknown';

            $this->info("Available backups for course {$courseId}:");

            // Prepare table data with backup statistics
            $tableData = [];
            foreach ($courseBackups as $backup) {
                $stats = TextBackup::getBackupStats($backup['id']);
                $statsText = [];
                foreach ($stats as $entityType => $count) {
                    $statsText[] = "{$entityType}: {$count}";
                }

                $tableData[] = [
                    $backup['id'],
                    Carbon::parse($backup['created_at'])->format('Y-m-d H:i:s'),
                    $courseName,
                    implode(', ', $statsText)
                ];
            }

            $this->table(
                ['Backup ID', 'Created At', 'Course Name', 'Content'],
                $tableData
            );

            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to list backups: " . $e->getMessage());
            return 1;
        }
    }
}
