<?php
namespace App\Services\CodeChecking;

use App\CodeTaskFile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskFiles
{
    public function __construct(private Artifacts $artifacts) {}

    public function create(Request $request, int $taskId): CodeTaskFile
    {
        $data = $request->validate(['path' => 'required|string|max:240',
            'kind' => 'required|in:attachment,starter,resource', 'is_public' => 'required|boolean',
            'read_only' => 'sometimes|boolean', 'include_in_check' => 'sometimes|boolean',
            'file' => 'sometimes|file|max:51200', 'content' => 'sometimes|string|max:2097152']);
        $path = Paths::validate($data['path']);
        abort_if(CodeTaskFile::where('task_id', $taskId)->where('path', $path)->exists(), 409, 'File path already exists.');
        abort_if(CodeTaskFile::where('task_id', $taskId)->count() >= 100, 422, 'At most 100 task files.');
        abort_unless(($request->hasFile('file') ? 1 : 0) + (array_key_exists('content', $data) ? 1 : 0) === 1, 422, 'Supply file or content.');
        abort_if(in_array($data['kind'], ['starter', 'resource'], true) && !$request->boolean('is_public'), 422,
            'Starter and runtime files must be visible to students; use private attachments or test inputs for checking data.');
        $total = CodeTaskFile::where('task_id', $taskId)->get()->sum(fn ($item) => $item->artifact['bytes']);
        $artifact = $request->hasFile('file') ? $this->artifacts->putFile($request->file('file')->getRealPath()) : $this->artifacts->put($data['content']);
        abort_if($total + $artifact['bytes'] > 268435456, 422, 'Task files exceed 256 MiB.');
        if ($request->hasFile('file') && preg_match('/\.(py|cpp|cc|c|h|hpp|txt|json|js|html|css|php|sql|java|cs|swift|xml|ipynb)$/iD',$path)) {
            $text=file_get_contents($request->file('file')->getRealPath());
            if (mb_check_encoding($text,'UTF-8')) $this->artifacts->put($text);
        }
        return CodeTaskFile::create(['id' => (string) Str::uuid(), 'task_id' => $taskId, 'path' => $path,
            'original_name'=>$request->hasFile('file') ? $request->file('file')->getClientOriginalName() : basename($path),
            'mime_type'=>$request->hasFile('file') ? $request->file('file')->getMimeType() : 'text/plain',
            'kind' => $data['kind'], 'is_public' => $request->boolean('is_public'), 'read_only' => $request->boolean('read_only'),
            'include_in_check' => $request->boolean('include_in_check'), 'artifact' => $artifact, 'created_by' => $request->user()->id]);
    }

    public function replace(Request $request,int $taskId,string $id): CodeTaskFile
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request,$taskId,$id) {
            \App\Task::lockForUpdate()->findOrFail($taskId);
            $old=CodeTaskFile::where('task_id',$taskId)->lockForUpdate()->findOrFail($id);
            $old->delete();
            $new=$this->create($request,$taskId);
            $new->update(['previous_id'=>$old->id]);
            return $new;
        });
    }

    public function view(CodeTaskFile $file, int $course): array
    {
        return ['id' => $file->id, 'path' => $file->path, 'kind' => $file->kind, 'is_public' => $file->is_public,
            'original_name'=>$file->original_name ?? basename($file->path),'previous_id'=>$file->previous_id,
            'read_only' => $file->read_only, 'include_in_check' => $file->include_in_check, 'bytes' => $file->artifact['bytes'],
            'download_url' => route('code-task-files.download', ['course' => $course, 'task' => $file->task_id, 'attachment' => $file->id])];
    }
}
