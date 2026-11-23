<?php
namespace App\Console\Commands;

use App\MaterialReview\{Documents,Review,Resources,LegacyImport,Problem};
use App\User;
use Illuminate\Console\Command;

/** Authoring interface for external models. Publication intentionally has no CLI action. */
class MaterialReview extends Command
{
    protected $signature='materials:review {action : catalog|snapshot|revision|create|propose|update|validate|feedback|export|import|resource|legacy-plan|legacy-import} {--actor= : Administrator ID} {--lesson=} {--revision=} {--draft=} {--draft-version=} {--input= : JSON file, or - for stdin} {--sqlite=} {--mapping=}';
    protected $description='Версионированный JSON-интерфейс ревью учебных материалов';
    public function handle(Review $review): int
    {
        try {
            $user=User::find($this->option('actor'));
            if (!$user) throw new Problem('actor','Нужен --actor с ID администратора.',[],403);
            $review->admin($user);
            if (!$review->documents->installed()) throw new Problem('migration','Миграция модуля не применена.',[],503);
            $input=[];
            if ($path=$this->option('input')) {
                if ($path!=='-'&&(!is_file($path)||!is_readable($path))) throw new Problem('input','JSON-файл не найден или недоступен.');
                $data=$path==='-'?stream_get_contents(STDIN):file_get_contents($path);
                if (strlen($data)>256*1024*1024) throw new Problem('size','Пакет превышает 256 МБ.');
                $input=Documents::decode($data);
                if (!is_array($input)) throw new Problem('json','Ожидается объект JSON.');
            }
            $draft=(string)$this->option('draft'); $version=$this->option('draft-version');
            if (in_array($this->argument('action'),['propose','update'],true)&&(!$version||!ctype_digit((string)$version))) throw new Problem('version','Для записи нужен --draft-version.');
            $out=match($this->argument('action')) {
                'catalog'=>['format_version'=>1,'installation_id'=>$review->documents->installation(),'programs'=>$review->catalog($user)],
                'snapshot'=>['format_version'=>1,'installation_id'=>$review->documents->installation(),'revision'=>$review->documents->revision((int)$this->option('lesson'),'snapshot',$user->id)],
                'revision'=>$review->documents->read((string)$this->option('revision')),
                'create'=>$review->create($user,$input),
                'propose'=>$review->propose($user,$draft,$input,(int)$version),
                'update'=>$review->update($user,$draft,$input,(int)$version),
                'validate'=>$review->check($user,$draft),
                'feedback'=>$review->get($user,$draft),
                'export'=>$review->export($user,$draft), 'import'=>$review->import($user,$input),
                'resource'=>app(Resources::class)->store($user,(int)($input['program_id']??0),$input),
                'legacy-plan','legacy-import'=>app(LegacyImport::class)->run($user,(string)$this->option('sqlite'),$this->option('mapping'),$this->argument('action')==='legacy-import'),
                default=>throw new Problem('action','Неизвестное действие. Публикация выполняется в интерфейсе администратора.'),
            };
            $this->line(Documents::encode(['ok'=>true,'data'=>$out])); return self::SUCCESS;
        } catch (Problem $e) { $this->line(Documents::encode(['ok'=>false]+$e->json())); return self::FAILURE; }
        catch (\JsonException $e) { $this->line(Documents::encode(['ok'=>false,'error'=>'json','message'=>$e->getMessage()])); return self::FAILURE; }
        catch (\TypeError $e) { $this->line(Documents::encode(['ok'=>false,'error'=>'input_type','message'=>'Типы данных не соответствуют контракту v1.'])); return self::FAILURE; }
    }
}
