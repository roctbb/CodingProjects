<?php

namespace Tests\Feature;

use App\Services\Tutor\Responder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\DeadlineFeatureCase;

class TutorResponderTest extends DeadlineFeatureCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.providers.openai.key'=>'test', 'ai.providers.openai.base_url'=>'https://tutor.example',
            'tutor.model'=>'gpt-6.1-terra', 'tutor.check_model'=>'gpt-6.1-terra', 'tutor.guided_only'=>false]);
    }

    private function ask(?callable $reserve = null): array
    {
        return app(Responder::class)->reply(['mode'=>'hint','topic'=>'Свет','question'=>'Что обозначает координата пикселя?',
            'assignment'=>'Цвет пикселя','readings'=>[['id'=>'s1','heading'=>'Цвет','title'=>'Свет']],
            'lesson'=>['steps'=>[]], 'history'=>[], 'neighbors'=>[]], reserve: $reserve);
    }

    private function response(array $json, string $status='completed'): array
    {
        return ['status'=>$status,'output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>json_encode($json)]]]],
            'usage'=>['input_tokens'=>12,'output_tokens'=>5,'total_tokens'=>17]];
    }

    private function draft(): array
    {
        return ['answer'=>'Координаты задают положение пикселя. 🔎','sources'=>['s1'],'card'=>null];
    }

    public function test_verified_answer_and_check_share_a_meter_and_keep_only_real_sources(): void
    {
        Http::fakeSequence()->push($this->response($this->draft()))->push($this->response(['allow'=>true,'good_question'=>true]));
        $answer=$this->ask();
        $this->assertFalse($answer['guided']);
        $this->assertTrue($answer['good_question']);
        $this->assertSame(['s1'],$answer['sources']);
        $this->assertSame(34,$answer['usage']['total_tokens']);
        $this->assertSame(['answer','check'],array_column($answer['usage']['calls'],'stage'));
        foreach (Http::recorded() as [$request]) {
            $this->assertSame('gpt-6.1-terra',$request['model']);
            $this->assertSame('medium',$request['reasoning']['effort']);
            $this->assertFalse($request['store']);
        }
    }

    public function test_exact_counts_reserve_both_models_and_the_draft_before_generation(): void
    {
        Http::fake([
            '*/responses/input_tokens' => Http::response(['input_tokens'=>45]),
            '*/responses' => Http::sequence()->push($this->response($this->draft()))
                ->push($this->response(['allow'=>true, 'good_question'=>false])),
        ]);
        $reservations = [];
        $reply = $this->ask(function ($tokens, $prior) use (&$reservations) {
            abort_if($tokens + $prior['known_tokens'] > 11000, 429, 'Личный дневной лимит.');
            $reservations[] = [$tokens, $prior['known_tokens']];
        });
        $this->assertSame([[10624,0], [10714,0], [10714,0]], $reservations);
        $this->assertSame(34, $reply['usage']['total_tokens']);
        $this->assertFalse($reply['guided']);
        $this->assertSame($this->draft()['answer'], $reply['answer']);
        Http::assertSentCount(4);
    }

    public function test_exact_count_over_quota_never_starts_generation(): void
    {
        Http::fake(['*/responses/input_tokens'=>Http::response(['input_tokens'=>500])]);
        $reply = $this->ask(fn ($tokens) => abort_if($tokens > 11000, 429, 'Личный дневной лимит.'));
        $this->assertSame('Личный дневной лимит.', $reply['quota_limited']);
        $this->assertSame(0, $reply['usage']['known_tokens']);
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => !str_ends_with($request->url(), '/input_tokens'));
    }

    public function test_interrupted_check_retains_paid_first_call_and_hides_unchecked_draft(): void
    {
        $calls=0;
        Http::fake(function () use (&$calls) {
            if (++$calls===2) throw new ConnectionException('interrupted');
            return Http::response($this->response($this->draft()));
        });
        $answer=$this->ask();
        $this->assertTrue($answer['guided']);
        $this->assertNotSame($this->draft()['answer'],$answer['answer']);
        $this->assertSame(17,$answer['usage']['known_tokens']);
        $this->assertNull($answer['usage']['total_tokens']);
        $this->assertFalse($answer['usage']['complete']);
    }

    public function test_provider_usage_on_incomplete_response_is_charged(): void
    {
        Http::fakeSequence()->push($this->response($this->draft(),'incomplete'));
        $answer=$this->ask();
        $this->assertTrue($answer['guided']);
        $this->assertTrue($answer['usage']['complete']);
        $this->assertSame(17,$answer['usage']['known_tokens']);
        Http::assertSentCount(1);
    }
}
