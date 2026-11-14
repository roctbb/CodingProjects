import {EditorView, basicSetup} from 'codemirror';
import {python} from '@codemirror/lang-python';
import {cpp} from '@codemirror/lang-cpp';
import {javascript} from '@codemirror/lang-javascript';
import {HighlightStyle, syntaxHighlighting} from '@codemirror/language';
import {tags} from '@lezer/highlight';

const highlighting = syntaxHighlighting(HighlightStyle.define([
    {tag:tags.comment,color:'var(--gc-syntax-comment)'},
    {tag:[tags.keyword,tags.modifier,tags.operatorKeyword],color:'var(--gc-syntax-keyword)'},
    {tag:[tags.string,tags.regexp],color:'var(--gc-syntax-string)'},
    {tag:[tags.number,tags.bool,tags.null],color:'var(--gc-syntax-number)'},
    {tag:[tags.function(tags.variableName),tags.function(tags.propertyName)],color:'var(--gc-syntax-function)'},
]));

export function editor(parent, source, language, change) {
    return new EditorView({parent, doc:source || '', extensions:[basicSetup, highlighting,
        language === 'python' ? python() : language === 'cpp' ? cpp() : ['js','javascript'].includes(language) ? javascript() : [],
        EditorView.contentAttributes.of({'aria-label':'Код решения'}),
        EditorView.updateListener.of(update => {if (update.docChanged) change(update.state.doc.toString());})]});
}
