import autosize from 'autosize';

export default function initSolutionReview() {
    const syncQueue = () => {
        const id = window.location.hash.match(/^#solution-(\d+)$/)?.[1];
        const selected = id && document.querySelector(`[data-review-queue-solution="${id}"]`);
        if (!selected) return;
        document.querySelectorAll('[data-review-queue-solution]').forEach(item => {
            item.classList.toggle('is-current', item === selected);
            if (item === selected) item.setAttribute('aria-current', 'true'); else item.removeAttribute('aria-current');
        });
    };
    syncQueue();
    window.addEventListener('hashchange', syncQueue);
    const queue = document.getElementById('review-queue-picker');
    queue?.addEventListener('shown.bs.offcanvas', () => {
        queue.querySelector('[aria-current="true"]')?.scrollIntoView({block:'nearest'});
    });

    document.querySelectorAll('[data-solution-recheck-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const feedback = document.getElementById(button.dataset.solutionFeedbackId);
            const form = document.getElementById(button.dataset.solutionFormId);
            if (!feedback || !form) return;

            feedback.hidden = true;
            form.hidden = false;
            form.classList.remove('is-hidden');

            const commentInput = form.querySelector('textarea[name="comment"]');
            if (commentInput) {
                autosize.update(commentInput);
                commentInput.focus();
                commentInput.setSelectionRange(commentInput.value.length, commentInput.value.length);
            }
        });
    });

    document.querySelectorAll('[data-solution-recheck-cancel]').forEach(function (button) {
        button.addEventListener('click', function () {
            const feedback = document.getElementById(button.dataset.solutionFeedbackId);
            const form = document.getElementById(button.dataset.solutionFormId);
            if (!feedback || !form) return;

            form.hidden = true;
            form.classList.add('is-hidden');
            feedback.hidden = false;
            feedback.querySelector('[data-solution-recheck-toggle]')?.focus();
        });
    });
}
