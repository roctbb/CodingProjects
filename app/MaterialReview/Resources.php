<?php
namespace App\MaterialReview;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Immutable, self-contained resources, including SVG sanitization before storage. */
class Resources
{
    public static function safeUrl(string $url): bool
    {
        $url=html_entity_decode($url,ENT_QUOTES|ENT_HTML5,'UTF-8');
        if (preg_match('/[\x00-\x20]/',$url)||str_starts_with($url,'//')) return false;
        $scheme=parse_url($url,PHP_URL_SCHEME);
        return $scheme===null||in_array(strtolower((string)$scheme),['http','https'],true);
    }
    public function store(User $user,int $program,array $input): array
    {
        app(Review::class)->admin($user);
        if (!DB::table('programs')->where('id',$program)->exists()) throw new Problem('program','Программа не найдена.');
        return $this->persist($program,$input);
    }
    public function capture(int $program,string $url): ?array
    {
        if (preg_match('~^/material-resources/([a-f0-9-]{36})$~i',$url,$m)) {
            $r=DB::table('material_review_resources')->where('id',$m[1])->where('program_id',$program)->first(); return $r?(array)$r:null;
        }
        if (!str_starts_with($url,'/')||str_starts_with($url,'//')) return null;
        $path=rawurldecode(parse_url($url,PHP_URL_PATH)??'');
        if (preg_match('~(^|/)\.\.(/|$)|[\x00-\x1f]~',$path)) return null;
        if (str_starts_with($path,'/media/')) {
            if (config('filesystems.disks.'.config('filesystems.default').'.driver')!=='local') return null;
            $disk=\Illuminate\Support\Facades\Storage::disk();
            $root=realpath($disk->path('')); $candidate=realpath($disk->path(substr($path,7)));
        } else { $root=realpath(public_path()); $candidate=realpath(public_path(ltrim($path,'/'))); }
        if (!$root||!$candidate||!str_starts_with($candidate,$root.DIRECTORY_SEPARATOR)||!is_file($candidate)||filesize($candidate)>20*1024*1024) return null;
        $bytes=file_get_contents($candidate); $hash=hash('sha256',$bytes);
        if ($r=DB::table('material_review_resources')->where('program_id',$program)->where('original_sha256',$hash)->first()) return (array)$r;
        try { return $this->persist($program,['name'=>basename($candidate),'data'=>base64_encode($bytes)]); }
        catch (Problem) { return null; }
    }
    private function persist(int $program,array $input): array
    {
        $bytes=base64_decode($input['data']??'',true);
        if ($bytes===false||$bytes===''||strlen($bytes)>20*1024*1024) throw new Problem('resource','Ожидается base64 файла размером до 20 МБ.');
        $original=$input['original_data']??base64_encode($bytes); $originalBytes=base64_decode($original,true);
        if ($originalBytes===false||strlen($originalBytes)>20*1024*1024) throw new Problem('resource','Некорректный оригинал ресурса.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (($input['mime']??'')==='image/svg+xml'||str_contains(substr($bytes,0,300),'<svg')) {
            $bytes=$this->svg($bytes); $mime='image/svg+xml';
        } elseif (!in_array($mime,['image/png','image/jpeg','image/gif','image/webp','application/pdf','text/plain'],true)) throw new Problem('mime','Неподдерживаемый тип ресурса.',['mime'=>$mime]);
        if (str_starts_with($mime,'image/')&&$mime!=='image/svg+xml'&&@getimagesizefromstring($bytes)===false) throw new Problem('image','Изображение повреждено.');
        $hash=hash('sha256',$bytes); $id=$input['id']??(string)Str::uuid();
        if (isset($input['sha256'])&&!hash_equals($hash,$input['sha256'])) throw new Problem('resource_hash','Контрольная сумма ресурса не совпадает.');
        if (!Str::isUuid($id)) throw new Problem('identity','ID ресурса должен быть UUID.');
        if ($existing=DB::table('material_review_resources')->find($id)) {
            if ($existing->sha256!==$hash || (int)$existing->program_id!==$program) throw new Problem('identity','ID ресурса занят другой версией.');
            return (array)$existing;
        }
        DB::table('material_review_resources')->insert(['id'=>$id,'program_id'=>$program,'mime'=>$mime,'sha256'=>$hash,
            'name'=>mb_substr(basename($input['name']??'resource'),0,255),'data'=>base64_encode($bytes),'original_sha256'=>hash('sha256',$originalBytes),'original_data'=>$original,'created_at'=>now(),'updated_at'=>now()]);
        return (array)DB::table('material_review_resources')->find($id);
    }
    private function svg(string $xml): string
    {
        if (preg_match('/<!DOCTYPE|<!ENTITY/i',$xml)) throw new Problem('svg','DTD и XML entities запрещены.');
        $doc=new \DOMDocument(); $previous=libxml_use_internal_errors(true);
        try { if (!$doc->loadXML($xml,LIBXML_NONET)||$doc->documentElement?->localName!=='svg') throw new Problem('svg','Некорректный SVG.'); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $allowed=['svg','g','path','rect','circle','ellipse','line','polyline','polygon','text','tspan','defs','marker','linearGradient','radialGradient','stop','clipPath','mask','title','desc','pattern','symbol','use'];
        foreach (iterator_to_array($doc->getElementsByTagName('*')) as $node) {
            if (!in_array($node->localName,$allowed,true)) { $node->parentNode?->removeChild($node); continue; }
            foreach (iterator_to_array($node->attributes) as $attr) {
                $value=$attr->value; $name=strtolower($attr->localName);
                if (str_starts_with($name,'on')||$name==='style'||($name==='href'&&!str_starts_with($value,'#'))||preg_match('/(?:javascript:|data:|https?:|\/\/|url\(\s*[\x22\x27]?(?!#))/i',$value)) $node->removeAttributeNode($attr);
            }
        }
        return $doc->saveXML($doc->documentElement);
    }
}
