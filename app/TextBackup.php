<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TextBackup extends Model
{
    protected $table = 'text_backups';

    protected $fillable = [
        'backup_id',
        'course_id',
        'entity_type',
        'entity_id',
        'field_name',
        'original_text'
    ];

    /**
     * Create a new backup for a course
     *
     * @param int $courseId
     * @return string
     */
    public static function createBackupId($courseId)
    {
        return 'course_' . $courseId . '_' . Carbon::now()->format('Y-m-d_H-i-s');
    }

    /**
     * Store a text field backup
     *
     * @param string $backupId
     * @param int $courseId
     * @param string $entityType
     * @param int $entityId
     * @param string $fieldName
     * @param string|null $originalText
     * @return TextBackup
     */
    public static function storeFieldBackup($backupId, $courseId, $entityType, $entityId, $fieldName, $originalText)
    {
        return self::create([
            'backup_id' => $backupId,
            'course_id' => $courseId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'field_name' => $fieldName,
            'original_text' => $originalText
        ]);
    }

    /**
     * Get all backups for a course
     *
     * @param int $courseId
     * @return \Illuminate\Support\Collection
     */
    public static function getCourseBackups($courseId)
    {
        return self::where('course_id', $courseId)
            ->select('backup_id', 'created_at')
            ->groupBy('backup_id', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($backup) use ($courseId) {
                return [
                    'id' => $backup->backup_id,
                    'created_at' => $backup->created_at->toISOString(),
                    'course_id' => $courseId
                ];
            });
    }

    /**
     * Get all backup entries for a specific backup ID
     *
     * @param string $backupId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getBackupEntries($backupId)
    {
        return self::where('backup_id', $backupId)->get();
    }

    /**
     * Delete all entries for a backup
     *
     * @param string $backupId
     * @return bool
     */
    public static function deleteBackup($backupId)
    {
        return self::where('backup_id', $backupId)->delete();
    }

    /**
     * Check if a backup exists
     *
     * @param string $backupId
     * @return bool
     */
    public static function backupExists($backupId)
    {
        return self::where('backup_id', $backupId)->exists();
    }

    /**
     * Get backup statistics
     *
     * @param string $backupId
     * @return array
     */
    public static function getBackupStats($backupId)
    {
        $entries = self::where('backup_id', $backupId)
            ->selectRaw('entity_type, COUNT(*) as count')
            ->groupBy('entity_type')
            ->get();

        $stats = [];
        foreach ($entries as $entry) {
            $stats[$entry->entity_type] = $entry->count;
        }

        return $stats;
    }
}