<?php
namespace Tests\Feature;

use App\CoinTransaction;
use App\Course;
use App\Lesson;
use App\MarketDeal;
use App\MarketGood;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\DeadlineFeatureCase;

class BusinessMoneyTest extends DeadlineFeatureCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('CREATE UNIQUE INDEX money_course_stats ON course_student_points(course_id,student_id)');
        DB::statement('CREATE UNIQUE INDEX money_lesson_stats ON lesson_student_stats(course_id,lesson_id,student_id)');
        DB::table('coin_transactions')->delete(); CoinTransaction::register(1,100,'Initial');
    }

    private function good(int $stock=2, string $type='regular'): MarketGood
    {
        $good=MarketGood::first();$good->sale_type=$type;$good->in_stock=true;$good->number=$stock;$good->price=20;$good->auction_finished_at=null;$good->save();
        return $good;
    }

    public function test_purchase_requires_post_and_retries_return_original_order_even_after_cancellation(): void
    {
        $good=$this->good();$url='/insider/market/'.$good->id.'/buy';
        $body=['expected_price'=>20,'purchase_key'=>(string)Str::uuid()];$this->actingAs(User::find(1));
        $this->get($url)->assertStatus(405);
        $this->postJson($url,['expected_price'=>20])->assertUnprocessable();
        $before=MarketDeal::count();
        $this->post($url,$body)->assertRedirect();$this->post($url,$body)->assertRedirect();
        $this->assertSame($before+1,MarketDeal::count());$this->assertEquals(80,User::find(1)->balance());
        $this->assertSame(1,(int)$good->fresh()->number);
        $this->postJson($url,$body+['extra'=>'ignored'])->assertRedirect();
        $this->postJson($url,array_replace($body,['expected_price'=>21]))->assertConflict();
        $deal=MarketDeal::where('purchase_key',$body['purchase_key'])->firstOrFail();
        $this->actingAs(User::find(2))->post('/insider/market/cancel/'.$deal->id)->assertRedirect();
        $this->actingAs(User::find(1))->post($url,$body)->assertRedirect();
        $this->assertEquals(100,User::find(1)->balance());$this->assertSame($before+1,MarketDeal::count());
    }

    public function test_cancellation_restores_stock_keeps_history_and_cannot_refund_or_ship_twice(): void
    {
        $good=$this->good(1);$deal=$good->buy(User::find(1));
        $this->actingAs(User::find(1))->post('/insider/market/cancel/'.$deal->id)->assertForbidden();
        $this->actingAs(User::find(2));
        $this->get('/insider/market/cancel/'.$deal->id)->assertStatus(405);
        $this->post('/insider/market/cancel/'.$deal->id)->assertRedirect();
        $this->post('/insider/market/cancel/'.$deal->id)->assertRedirect();
        $this->assertNotNull($deal->fresh()->cancelled_at);$this->assertEquals(2,$deal->fresh()->cancelled_by);
        $this->assertNotNull($deal->fresh()->refund_transaction_id);
        $this->assertSame(1,(int)$good->fresh()->number);$this->assertEquals(100,User::find(1)->balance());
        $this->post('/insider/market/ship/'.$deal->id)->assertConflict();
    }

    public function test_issued_orders_cannot_be_cancelled_and_auction_cancellation_does_not_reopen_inventory(): void
    {
        $good=$this->good();$deal=$good->buy(User::find(1));
        $this->actingAs(User::find(2))->post('/insider/market/ship/'.$deal->id)->assertRedirect();
        $this->post('/insider/market/ship/'.$deal->id)->assertRedirect();
        $this->post('/insider/market/cancel/'.$deal->id)->assertConflict();
        $this->assertEquals(80,User::find(1)->balance());
        $other=$good->buy(User::find(1));$other->source='auction';$other->save();
        $this->post('/insider/market/cancel/'.$other->id)->assertRedirect();
        $this->assertSame(0,(int)$good->fresh()->number);
    }

    public function test_failed_refund_rolls_back_order_and_stock_and_retry_refunds_once(): void
    {
        $good=$this->good(1);$deal=$good->buy(User::find(1));
        DB::statement("CREATE TRIGGER fail_money_refund BEFORE INSERT ON coin_transactions WHEN NEW.price > 0 BEGIN SELECT RAISE(ABORT,'refund failed'); END");
        $this->actingAs(User::find(2))->post('/insider/market/cancel/'.$deal->id)->assertStatus(500);
        $this->assertNull($deal->fresh()->cancelled_at);$this->assertSame(0,(int)$good->fresh()->number);
        $this->assertEquals(80,User::find(1)->balance());DB::statement('DROP TRIGGER fail_money_refund');
        $this->post('/insider/market/cancel/'.$deal->id)->assertRedirect();
        $this->assertEquals(100,User::find(1)->balance());
    }

    public function test_notification_failure_cannot_repeat_an_auction_refund_or_fail_a_purchase(): void
    {
        $good=$this->good(1,'auction');DB::table('market_bids')->where('good_id',$good->id)->delete();
        $good->placeBid(User::find(1),20);
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('Notification unavailable'));
        $good->cancelActiveAuctionBids();$good->cancelActiveAuctionBids();
        $this->assertEquals(100,User::find(1)->balance());$this->assertSame(0,$good->auctionBids()->count());
        $good=$this->good();
        $this->actingAs(User::find(1))->post('/insider/market/'.$good->id.'/buy',[
            'purchase_key'=>(string)Str::uuid(),'expected_price'=>20])->assertRedirect();
        $this->assertEquals(80,User::find(1)->balance());
    }

    public function test_early_access_and_payment_are_atomic_and_repeated_purchase_is_free(): void
    {
        DB::table('lesson_early_accesses')->delete();
        DB::table('lessons')->where('id',1)->update(['early_access_enabled'=>true]);
        DB::table('lesson_info')->where('course_id',1)->where('lesson_id',1)->update(['start_date'=>'2027-01-01']);
        $cost=User::find(1)->earlyLessonAccessCost(Lesson::find(1));$this->assertGreaterThan(0,$cost);
        DB::statement("CREATE TRIGGER fail_access_payment BEFORE INSERT ON coin_transactions WHEN NEW.price < 0 BEGIN SELECT RAISE(ABORT,'payment failed'); END");
        $this->actingAs(User::find(1))->post('/insider/courses/1/lessons/1/early-access')->assertStatus(500);
        $this->assertFalse(Lesson::find(1)->hasEarlyAccess(Course::find(1),User::find(1)));
        $this->assertEquals(100,User::find(1)->balance());DB::statement('DROP TRIGGER fail_access_payment');
        $this->post('/insider/courses/1/lessons/1/early-access')->assertRedirect();
        $this->post('/insider/courses/1/lessons/1/early-access')->assertRedirect();
        $this->assertTrue(Lesson::find(1)->hasEarlyAccess(Course::find(1),User::find(1)));
        $this->assertEquals(100-$cost,User::find(1)->balance());
    }
}
