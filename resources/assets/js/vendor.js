import jq from 'jquery';
import axios from 'axios';
import autosize from 'autosize';
import flatpickr from 'flatpickr';
import hljs from 'highlight.js';
import { marked } from 'marked';
import Prism from 'prismjs';
import linkifyHtml from 'linkify-html';
import 'bootstrap';
import 'bootstrap-select';
import 'jquery-ui-dist/jquery-ui';
import 'prismjs/components/prism-python';

const $ = window.jQuery || window.$ || jq;
window.$ = window.jQuery = $;
window.axios = axios;
window.autosize = autosize;
window.flatpickr = flatpickr;
window.hljs = hljs;
window.marked = marked;
window.Prism = Prism;

if ($.fn && !$.fn.linkify) {
    $.fn.linkify = function (options) {
        return this.each(function () {
            this.innerHTML = linkifyHtml(this.innerHTML, options || {});
        });
    };
}

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

var token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

document.addEventListener('DOMContentLoaded', function () {
    var url = document.location.toString();

    if (url.match('#')) {
        $('a[href="#' + url.split('#')[1] + '"]').tab('show');
    }

    $('.nav-tabs a').on('shown.bs.tab', function (event) {
        window.location.hash = event.target.hash;
    });

    $('.nav-link').on('click', function () {
        $('.nav-link.active').removeClass('active');
    });

    if ($.fn.datepicker) {
        $('.date').datepicker({
            changeMonth: true,
            changeYear: true,
            yearRange: '1940:2025',
            dateFormat: 'yy-mm-dd'
        });
    }

    if ($.fn.linkify) {
        $('div.markdown').linkify({
            target: '_blank'
        });
    }

    $('div.markdown a').attr('target', '_blank');

    if ($.fn.selectpicker) {
        $('.selectpicker').selectpicker();
    }

    if ($.fn.popover) {
        $(document).popover({
            selector: '[data-toggle=popover]',
            trigger: 'hover'
        });
    }

    if ($.fn.dropdown) {
        $('[data-toggle="dropdown"]').dropdown();
    }

    if (window.MathJax && window.MathJax.typesetPromise) {
        window.MathJax.typesetPromise();
    }

    if (window.autosize) {
        window.autosize(document.querySelectorAll('textarea'));
    }

    if (window.hljs) {
        document.querySelectorAll('pre code').forEach(function (block) {
            window.hljs.highlightElement(block);
        });
    }
});
