<?php
// Read an existing consistent source copy; import solely into this script's synthetic temporary database.
if (PHP_SAPI!=='cli'||empty($argv[1])||empty($argv[2])||!is_dir($argv[1])||!is_file($argv[2])) exit(1);
$testDirectory=realpath($argv[1]);
if (!str_starts_with($testDirectory,realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR)) throw new RuntimeException('A disposable system temporary directory is required.');
$source=$argv[2]; $originalHash=hash_file('sha256',$source);
require __DIR__.'/prepare-material-review.php';
$before=Illuminate\Support\Facades\DB::table('program_steps')->where('id',1)->value('theory');
$importer=app(App\MaterialReview\LegacyImport::class);
$plan=$importer->run($admin,$source,null,false);
if (Illuminate\Support\Facades\DB::table('material_review_legacy')->count()!==0) throw new RuntimeException('Planning wrote to the destination.');
$first=$importer->run($admin,$source,null,true);
$count=Illuminate\Support\Facades\DB::table('material_review_legacy')->count();
$second=$importer->run($admin,$source,null,true);
if ($count!==Illuminate\Support\Facades\DB::table('material_review_legacy')->count()) throw new RuntimeException('Repeated import created duplicate rows.');
if ($originalHash!==hash_file('sha256',$source)) throw new RuntimeException('The source copy was modified.');
if ($before!==Illuminate\Support\Facades\DB::table('program_steps')->where('id',1)->value('theory')) throw new RuntimeException('Import changed published synthetic material.');
echo App\MaterialReview\Documents::encode(['source_unchanged'=>true,'published_material_unchanged'=>true,'idempotent'=>true,
    'tables'=>$plan['tables'],'archived_rows'=>$count,'proposal_versions'=>count($first['proposals']),'unmapped'=>count($first['unmapped'])])."\n";
