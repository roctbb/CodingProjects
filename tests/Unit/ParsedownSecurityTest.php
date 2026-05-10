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

    public function testSafeUrlRejectsJavascriptUrls()
    {
        $this->assertSame('#', safe_url('javascript:alert(1)'));
        $this->assertSame('https://example.com', safe_url('https://example.com'));
        $this->assertSame('/insider/courses', safe_url('/insider/courses'));
    }
}
