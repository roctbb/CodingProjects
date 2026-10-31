import { marked } from 'marked';

const delimiters = [['$$', '$$'], ['\\[', '\\]'], ['\\(', '\\)'], ['$', '$']];

function mathToken(source, display) {
    // Keep escaped currency signs from becoming MathJax delimiters.
    if (!display && source.startsWith('\\$')) {
        return { type: 'notebookInlineMath', raw: '\\$', text: '\\$' };
    }

    for (const [open, close] of delimiters) {
        if ((display && open !== '$$' && open !== '\\[') || !source.startsWith(open)) {
            continue;
        }

        let offset = open.length;
        let end;
        while ((end = source.indexOf(close, offset)) !== -1) {
            let backslashes = 0;
            for (let i = end - 1; i >= 0 && source[i] === '\\'; i--) {
                backslashes++;
            }

            if (backslashes % 2 === 0) {
                const text = source.slice(0, end + close.length);
                return { type: display ? 'notebookDisplayMath' : 'notebookInlineMath', raw: text, text };
            }

            offset = end + close.length;
        }

        return;
    }
}

function escapeMath(text) {
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// Consume TeX before Markdown interprets backslashes, emphasis, or list markers.
marked.use({
    extensions: [
        {
            name: 'notebookDisplayMath',
            level: 'block',
            start(source) { return source.match(/\n(?=\$\$|\\\[)/)?.index; },
            tokenizer(source) { return mathToken(source, true); },
            renderer(token) { return '<p>' + escapeMath(token.text) + '</p>\n'; },
        },
        {
            name: 'notebookInlineMath',
            level: 'inline',
            start(source) { return source.match(/\$|\\[([$]/)?.index; },
            tokenizer(source) { return mathToken(source, false); },
            renderer(token) { return escapeMath(token.text); },
        },
    ],
});

export default function renderNotebookMarkdown(source) {
    return marked(source);
}
