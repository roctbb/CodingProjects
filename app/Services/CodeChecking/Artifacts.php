<?php

namespace App\Services\CodeChecking;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class Artifacts
{
    public const MAX_TEST_BYTES = 33554432;
    public const MAX_TEST_PREVIEW_BYTES = 65536;

    /** Small authorized test bodies are text; larger or binary bodies remain downloads. */
    public function testField(array $value, string $downloadUrl): array
    {
        if ($value['type'] === 'inline') return $value;
        if ($value['bytes'] <= self::MAX_TEST_PREVIEW_BYTES) {
            $text = $this->get($value['blob_id']);
            if (mb_check_encoding($text, 'UTF-8') && !str_contains($text, "\0")) {
                return ['type'=>'inline', 'text'=>$text, 'bytes'=>strlen($text), 'truncated'=>$value['truncated'] ?? false];
            }
        }
        unset($value['blob_id'], $value['sha256']);
        $value['download_url'] = $downloadUrl;
        return $value;
    }

    public function disk()
    {
        return Storage::disk('code_checking');
    }

    public function path(string $digest): string
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $digest), 422, 'Invalid artifact ID.');
        return substr($digest, 0, 2).'/'.$digest;
    }

    public function put(string $text): array
    {
        $digest = hash('sha256', $text);
        if (Schema::hasTable('code_artifact_contents')) {
            if (!mb_check_encoding($text, 'UTF-8')) return $this->putOriginal($text);
            $encoded=str_contains($text,"\0");
            DB::table('code_artifact_contents')->insertOrIgnore(['id'=>$digest,'content'=>$encoded?base64_encode($text):$text,'encoding'=>$encoded?'base64':'utf8']);
            return $this->record($digest,strlen($text));
        }
        if (!$this->disk()->exists($this->path($digest))) {
            abort_unless($this->disk()->put($this->path($digest), $text), 503, 'Unable to save artifact.');
        }
        return $this->record($digest, strlen($text));
    }

    /** Explicit DB storage for archived code/tests, including binary test fixtures. */
    public function putDatabase(string $bytes): array
    {
        abort_unless(Schema::hasTable('code_artifact_contents'),503,'Database artifact storage is required.');
        $digest=hash('sha256',$bytes);
        $encoded=!mb_check_encoding($bytes,'UTF-8') || str_contains($bytes,"\0");
        DB::table('code_artifact_contents')->insertOrIgnore(['id'=>$digest,'content'=>$encoded?base64_encode($bytes):$bytes,'encoding'=>$encoded?'base64':'utf8']);
        return $this->record($digest,strlen($bytes));
    }

    public function putFile(string $path, bool $original = true): array
    {
        if (Schema::hasTable('code_artifact_contents')) {
            if (!$original) {
                $text = file_get_contents($path);
                abort_unless(mb_check_encoding($text,'UTF-8'),422,'Text must be UTF-8.');
                return $this->put($text);
            }
            return $this->storeOriginal($path);
        }
        $digest = hash_file('sha256', $path);
        if (!$this->disk()->exists($this->path($digest))) {
            $stream = fopen($path, 'rb');
            try {
                abort_unless($this->disk()->put($this->path($digest), $stream), 503, 'Unable to save artifact.');
            } finally {
                if (is_resource($stream)) fclose($stream);
            }
        }
        return $this->record($digest, filesize($path));
    }

    private function record(string $digest, int $bytes): array
    {
        DB::table('code_artifacts')->insertOrIgnore(['id' => $digest, 'bytes' => $bytes, 'created_at' => now()]);
        return ['type' => 'blob', 'blob_id' => $digest, 'sha256' => $digest, 'bytes' => $bytes, 'truncated' => false];
    }

    public function exists(string $digest, ?int $bytes = null): bool
    {
        $this->path($digest);
        $row = DB::table('code_artifacts')->find($digest);
        if (!$row || ($bytes !== null && (int)$row->bytes !== $bytes)) return false;
        if (Schema::hasTable('code_artifact_contents') && DB::table('code_artifact_contents')->where('id',$digest)->exists()) return true;
        if (!empty($row->storage_disk)) return Storage::disk($row->storage_disk)->exists($row->storage_key);
        return $this->disk()->exists($this->path($digest));
    }

    public function putOriginal(string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(),'cp-original-');
        try {file_put_contents($path,$bytes); return $this->putFile($path);}
        finally {@unlink($path);}
    }

    private function storeOriginal(string $path): array
    {
        $digest=hash_file('sha256',$path); $bytes=filesize($path); $key=$this->path($digest);
        $disk=Storage::disk('code_originals');
        if (!$disk->exists($key)) {
            $stream=fopen($path,'rb');
            try {abort_unless($disk->put($key,$stream,['visibility'=>'private']),503,'Unable to save original.');}
            finally {fclose($stream);}
        }
        abort_unless($disk->size($key)===$bytes,503,'Original size mismatch.');
        // Verify the bytes once before changing the durable location. ETag is not a SHA-256.
        $stream=$disk->readStream($key); abort_unless(is_resource($stream),503,'Original is unavailable.'); $hash=hash_init('sha256');
        try {hash_update_stream($hash,$stream);}
        finally {if(is_resource($stream))fclose($stream);}
        abort_unless(hash_equals($digest,hash_final($hash)),503,'Original checksum mismatch.');
        $artifact=$this->record($digest,$bytes);
        DB::table('code_artifacts')->where('id',$digest)->update(['storage_disk'=>'code_originals','storage_key'=>$key,'verified_at'=>now()]);
        return $artifact;
    }

    public function get(string $digest): string
    {
        $stream=$this->readStream($digest);
        try {return stream_get_contents($stream);}
        finally {if(is_resource($stream))fclose($stream);}
    }

    /** Saved code can be read without consulting the original-file storage. */
    public function databaseContent(string $digest): ?string
    {
        $this->path($digest);
        if (!Schema::hasTable('code_artifact_contents')) return null;
        $row = DB::table('code_artifact_contents')->where('id', $digest)->first();
        if (!$row) return null;
        $body = $row->encoding === 'base64' ? base64_decode($row->content, true) : $row->content;
        abort_unless(is_string($body), 503, 'Не удалось прочитать сохранённый исходник.');
        return $body;
    }

    public function readStream(string $digest)
    {
        $this->path($digest);
        if (Schema::hasTable('code_artifact_contents')) {
            $content=DB::table('code_artifact_contents')->where('id',$digest)->first();
            if ($content) {
                $body=$content->encoding==='base64'?base64_decode($content->content,true):$content->content;
                $stream=fopen('php://temp','w+b'); fwrite($stream,$body); rewind($stream); return $stream;
            }
        }
        $row=DB::table('code_artifacts')->find($digest);
        $stream=!empty($row->storage_disk) ? Storage::disk($row->storage_disk)->readStream($row->storage_key) : $this->disk()->readStream($this->path($digest));
        abort_unless(is_resource($stream),404);return $stream;
    }

    public function withLocalFile(array $artifact, callable $action): mixed
    {
        $path=tempnam(sys_get_temp_dir(),'cp-artifact-'); $stream=$this->readStream($artifact['blob_id']);
        try {
            $out=fopen($path,'wb');try {stream_copy_to_stream($stream,$out);} finally {fclose($out);fclose($stream);}
            abort_unless(filesize($path)===$artifact['bytes'] && hash_equals($artifact['sha256'],hash_file('sha256',$path)),503,'Artifact checksum mismatch.');
            return $action($path);
        } finally {@unlink($path);}
    }

    public function download(array $artifact, string $name, bool $direct = true)
    {
        $artifact['bytes'] ??= (int)DB::table('code_artifacts')->where('id',$artifact['blob_id'])->value('bytes');
        abort_unless($this->exists($artifact['blob_id'],$artifact['bytes']),404);
        $row=DB::table('code_artifacts')->find($artifact['blob_id']);
        $headers=['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','Content-Type'=>'application/octet-stream'];
        $name=basename(str_replace('\\','/',$name));
        $name=preg_replace('/[\x00-\x1F\x7F]/','_',$name) ?: 'download';
        if ($direct && !empty($row->storage_disk) && config('filesystems.disks.'.$row->storage_disk.'.driver')==='s3') {
            $disposition=\Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition('attachment',$name,str_replace('%','_',\Illuminate\Support\Str::ascii($name)) ?: 'download');
            return redirect()->away(Storage::disk($row->storage_disk)->temporaryUrl($row->storage_key,now()->addMinutes(5),
                ['ResponseContentType'=>'application/octet-stream','ResponseContentDisposition'=>$disposition]))->withHeaders($headers);
        }
        return response()->streamDownload(function () use ($artifact) {
            $stream=$this->readStream($artifact['blob_id']);try {fpassthru($stream);} finally {fclose($stream);}
        },$name,$headers);
    }

    public function descriptor(mixed $field, bool $required = false): ?array
    {
        if ($field === null && !$required) return null;
        abort_unless(is_array($field) && is_int($field['bytes'] ?? null) && $field['bytes'] >= 0
            && $field['bytes'] <= self::MAX_TEST_BYTES && is_bool($field['truncated'] ?? null)
            && is_string($field['sha256'] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $field['sha256']), 422, 'Invalid test field.');
        if (($field['type'] ?? null) === 'inline') {
            abort_unless(is_string($field['text'] ?? null) && strlen($field['text']) <= 65536
                && strlen($field['text']) === $field['bytes']
                && hash_equals($field['sha256'], hash('sha256', $field['text'])), 422, 'Invalid inline field.');
            return array_intersect_key($field, array_flip(['type', 'text', 'bytes', 'sha256', 'truncated']));
        }
        abort_unless(($field['type'] ?? null) === 'blob' && ($field['blob_id'] ?? null) === $field['sha256']
            && $this->exists($field['blob_id'], $field['bytes']), 422, 'Artifact is missing or invalid.');
        return array_intersect_key($field, array_flip(['type', 'blob_id', 'bytes', 'sha256', 'truncated']));
    }
}
