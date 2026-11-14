<?php
namespace Tests\Feature;

use App\Services\CodeChecking\Artifacts;
use App\Services\CodeChecking\Configuration;
use App\Services\CodeChecking\HistoricalReport;
use App\Services\CodeChecking\LegacyArchive;
use App\Services\CodeChecking\LegacySuites;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LegacyArchiveTest extends TestCase
{
    protected function setUp():void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);
        DB::purge('sqlite');Storage::fake('code_checking');Storage::fake('code_originals');
        DB::unprepared('CREATE TABLE users (id INTEGER PRIMARY KEY,role TEXT,email_verified_at TEXT); INSERT INTO users VALUES (1,"admin","2026-01-01"),(2,"student","2026-01-01");
            CREATE TABLE code_artifacts (id TEXT PRIMARY KEY,bytes INTEGER,created_at TEXT,storage_disk TEXT,storage_key TEXT,verified_at TEXT);
            CREATE TABLE code_artifact_contents (id TEXT PRIMARY KEY,content TEXT,encoding TEXT);
            CREATE TABLE code_legacy_exports (id TEXT PRIMARY KEY,manifest TEXT,files TEXT,created_by INTEGER,created_at TEXT);');
    }

    protected function tearDown():void
    {
        DB::disconnect('sqlite');parent::tearDown();
    }

    public function test_complete_archive_keeps_unowned_code_binary_fixtures_and_encrypted_environment():void
    {
        $root=sys_get_temp_dir().'/cp-archive-'.bin2hex(random_bytes(8));mkdir($root);mkdir($root.'/blobs');
        try{
            $blob=function(string $bytes)use($root){$hash=hash('sha256',$bytes);file_put_contents($root.'/blobs/'.$hash,$bytes);return ['path'=>'blobs/'.$hash,'sha256'=>$hash,'bytes'=>strlen($bytes)];};
            $source=$blob('unowned private source');$binary=$blob("\xFF\0test fixture");$original=$blob('original ZIP bytes');
            $rows=['tasks'=>[['id'=>100,'test_config'=>['tests'=>[['input'=>$binary,'output'=>$source]]]]],
                'solutions'=>[['id'=>'orphan','task_id'=>null,'user_id'=>null,'course_id'=>null,'source'=>$source,'original_archive'=>$original]],
                'similarities'=>[['code_id'=>'orphan','code_id2'=>'another','percent'=>42]],
                'source-files'=>[['kind'=>'environments','name'=>'fixture.bin','artifact'=>$binary]]];
            $manifest=['scope'=>'complete','counts'=>[],'files'=>[]];
            foreach($rows as $kind=>$data){$name=$kind.'.jsonl';file_put_contents($root.'/'.$name,implode('',array_map(fn($r)=>json_encode($r)."\n",$data)));$manifest['files'][$name]=hash_file('sha256',$root.'/'.$name);$manifest['counts'][str_replace('-','_',$kind)]=count($data);}
            file_put_contents($root.'/manifest.json',json_encode($manifest));file_put_contents($root.'/private-env','OLD_DECRYPTION_KEY=secret');
            $archive=app(LegacyArchive::class);
            $this->assertFalse($archive->run($root,1)['apply']);$this->assertDatabaseCount('code_artifacts',0);
            $result=$archive->run($root,1,true,$root.'/private-env');
            $this->assertSame(1,$result['counts']['solutions']);
            $artifacts=app(Artifacts::class);
            $this->assertSame("\xFF\0test fixture",$artifacts->get($binary['sha256']));
            $this->assertSame('base64',DB::table('code_artifact_contents')->where('id',$binary['sha256'])->value('encoding'));
            $this->assertSame('code_originals',DB::table('code_artifacts')->where('id',$original['sha256'])->value('storage_disk'));
            $files=json_decode(DB::table('code_legacy_exports')->first()->files,true);
            $encrypted=$artifacts->get($files['runtime-environment.encrypted']['blob_id']);
            $this->assertStringNotContainsString('OLD_DECRYPTION_KEY',$encrypted);
            $this->assertSame('OLD_DECRYPTION_KEY=secret',Crypt::decryptString($encrypted));
            $index=json_decode($artifacts->get($files['archive-index.json']['blob_id']),true);
            $content='';foreach($index['files']['solutions.jsonl']['parts'] as $part)$content.=$artifacts->get($files[$part]['blob_id']);
            $this->assertSame(file_get_contents($root.'/solutions.jsonl'),$content);
            $this->assertTrue($archive->run($root,1,true)['already_archived']);
            $this->actingAs(\App\User::find(2))->getJson('/insider/settings/code-executor/legacy-exports')->assertForbidden();
            $this->actingAs(\App\User::find(1))->getJson('/insider/settings/code-executor/legacy-exports')->assertOk()->assertJsonPath('items.0.id',$result['id']);
            $this->get('/insider/settings/code-executor/legacy-exports/'.$result['id'].'/files/solutions-jsonl.0001.part')->assertOk();
            // Corruption is detected even when the export was already archived.
            file_put_contents($root.'/blobs/'.$source['sha256'],'corrupted');
            $this->expectException(\InvalidArgumentException::class);$archive->run($root,1,true);
        }finally{foreach(glob($root.'/blobs/*') as $path)unlink($path);rmdir($root.'/blobs');foreach(glob($root.'/*') as $path)unlink($path);rmdir($root);}
    }

    public function test_shared_checker_package_cannot_be_reused_as_io_data():void
    {
        $bundle=app(Artifacts::class)->put('{"format":1,"version":"'.str_repeat('a',64).'","archive_base64":"private"}');
        $this->assertTrue(app(LegacySuites::class)->isBundleArtifact($bundle['blob_id']));
        try{app(Configuration::class)->io(['tests'=>[['input'=>$bundle,'output'=>'x','is_public'=>true]]],10);$this->fail('Internal tests leaked through IO');}
        catch(HttpException $e){$this->assertSame(422,$e->getStatusCode());}
    }

    public function test_old_notice_is_normalized_without_changing_saved_report():void
    {
        $report=['legacy'=>true,'notice'=>'Old ambiguous notice','tests'=>[]];
        $this->assertSame(HistoricalReport::NOTICE,app(HistoricalReport::class)->view($report)['notice']);
        $this->assertSame('Old ambiguous notice',$report['notice']);
    }
}
