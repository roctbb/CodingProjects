<?php
namespace App\Services\CodeChecking;

/** Only the hash-pinned migration suites are executable; no uploaded checkers. */
class LegacySuites
{
    /** Shared checker packages contain tests for several courses, not one fixture. */
    public function isBundleArtifact(string $id): bool
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('code_artifact_contents')) return false;
        $row=\Illuminate\Support\Facades\DB::table('code_artifact_contents')->where('id',$id)
            ->selectRaw('substr(content, 1, 160) as prefix')->first();
        return $row && preg_match('/^\{"format":1,"version":"[a-f0-9]{64}","archive_base64":"/D',$row->prefix)===1;
    }

    public function validate(array $config, string $language, int $points): array
    {
        $catalogue=json_decode(file_get_contents(resource_path('code-checking/legacy-suites.json')),true,64,JSON_THROW_ON_ERROR);
        $id=(string)($config['suite_id']??''); $suite=$catalogue['tasks'][$id]??null;
        abort_unless($suite && ($config['suite_version']??null)===$catalogue['version']
            && $suite['language']===$language && $suite['points']===$points,422,'Unknown checking suite or changed language/maximum.');
        return ['suite_id'=>$id,'suite_version'=>$catalogue['version'],'bundle'=>$config['bundle']??null];
    }
}
