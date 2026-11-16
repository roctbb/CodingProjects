import initAchievements from './components/achievements';
import initLearningAvatar from './components/learning-avatar';
import initSolutionReview from './components/solution-review';
import initReadingWorkspace from './components/reading-workspace';
import initContentTabs from './components/content-tabs';
import initProfileLinks from './components/profile-links';
import initInterfaceControls from './components/interface-controls';
import initWorkbookNavigation from './components/workbook-navigation';
import initTabStrips from './components/tab-strip';
import initDeadlineWorkspaces from './components/deadline-workspace';
import initDeadlineEditors from './components/deadline-editor';
import initDeadlineSummaries from './components/deadline-summary';
import initSelectionControls from './components/selection-controls';
import initAvatarFrameEditor from './components/avatar-frame-editor';
import initCollectionFilters from './components/collection-filters';
import initMarketWorkspace from './components/market-workspace';
import initMarketPreviews from './components/market-preview';
import initAssessmentWorkspaces from './components/assessment-workspace';
import initReportWorkspaces from './components/report-workspace';
import initPromotionPickers from './components/promotion-picker';
import { initContentMedia, initCodeHighlighting } from './components/content-media';
import { initMarkdownEditors, initImproveText } from './components/markdown-editor';
import initNavigation from './components/navigation';
import initCharts from './components/charts';
import initFormActions from './components/form-actions';
import initFormWorkspaces from './components/form-workspace';
import initAccountForms from './components/account-forms';
import initAiSettings from './components/ai-settings';
import initRewardNotices from './components/reward-notices';
import initCodeChecking from './components/code-checking';
import initScreenPet from './components/screen-pet';

// Compatibility entry: retain legacy globals and run each initializer once.
import axios from 'axios';
import * as bootstrap from 'bootstrap';
import initRoomCharacters from './room-character';
import initInterfaceForms from './components/interface-forms';
import initChapterTimelines from './components/chapter-timeline';
import autosize from 'autosize';
import flatpickr from 'flatpickr';
import { Russian } from 'flatpickr/dist/l10n/ru.js';
import hljs from 'highlight.js/lib/core';
import python from 'highlight.js/lib/languages/python';
import javascript from 'highlight.js/lib/languages/javascript';
import xml from 'highlight.js/lib/languages/xml';
import css from 'highlight.js/lib/languages/css';
import json from 'highlight.js/lib/languages/json';
import bash from 'highlight.js/lib/languages/bash';
import sql from 'highlight.js/lib/languages/sql';
import cpp from 'highlight.js/lib/languages/cpp';
import java from 'highlight.js/lib/languages/java';
import csharp from 'highlight.js/lib/languages/csharp';

hljs.registerLanguage('python', python);
hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('xml', xml);
hljs.registerLanguage('css', css);
hljs.registerLanguage('json', json);
hljs.registerLanguage('bash', bash);
hljs.registerLanguage('sql', sql);
hljs.registerLanguage('cpp', cpp);
hljs.registerLanguage('java', java);
hljs.registerLanguage('csharp', csharp);

window.axios = axios;
window.autosize = autosize;
window.flatpickr = flatpickr;
window.flatpickr.localize(Russian);
window.hljs = hljs;
window.bootstrap = bootstrap;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

var token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

document.addEventListener('DOMContentLoaded', function () {
    initRoomCharacters();
    initInterfaceForms();
    initAccountForms();
    initChapterTimelines();
    // Auto-init toasts
    document.querySelectorAll('.toast[data-bs-autohide]').forEach(function (el) {
        new bootstrap.Toast(el).show();
    });

    initAchievements();

    initLearningAvatar();

    initSolutionReview();

    const reading = initReadingWorkspace();
    initContentTabs({ onChange: () => reading.refresh() });
    initProfileLinks();
    initInterfaceControls();
    initWorkbookNavigation();
    initTabStrips();
    initDeadlineWorkspaces();
    initDeadlineEditors();
    initDeadlineSummaries();

    if (window.autosize) {
        window.autosize(document.querySelectorAll('textarea'));
    }

    document.querySelectorAll('[data-progress-width]').forEach(function (progressBar) {
        var width = parseFloat(progressBar.dataset.progressWidth);
        if (!Number.isNaN(width)) progressBar.style.width = Math.max(0, Math.min(100, width)) + '%';
    });

    document.querySelectorAll('[data-progress-height]').forEach(function (progressElement) {
        var target = progressElement.classList.contains('progress') ? progressElement : progressElement.closest('.progress');
        if (target) target.style.height = progressElement.dataset.progressHeight;
    });

    initSelectionControls();

    initAvatarFrameEditor();

    initCollectionFilters();
    initMarketWorkspace();
    initMarketPreviews();
    initAssessmentWorkspaces();
    initReportWorkspaces();
    initPromotionPickers();

    initContentMedia();
    reading.refresh();
    initCodeHighlighting();

    initMarkdownEditors();
    initFormWorkspaces();
    initCodeChecking();
    initAiSettings();

    initNavigation();

    initImproveText();

    initCharts();

    initFormActions();
    initRewardNotices();
    initScreenPet();

});
