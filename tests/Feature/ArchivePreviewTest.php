<?php

namespace Tests\Feature;

use App\CodeExecutionRun;
use App\CodeSubmission;
use App\Services\CodeChecking\ArchivePreview;
use App\Services\CodeChecking\Artifacts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\DeadlineFeatureCase;

class ArchivePreviewTest extends DeadlineFeatureCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('code_originals'); Storage::fake('code_checking');
        config(['filesystems.disks.code_originals.driver' => 'local']);
        DB::table('users')->where('id', 5)->update(['role' => 'teacher']);
    }

    private function saved(array $rows, bool $legacy = true): CodeSubmission
    {
        $artifacts = app(Artifacts::class);
        // The saved file list is authoritative even if the original archive has different bytes.
        $zip = $artifacts->putOriginal('ORIGINAL ARCHIVE BYTES; must never be unpacked by this viewer');
        $raw = $artifacts->putDatabase(json_encode($rows, JSON_THROW_ON_ERROR));
        $id = (string) Str::uuid(); $runId = (string) Str::uuid();
        $submission = CodeSubmission::withoutEvents(fn () => CodeSubmission::create([
            'id' => $id, 'user_id' => 1, 'course_id' => 1, 'task_id' => 11,
            'request_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'language' => 'text', 'mode' => 'files',
            'files' => [['name' => 'solution.zip', 'artifact' => $zip]], 'current_run_id' => $runId, 'preparation_state' => 'ready',
            'source_metadata' => $legacy ? ['legacy_id' => 'TESTZIP', 'legacy_metadata' => ['lang' => 'zip'], 'raw_source_artifact' => $raw] : [],
        ]));
        CodeExecutionRun::create(['id' => $runId, 'submission_id' => $id, 'snapshot' => ['points' => 10, 'check_type' => 'manual'], 'state' => 'completed']);
        return $submission;
    }

    public function test_saved_file_list_is_db_only_lazy_and_scoped_without_changing_the_original_download(): void
    {
        $record = $this->saved([['name' => 'src/main.py', 'content' => "print('saved version')\n", 'is-binary' => false, 'private-extra' => 'DO NOT EXPOSE']]);
        $this->partialMock(Artifacts::class, function ($mock) {
            $mock->shouldNotReceive('get');
            $mock->shouldNotReceive('withLocalFile');
            $mock->shouldNotReceive('readStream');
        });
        $artifactsBefore = DB::table('code_artifacts')->count();
        $base = '/insider/code-submissions/'.$record->id.'/files/0/archive';
        $metadata = $this->actingAs(\App\User::findOrFail(1))->getJson($base)->assertOk()->assertJsonCount(1, 'entries')
            ->assertDontSee('saved version')->assertDontSee('DO NOT EXPOSE')->assertDontSee('GeekPaste')->json();
        $entry = $metadata['entries'][0];
        $this->assertSame('src/main.py', $entry['path']);
        $this->assertSame('python', $entry['language']);
        $this->getJson($entry['source_url'])->assertOk()->assertJsonPath('source', "print('saved version')\n");
        $this->actingAs(\App\User::findOrFail(2))->getJson($base)->assertOk();
        $this->actingAs(\App\User::findOrFail(3))->getJson($base)->assertForbidden();
        $this->getJson($entry['source_url'])->assertForbidden();
        $this->actingAs(\App\User::findOrFail(5))->getJson($base)->assertForbidden();
        $this->actingAs(\App\User::findOrFail(1))->getJson($base.'/entries/'.str_repeat('0', 64))->assertNotFound();
        $this->assertSame($artifactsBefore, DB::table('code_artifacts')->count());
        // Release the read-prohibition spy only for the existing original download endpoint.
        app()->forgetInstance(Artifacts::class);
        $response = $this->get($metadata['download_url'])->assertOk();
        $this->assertSame('ORIGINAL ARCHIVE BYTES; must never be unpacked by this viewer', $response->streamedContent());
    }

    public function test_all_historical_entries_are_retained_and_large_saved_text_downloads_without_preview(): void
    {
        $large = str_repeat('x', ArchivePreview::MAX_PREVIEW_BYTES + 1);
        $rows = array_map(fn ($index) => ['name' => 'part-'.$index.'.py', 'content' => 'pass', 'is-binary' => false], range(1, 105));
        $rows[] = ['name' => 'large.py', 'content' => $large, 'is-binary' => false];
        $rows[] = ['name' => 'image.png', 'content' => 'Файл размером 1234 байт.', 'is-binary' => true];
        $record = $this->saved($rows);
        $base = '/insider/code-submissions/'.$record->id.'/files/0/archive';
        $data = $this->actingAs(\App\User::findOrFail(1))->getJson($base)->assertOk()->assertJsonCount(107, 'entries')->json();
        $entry = $data['entries'][105];
        $this->assertFalse($entry['previewable']);
        $this->getJson($entry['source_url'])->assertStatus(422)->assertDontSee('GeekPaste');
        $download = $this->get($entry['download_url'])->assertOk();
        $this->assertSame(hash('sha256', $large), hash('sha256', $download->streamedContent()));
        $binary = $data['entries'][106];
        $this->assertFalse($binary['previewable']);
        $this->assertSame('archive', $binary['download_kind']);
        $this->assertSame($data['download_url'], $binary['download_url']);
    }

    public function test_untrusted_virtual_paths_and_malformed_rows_never_become_filesystem_reads_or_zip_fallbacks(): void
    {
        $record = $this->saved([
            ['name' => '../../outside.py', 'content' => 'ONLY SAVED TEXT', 'is-binary' => 0],
            42, null, ['name' => 'bad.py', 'content' => ['not a string'], 'is-binary' => ['malformed']],
        ]);
        $base = '/insider/code-submissions/'.$record->id.'/files/0/archive';
        $data = $this->actingAs(\App\User::findOrFail(1))->getJson($base)->assertOk()->assertJsonCount(4, 'entries')->json();
        $this->getJson($data['entries'][0]['source_url'])->assertOk()->assertJsonPath('source', 'ONLY SAVED TEXT');
        $download = $this->get($data['entries'][0]['download_url'])->assertOk();
        $this->assertStringNotContainsString('../', $download->headers->get('Content-Disposition'));
        foreach (array_slice($data['entries'], 1) as $entry) {
            $this->assertFalse($entry['previewable']);
            $this->getJson($entry['source_url'])->assertStatus(422);
        }
        $native = $this->saved([], false);
        $this->getJson('/insider/code-submissions/'.$native->id.'/files/0/archive')->assertNotFound();
        // Missing DB text must never be reconstructed by reading an old ZIP from S3.
        DB::table('code_artifact_contents')->where('id', $record->source_metadata['raw_source_artifact']['blob_id'])->delete();
        $this->getJson($base)->assertStatus(422)->assertDontSee('GeekPaste');
    }
}
