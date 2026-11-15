import {marked} from 'marked';
import {node} from './ui';

const tags = new Set(['P','BR','H1','H2','H3','H4','H5','H6','STRONG','EM','DEL','BLOCKQUOTE','UL','OL','LI','PRE','CODE','TABLE','THEAD','TBODY','TR','TH','TD','HR','A']);

export function markdownContent(value) {
    const template = document.createElement('template');
    template.innerHTML = marked.parse(String(value || ''), {mangle:false, headerIds:false});
    const clean = parent => {
        for (const el of [...parent.children]) {
            if (!tags.has(el.tagName)) {el.replaceWith(document.createTextNode(el.textContent)); continue;}
            const href = el.tagName === 'A' ? el.getAttribute('href') : null;
            for (const attr of [...el.attributes]) el.removeAttribute(attr.name);
            if (href) {
                try {const url = new URL(href, location.href); if (['http:','https:','mailto:'].includes(url.protocol)) {el.setAttribute('href',url.href);el.setAttribute('rel','noopener noreferrer');el.setAttribute('target','_blank');}} catch {}
            }
            clean(el);
        }
    };
    clean(template.content);
    return node('div', {class:'gc-feedback-markdown markdown'}, template.content);
}

export function feedbackPanel(comment, {title='Комментарий преподавателя', collapsible=false, open=true, meta=''}={}) {
    const heading = node(collapsible ? 'summary' : 'header', {}, node('div', {}, node('h4', {class:'h6 mb-0'}, title), meta ? node('span', {class:'gc-help-text'},meta) : null));
    if (collapsible) heading.append(node('i',{class:'fas fa-chevron-down','aria-hidden':'true'}));
    return node(collapsible ? 'details' : 'section', {class:'gc-grading-feedback', ...(collapsible ? {open} : {})}, heading, markdownContent(comment));
}
