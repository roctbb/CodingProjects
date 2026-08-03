<?php

namespace Tests\Unit;

use Tests\TestCase;

class ParsedownSecurityTest extends TestCase
{
    public function testParsedownMathEscapesRawHtml()
    {
        $html = parsedown_math('Hello <img src=x onerror=alert(1)>');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    public function testParsedownMathDoesNotDecodeHtmlInsideMath()
    {
        $html = parsedown_math('$<img src=x onerror=alert(1)>$');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    public function testParsedownMathDropsJavascriptLinks()
    {
        $html = parsedown_math('[click](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function testParsedownMathAllowsExplicitLineBreaksInsideTables()
    {
        $markdown = "| Ввод | Вывод |\n"
            . "|---|---|\n"
            . "| `6`<br>`нет` | `Проход разрешён` |";

        $html = parsedown_math($markdown);

        $this->assertStringContainsString('<table>', $html);
        $this->assertMatchesRegularExpression('/<code>6<\/code>\s*<br\s*\/?>\s*<code>нет<\/code>/', $html);
    }

    public function testParsedownMathDoesNotInterpretBreakTagInsideCode()
    {
        $html = parsedown_math('Используйте тег `<br>` для переноса.');

        $this->assertStringContainsString('<code>&lt;br&gt;</code>', $html);
        $this->assertDoesNotMatchRegularExpression('/<code>\s*<br\s*\/?>\s*<\/code>/', $html);
    }

    public function testParsedownMathEscapesBreakTagWithAttributes()
    {
        $html = parsedown_math('До<br onclick="alert(1)">после');

        $this->assertStringNotContainsString('<br onclick=', $html);
        $this->assertStringContainsString('&lt;br onclick=', $html);
    }

    public function testSafeUrlRejectsJavascriptUrls()
    {
        $this->assertSame('#', safe_url('javascript:alert(1)'));
        $this->assertSame('https://example.com', safe_url('https://example.com'));
        $this->assertSame('/insider/courses', safe_url('/insider/courses'));
    }
}
