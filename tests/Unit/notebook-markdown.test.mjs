import assert from 'node:assert/strict';
import { test } from 'node:test';
import renderNotebookMarkdown from '../../resources/assets/js/notebook-markdown.js';

const formulas = [
    String.raw`\[ \mathrm{accuracy} = \frac{\text{число верных предсказаний}}{\text{число проверенных объектов}}. \]`,
    String.raw`\(x_1 * x_2\)`,
    '$x_1 * x_2$',
    String.raw`$$\begin{pmatrix}1&2\\3&4\end{pmatrix}$$`,
    String.raw`$\text{Price: \$5} + x_1$`,
    '\\[\na_1\n\n* b_2\n\\]',
    '$$\na_1\n\n* b_2\n$$',
];

for (const formula of formulas) {
    test('preserves TeX: ' + formula, () => {
        const html = renderNotebookMarkdown('**Before**\n\n' + formula + '\n\n*After*');
        assert.ok(html.includes(formula.replace(/&/g, '&amp;')));
        assert.ok(html.includes('<strong>Before</strong>'));
        assert.ok(html.includes('<em>After</em>'));
        assert.ok(!html.includes('<li>'));
    });
}

test('display math protects blank lines even without a preceding blank line', () => {
    const formula = '\\[\na_1\n\n* b_2\n\\]';
    const html = renderNotebookMarkdown('Before\n' + formula + '\n**After**');
    assert.ok(html.includes(formula));
    assert.ok(html.includes('<strong>After</strong>'));
    assert.ok(!html.includes('<li>'));
});

test('preserves inline formulas alongside Markdown', () => {
    assert.equal(
        renderNotebookMarkdown(String.raw`**Before** $x_1$ and \(y_1\) *after*.`),
        '<p><strong>Before</strong> $x_1$ and \\(y_1\\) <em>after</em>.</p>\n',
    );
});

test('keeps code samples as code', () => {
    const formula = String.raw`\[x_1 * x_2\]`;
    assert.equal(renderNotebookMarkdown('`' + formula + '`'), '<p><code>' + formula + '</code></p>\n');
    assert.equal(renderNotebookMarkdown('```\n' + formula + '\n```'), '<pre><code>' + formula + '\n</code></pre>\n');
    assert.equal(renderNotebookMarkdown('    ' + formula), '<pre><code>' + formula + '\n</code></pre>\n');
});

test('escapes HTML inside every math delimiter', () => {
    for (const [open, close] of [['$', '$'], ['$$', '$$'], ['\\(', '\\)'], ['\\[', '\\]']]) {
        const html = renderNotebookMarkdown(open + '<img src=x onerror=alert(1)>&lt;script&gt;' + close);
        assert.ok(!html.includes('<img'));
        assert.ok(html.includes('&lt;img'));
        assert.ok(html.includes('&amp;lt;script&amp;gt;'));
    }
});

test('escaped and unmatched delimiters allow ordinary Markdown', () => {
    assert.equal(renderNotebookMarkdown(String.raw`\$5 and *text*`), '<p>\\$5 and <em>text</em></p>\n');
    assert.equal(renderNotebookMarkdown('$x and **text**'), '<p>$x and <strong>text</strong></p>\n');
    assert.equal(renderNotebookMarkdown(String.raw`\$5 and $x_1$`), '<p>\\$5 and $x_1$</p>\n');
});
