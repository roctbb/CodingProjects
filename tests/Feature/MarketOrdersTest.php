<?php

namespace Tests\Feature;

use App\Queries\MarketOrders;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketOrdersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT);
            CREATE TABLE market_goods (id INTEGER PRIMARY KEY, name TEXT);
            CREATE TABLE market_deals (id INTEGER PRIMARY KEY, good_id INTEGER, user_id INTEGER, price INTEGER, shipped INTEGER, created_at TEXT, updated_at TEXT);
            INSERT INTO users VALUES (1, 'Мария Орлова'), (2, 'Александр Соколов');
            INSERT INTO market_goods VALUES (1, 'Набор наклеек'), (2, 'Значок 100%_!');
            SQL);
        Paginator::currentPageResolver(fn () => 1);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function testQueuesAreBoundedStableAndHaveIndependentPageNames(): void
    {
        for ($id = 1; $id <= 60; $id++) $this->order($id, ['shipped' => $id > 30]);
        $query = new MarketOrders();
        $first = $query->paginate(false, '', 'market-orders');
        $this->assertSame(30, $first->total());
        $this->assertSame(range(30, 6), $first->pluck('id')->all());
        $this->assertStringEndsWith('#market-orders', $first->nextPageUrl());
        $this->assertStringContainsString('active_page=2', $first->nextPageUrl());
        Paginator::currentPageResolver(fn ($name) => $name === 'active_page' ? 2 : 1);
        $this->assertSame(range(5, 1), $query->paginate(false, '')->pluck('id')->all());
        $this->assertSame(range(60, 36), $query->paginate(true, '')->pluck('id')->all());
    }

    public function testSearchRunsBeforePaginationAndMatchesCyrillicNamesProductsAndOrderNumbers(): void
    {
        for ($id = 1; $id <= 30; $id++) $this->order($id);
        $this->order(31, ['user_id' => 2, 'good_id' => 2]);
        foreach (['соколов', 'ЗНАЧОК', '#31'] as $search) {
            $result = (new MarketOrders())->paginate(false, $search);
            $this->assertSame([31], $result->pluck('id')->all());
        }
        $this->assertSame(30, (new MarketOrders())->paginate(false, 'орлова')->total());
    }

    public function testWildcardsAreLiteralAndDeletedReferencesDoNotBreakQueues(): void
    {
        $this->order(1);
        $this->order(2, ['good_id' => 2]);
        $this->order(3, ['good_id' => 999, 'user_id' => 999]);
        $query = new MarketOrders();
        $this->assertSame([2], $query->paginate(false, '%_!')->pluck('id')->all());
        $this->assertSame(0, $query->paginate(false, "%' OR 1=1 --")->total());
        $this->assertSame([3], $query->paginate(false, '#3')->pluck('id')->all());
        $this->assertSame(3, $query->paginate(false, '')->total());
    }

    public function testQueueReturnsToLastAvailablePageAfterOrdersAreFulfilled(): void
    {
        $this->order(1);
        Paginator::currentPageResolver(fn () => 999);
        $orders = (new MarketOrders())->paginate(false, '');
        $this->assertSame(1, $orders->currentPage());
        $this->assertSame([1], $orders->pluck('id')->all());
    }

    public function testDeletedProductAndBuyerRemainReadableWithoutInvalidActions(): void
    {
        $this->order(1, ['good_id' => 999, 'user_id' => 999]);
        $html = view('market.partials.orders_table', ['orders' => (new MarketOrders())->paginate(false, ''), 'showActions' => true, 'showShippedAt' => false])->render();
        $this->assertStringContainsString('Товар удалён', $html);
        $this->assertStringContainsString('Покупатель удалён', $html);
        $this->assertStringNotContainsString('/market/cancel/', $html);
        $this->assertStringNotContainsString('/market/ship/', $html);
    }

    private function order(int $id, array $attributes = []): void
    {
        DB::table('market_deals')->insert(array_replace(['id' => $id, 'user_id' => 1, 'good_id' => 1, 'shipped' => false,
            'created_at' => '2026-11-11 12:00:00', 'updated_at' => '2026-11-11 12:00:00'], $attributes));
    }
}
