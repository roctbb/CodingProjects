<?php

namespace App\Services\Tutor;

use App\Services\Ai\AiSettings;
use App\Services\Ai\ApiClient;
use App\Services\Ai\Connection;
use Illuminate\Support\Arr;

class Responder
{
    private const CODE = '~^\s*(?:\x60{3}|#include\b|(?:def|class|import|from|return)\s+[A-Za-z_]|(?:for|while|if)\b.{1,160}:\s*$|(?:print|input|range)\s*\(|std::|int\s+main\b|using namespace\b|[A-Za-z_]\w*\s*=\s*[A-Za-z_]\w*\s*\()~m';

    public function __construct(private ApiClient $api, private AiSettings $settings) {}

    public function reply(array $data, ?callable $checkpoint = null, ?callable $reserve = null, ?int $userId = null): array
    {
        $calls = [];
        $reply = $this->guided($data);
        if (!config('tutor.guided_only') && !($data['mode'] === 'review' && empty($data['review']['comment']))) {
            $settings = $this->settings->all();
            $provider = $settings['provider'];
            $connection = Connection::resolve($provider, $settings['providers'][$provider]);
            if (!empty($connection['key'])) {
                try {
                    $reply = $this->generate($data, $provider, $connection, $calls, $checkpoint, $reserve, $userId) ?? $reply;
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
                    if ($error->getStatusCode() !== 429) throw $error;
                    $reply['quota_limited'] = $error->getMessage();
                } catch (\RuntimeException|\JsonException $error) {
                    // ApiClient logs request identifiers. A failed check must never expose the unchecked draft.
                }
            }
        }
        return $reply + ['usage'=>$this->usage($calls)];
    }

    private function generate(array $data, string $provider, array $connection, array &$calls, ?callable $checkpoint, ?callable $reserve, ?int $userId): ?array
    {
        $cards = json_decode(file_get_contents(resource_path('tutor/reference.json')), true, flags: JSON_THROW_ON_ERROR);
        $words = static function (string $s): array {
            preg_match_all('/\w{3,}/u', mb_strtolower($s), $matches);
            return array_unique(array_map(fn ($w) => mb_substr($w, 0, 5), $matches[0]));
        };
        $terms = $words(implode(' ', [$data['topic'], $data['question'], $data['assignment']]));
        $cards = collect($cards)->map(fn ($c) => $c+['score'=>count(array_intersect($terms,$words($c['tags'])))])
            ->where('score','>',0)->sortByDesc('score')->take(2)->map(fn ($c) => Arr::except($c,'score'))->values()->all();
        $data['cards'] = $cards;
        $refs = array_column(array_merge($data['readings'], $data['lesson']['steps'] ?? [], $data['neighbors'] ?? []),'id');
        $source = $data['review_basis']['source'] ?? $data['latest_submission'] ?? [];
        $canCheck = !empty($data['review_basis']['feedback']) && ($source['source_status'] ?? null) === 'ready';
        $string = ['type'=>'string'];
        $fields = ['answer'=>$string,'sources'=>['type'=>'array','items'=>$refs && count($refs)<=200 ? $string+['enum'=>$refs] : $string,
            'maxItems'=>min(2,count($refs))], 'card'=>['type'=>['string','null'],'enum'=>array_merge([null],array_column($cards,'id'))]];
        if ($canCheck) $fields['recheck'] = ['anyOf'=>[['type'=>'null'],$this->object(array_fill_keys(['claim','evidence','focus'],$string))]];
        $draft = $this->call('answer',$fields,$data,$provider,$connection,$canCheck,$calls,$checkpoint,$reserve,$userId);
        $text = $draft['answer'] ?? null;
        $selected = $draft['sources'] ?? null;
        if (!is_string($text) || trim($text)==='' || mb_strlen($text)>1400 || $this->unsuitable($text)
            || !is_array($selected) || !array_is_list($selected) || count($selected)>2) return null;
        foreach ($selected as $ref) if (!is_string($ref) || !in_array($ref,$refs,true)) return null;
        $card = collect($cards)->firstWhere('id',$draft['card'] ?? null);
        if (isset($draft['card']) && !$card) return null;
        $fields = ['allow'=>['type'=>'boolean'], 'good_question'=>['type'=>'boolean']];
        if ($canCheck) $fields['confirm_recheck'] = ['type'=>'boolean'];
        $check = $this->call('check',$fields,$data+['draft'=>$draft],$provider,$connection,$canCheck,$calls,$checkpoint,$reserve,$userId);
        if (($check['allow'] ?? false)!==true) return null;
        $finding = $canCheck && ($check['confirm_recheck'] ?? false)===true ? ($draft['recheck'] ?? null) : null;
        if ($finding && (!is_array($finding) || collect($finding)->contains(fn ($s) => !is_string($s) || preg_match('~https?://|www\.|\x60{3}~i',$s))
            || $this->unsuitable($finding['focus'] ?? ''))) $finding = null;
        return ['answer'=>trim($text),'sources'=>array_values(array_unique($selected)), 'good_question'=>($check['good_question'] ?? false) === true,
            'reference'=>$card ? Arr::only($card,['title','text','example']) : null,'recheck'=>$finding,'guided'=>false];
    }

    private function unsuitable(string $text): bool
    {
        return preg_match(self::CODE,$text) || preg_match('~https?://|www\.|\x60{3}~i',$text);
    }

    private function object(array $fields): array
    {
        return ['type'=>'object','properties'=>$fields,'required'=>array_keys($fields),'additionalProperties'=>false];
    }

    private function call(string $stage, array $fields, array $data, string $provider, array $settings, bool $canCheck, array &$calls, ?callable $checkpoint, ?callable $reserve, ?int $userId): array
    {
        $model = config($stage === 'check' ? 'tutor.check_model' : 'tutor.model');
        $effort = config('tutor.effort');
        $instruction = file_get_contents(resource_path("tutor/$stage.txt"));
        if ($canCheck || !empty($data['review_basis'])) {
            $file = $canCheck ? "$stage-review" : 'missing-source';
            $instruction .= "\n".file_get_contents(resource_path("tutor/$file.txt"));
        }
        $payload = ['model'=>$model,'store'=>false,'max_output_tokens'=>1200,'instructions'=>$instruction,
            'reasoning'=>['effort'=>$effort],'input'=>json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
            'text'=>['format'=>['type'=>'json_schema','name'=>"tutor_$stage",'strict'=>true,'schema'=>$this->object($fields)]]];
        // UTF-8 bytes upper-bound the visible tokenized text; include schema, protocol overhead and the entire output allowance.
        if ($reserve) {
            $prior = $this->usage($calls);
            $output = $payload['max_output_tokens'];
            try {
                $reserve(strlen(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) + 1024 + $output, $prior);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
                if ($error->getStatusCode() !== 429) throw $error;
                // If even the output allowance cannot fit, skip the network request entirely.
                $reserve($output, $prior);
                $countSettings = $settings;
                $countSettings['endpoint'] = rtrim($settings['endpoint'], '/').'/input_tokens';
                $count = $this->api->create(Arr::only($payload, ['model','instructions','input','text','reasoning']),
                    $provider, $countSettings, 8, 'student_tutor', api: 'input_tokens');
                if (!is_int($count['input_tokens'] ?? null) || $count['input_tokens'] <= 0) {
                    throw new \RuntimeException('AI returned an invalid input token count');
                }
                $reserve($count['input_tokens'] + $output, $prior);
            }
        }
        $index = count($calls);
        $calls[] = ['stage'=>$stage,'model'=>$model,'reasoning_effort'=>$effort,'input_tokens'=>null,'output_tokens'=>null,
            'total_tokens'=>null,'cached_input_tokens'=>null,'reasoning_tokens'=>null];
        if ($checkpoint) $checkpoint($this->usage($calls));
        $observe = function ($usage,$requestId,$resolvedModel) use (&$calls,$index,$checkpoint) {
            $call = &$calls[$index];
            if ($requestId) $call['provider_request_id'] = $requestId;
            if (is_string($resolvedModel)) $call['model'] = $resolvedModel;
            foreach (['input_tokens','output_tokens','total_tokens','input_tokens_details.cached_tokens','output_tokens_details.reasoning_tokens'] as $path) {
                $value = data_get($usage,$path);
                $key = $path==='input_tokens_details.cached_tokens' ? 'cached_input_tokens' : last(explode('.',$path));
                if (is_int($value) && $value>=0) $call[$key]=$value;
            }
            if ($call['total_tokens']===null && $call['input_tokens']!==null && $call['output_tokens']!==null)
                $call['total_tokens']=$call['input_tokens']+$call['output_tokens'];
            if ($checkpoint) $checkpoint($this->usage($calls));
        };
        $result = $this->api->create($payload,$provider,$settings,25,'student_tutor',observeUsage:$observe,userId:$userId);
        $text='';
        foreach ($result['output'] ?? [] as $message) {
            if (($message['type'] ?? null)!=='message') continue;
            foreach ($message['content'] ?? [] as $part) if (($part['type'] ?? null)==='output_text') $text.=$part['text'];
        }
        $json=json_decode($text,true,flags:JSON_THROW_ON_ERROR);
        if (!is_array($json)) throw new \RuntimeException('Invalid tutor response');
        return $json;
    }

    private function usage(array $calls): array
    {
        $total = static function ($key) use ($calls) {
            $counts = array_column($calls,$key);
            return in_array(null,$counts,true) ? null : array_sum($counts);
        };
        $known=0;
        foreach ($calls as $call) $known += $call['total_tokens'] ?? (($call['input_tokens'] ?? 0)+($call['output_tokens'] ?? 0));
        return ['complete'=>$total('total_tokens')!==null,'known_tokens'=>$known,'total_tokens'=>$total('total_tokens'),
            'input_tokens'=>$total('input_tokens'),'output_tokens'=>$total('output_tokens'),'calls'=>$calls];
    }

    private function guided(array $data): array
    {
        $parts=$data['readings'] ?: array_values(array_filter($data['lesson']['steps'] ?? [], fn($s)=>!empty($s['current'])));
        $parts=array_slice($parts,0,2);
        if (in_array($data['mode'],['submission','review'],true)) {
            $answer='Выбери одно замечание из проверки и найди строку своей работы, к которой оно относится. На каком маленьком примере можно проверить эту строку?';
            if ($data['mode'] === 'submission') {
                $criteria = $data['latest_submission']['result']['criteria'] ?? [];
                foreach ($criteria as $criterion) {
                    if (empty($criterion['title']) || !isset($criterion['points'], $criterion['maximum']) || $criterion['points'] >= $criterion['maximum']) continue;
                    $title = mb_strimwidth($criterion['title'], 0, 120, '…');
                    $answer = "За «{$title}» в последней сдаче у тебя {$criterion['points']} из {$criterion['maximum']}. Найди в своей работе строку, которая отвечает за этот критерий. На каком примере ты можешь её проверить? 🔎";
                    break;
                }
            }
            if ($data['mode']==='review' && empty($data['review']['comment'])) $answer='В этой проверке записан только балл. Преподаватель видит эту переписку и может пояснить оценку.';
        } else {
            $answer=$parts ? 'Загляни в «'.(($parts[0]['heading'] ?? '') ?: $parts[0]['title']).'». ' : '';
            $answer.='Возьми самый маленький пример из условия. Какой результат ты ожидаешь получить вручную? 🔎';
        }
        return ['answer'=>$answer,'sources'=>array_column($parts,'id'),'reference'=>null,'recheck'=>null,'guided'=>true];
    }
}
