<?php
namespace App;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CodeDraft extends Model
{
    public static function workspace(Request $request, int $course, int $task): Builder
    {
        $keys = ['user_id' => $request->user()->id, 'course_id' => $course, 'task_id' => $task];
        $source = $request->query('from_submission');
        if ($source === null) return static::query()->withAttributes($keys);
        abort_unless(is_string($source) && Str::isUuid($source), 422);
        return (new static)->setTable('code_submission_drafts')->newQuery()
            ->withAttributes($keys + ['submission_id' => $source]);
    }

    protected $guarded = [];
    protected $hidden = ['value'];
    protected $casts = ['value' => 'array'];
}
