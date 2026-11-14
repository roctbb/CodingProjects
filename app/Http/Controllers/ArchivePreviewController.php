<?php

namespace App\Http\Controllers;

use App\CodeSubmission;
use App\Services\CodeChecking\Access;
use App\Services\CodeChecking\ArchivePreview;
use Illuminate\Http\Request;

class ArchivePreviewController extends Controller
{
    public function index(Request $request, string $submission, int $file, Access $access, ArchivePreview $preview)
    {
        $record = $this->record($request, $submission, $access);
        return $this->json($preview->metadata($record, $file));
    }

    public function source(Request $request, string $submission, int $file, string $entry, Access $access, ArchivePreview $preview)
    {
        $record = $this->record($request, $submission, $access);
        return $this->json($preview->source($record, $file, $entry));
    }

    public function download(Request $request, string $submission, int $file, string $entry, Access $access, ArchivePreview $preview)
    {
        $record = $this->record($request, $submission, $access);
        return $preview->download($record, $file, $entry);
    }

    private function record(Request $request, string $id, Access $access): CodeSubmission
    {
        $record = CodeSubmission::findOrFail($id);
        $access->authorOrTeacher($request->user(), $record);
        return $record;
    }

    private function json(array $value)
    {
        return response()->json($value)->withHeaders(['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
