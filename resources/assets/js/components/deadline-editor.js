export function stageRow(index, stage = {}) {
    // Values originate from native inputs or a validated endpoint; never interpolate arbitrary HTML.
    const date = /^\d{4}-\d\d-\d\d$/.test(stage.deadline_date || '') ? stage.deadline_date : '';
    const percent = Number.isFinite(Number(stage.retained_percent)) && Number(stage.retained_percent) >= 0 && Number(stage.retained_percent) <= 100 ? Number(stage.retained_percent) : 70;
    return `<div class="row g-2 align-items-end" data-deadline-row>
        <div class="col-12 col-sm-7"><label class="form-label w-100 mb-0">Срок сдачи · до 23:59<input class="form-control" type="date" name="stages[${index}][deadline_date]" value="${date}" required></label></div>
        <div class="col-9 col-sm-4"><label class="form-label w-100 mb-0">После срока<span class="input-group"><input class="form-control" type="number" name="stages[${index}][retained_percent]" min="0" max="100" step="any" value="${percent}" required><span class="input-group-text">%</span></span></label></div>
        <div class="col-3 col-sm-1 pb-1"><button class="btn btn-outline-secondary gc-icon-button" type="button" data-deadline-remove aria-label="Убрать срок"><i class="fas fa-xmark" aria-hidden="true"></i></button></div></div>`;
}

export function initDeadlineEditor(editor) {
    if (editor.dataset.initialized) return;
    editor.dataset.initialized = 'true';
    const rows = editor.querySelector('[data-deadline-rows]');
    const mode = editor.querySelector('[data-deadline-mode]');
    let next = rows.children.length;
    const update = () => {
        const active = mode.value === 'custom';
        editor.querySelector('[data-deadline-stages]').hidden = !active;
        rows.querySelectorAll('input').forEach(input => { input.disabled = !active; });
    };
    mode.addEventListener('change', () => {
        if (mode.value === 'custom' && !rows.children.length) rows.insertAdjacentHTML('beforeend', stageRow(next++));
        update();
    });
    editor.addEventListener('click', event => {
        if (event.target.closest('[data-deadline-add]')) { rows.insertAdjacentHTML('beforeend', stageRow(next++)); rows.lastElementChild.querySelector('input').focus(); }
        const remove = event.target.closest('[data-deadline-remove]');
        if (remove) remove.closest('[data-deadline-row]').remove();
    });
    update();
}

export default function initDeadlineEditors() {
    document.querySelectorAll('[data-deadline-editor]').forEach(initDeadlineEditor);
}

export function deadlinePayload(form) {
    const editor = form.querySelector('[data-deadline-editor]');
    const mode = editor.querySelector('[data-deadline-mode]').value;
    return {
        policy_revision: Number(editor.querySelector('[name="revision"]').value),
        timezone: editor.querySelector('[name="timezone"]').value,
        mode,
        stages: mode !== 'custom' ? [] : [...editor.querySelectorAll('[data-deadline-row]')].map(row => ({
            deadline_date: row.querySelector('input[type="date"]').value,
            retained_percent: Number(row.querySelector('input[type="number"]').value),
        })),
    };
}
