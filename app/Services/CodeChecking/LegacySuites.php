<?php
namespace App\Services\CodeChecking;

/** Only the hash-pinned migration suites are executable; no uploaded checkers. */
class LegacySuites
{
    public function validate(array $config, string $language, int $points): array
    {
        $catalogue=json_decode(file_get_contents(resource_path('code-checking/legacy-suites.json')),true,64,JSON_THROW_ON_ERROR);
        $id=(string)($config['suite_id']??''); $suite=$catalogue['tasks'][$id]??null;
        abort_unless($suite && ($config['suite_version']??null)===$catalogue['version']
            && $suite['language']===$language && $suite['points']===$points,422,'Unknown checking suite or changed language/maximum.');
        return ['suite_id'=>$id,'suite_version'=>$catalogue['version'],'bundle'=>$config['bundle']??null];
    }
}
