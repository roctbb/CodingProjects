<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthoringPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        DB::unprepared("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role TEXT, email_verified_at TEXT); INSERT INTO users VALUES (1,'Teacher','teacher','2026-01-01'),(2,'Student','student','2026-01-01'),(3,'Admin','admin','2026-01-01')");
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function testPreviewUsesStudentMarkdownRendererAndEscapesUnsafeHtml(): void
    {
        $source = "## Проверка\n\n| Ввод | Вывод |\n|---|---|\n| `6`<br>`нет` | Да |\n\n\\(x^2\\)\n\n<img src=x onerror=alert(1)>";
        $this->actingAs(User::find(1))->postJson('/insider/authoring/preview', ['text' => $source])
            ->assertOk()->assertJsonPath('html', parsedown_math($source));
        $html = $this->postJson('/insider/authoring/preview', ['text' => $source])->json('html');
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('\\(x^2\\)', $html);
    }

    public function testPreviewIsReadOnlyAndRestrictedToAuthors(): void
    {
        $this->postJson('/insider/authoring/preview', ['text' => 'Draft'])->assertUnauthorized();
        $this->actingAs(User::find(2))->postJson('/insider/authoring/preview', ['text' => 'Draft'])->assertForbidden();
        $this->actingAs(User::find(3))->postJson('/insider/authoring/preview', ['text' => null])->assertOk()->assertJsonPath('html', '');
        $this->postJson('/insider/authoring/preview', ['text' => ['invalid']])->assertUnprocessable();
        $this->assertDatabaseCount('users', 3);
    }
}
