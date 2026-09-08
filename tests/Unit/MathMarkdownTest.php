<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MathMarkdownTest extends TestCase
{
    public static function formulas(): array
    {
        return [
            'convolution' => [<<<'TEX'
$$J[y,x] = \sum_{dy=-r}^{r}\ \sum_{dx=-r}^{r} I[y+dy,\ x+dx] \cdot K[dy+r,\ dx+r].$$
TEX],
            'matrix' => [<<<'TEX'
$$\begin{pmatrix}10&20&30\\40&50&60\\70&80&90\end{pmatrix}$$
TEX],
            'inline dollars' => ['$x_1 * x_2$'],
            'inline parentheses' => ['\(x_1 * x_2\)'],
            'display brackets' => ['\[x_1 * x_2\]'],
            'escaped dollar' => ['$\text{Price: \$5} + x_1$'],
            'escaped dollar before formula' => ['\$5 and $x_1 + x_2$'],
            'multiline matrix' => [<<<'TEX'
$$
\begin{pmatrix}
10 & 20 & 30 \\
40 & 50 & 60 \\
70 & 80 & 90
\end{pmatrix}
$$
TEX],
            'blank lines and markdown markers in display math' => [<<<'TEX'
\[
a_1

* b_2
\]
TEX],
        ];
    }

    #[DataProvider('formulas')]
    public function testPreservesFormulaAsEscapedText(string $formula): void
    {
        $html = parsedown_math($formula);

        $this->assertSame($formula, html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $this->assertStringNotContainsString('<em>', $html);
        $this->assertStringNotContainsString('<li>', $html);
    }

    public function testStillParsesSurroundingMarkdown(): void
    {
        $html = parsedown_math('**Before** $x_1 + x_2$ and \(y_1 + y_2\) *after*.');

        $this->assertSame('<p><strong>Before</strong> $x_1 + x_2$ and \(y_1 + y_2\) <em>after</em>.</p>', $html);
    }

    public function testDisplayBlockEndsBeforeFollowingMarkdown(): void
    {
        $html = parsedown_math("Before\n\n$$\nx_1 + x_2\n$$\n\n**After**");

        $this->assertStringContainsString("$$\nx_1 + x_2\n$$", $html);
        $this->assertStringContainsString('<strong>After</strong>', $html);
    }

    public function testCodeSamplesRemainCode(): void
    {
        $formula = '$$\begin{pmatrix}1&2\\\\3&4\end{pmatrix}$$';
        $escaped = htmlspecialchars($formula, ENT_NOQUOTES, 'UTF-8');

        $this->assertSame('<p><code>'.$escaped.'</code></p>', parsedown_math('`'.$formula.'`'));
        $this->assertSame('<pre><code>'.$escaped.'</code></pre>', parsedown_math("```\n".$formula."\n```"));
        $this->assertSame('<pre><code>'.$escaped.'</code></pre>', parsedown_math('    '.$formula));
    }

    public function testEscapedAndUnmatchedDelimitersStillAllowMarkdown(): void
    {
        $this->assertSame('<p>\$5 and <em>text</em></p>', parsedown_math('\$5 and *text*'));
        $this->assertSame('<p>$x and <strong>text</strong></p>', parsedown_math('$x and **text**'));
    }

    public function testHtmlInsideFormulasRemainsEscaped(): void
    {
        foreach ([['$', '$'], ['$$', '$$'], ['\(', '\)'], ['\[', '\]'], ["$$\n", "\n$$"]] as [$open, $close]) {
            $html = parsedown_math($open.'<img src=x onerror=alert(1)>&lt;script&gt;'.$close);

            $this->assertStringNotContainsString('<img', $html);
            $this->assertStringNotContainsString('<script', $html);
            $this->assertStringContainsString('&lt;img', $html);
            $this->assertStringContainsString('&amp;lt;script&amp;gt;', $html);
        }
    }
}
