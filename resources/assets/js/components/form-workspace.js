export default function initFormWorkspaces() {
    document.querySelectorAll('[data-authoring-form], [data-profile-form], [data-account-form], [data-auth-form]').forEach(form => {
        const state = document.querySelector(`[data-form-state="${form.id}"]`);
        const focusField = id => {
            const input = form.querySelector(`[id="${CSS.escape(id)}"]`);
            if (!input) return;
            for(let parent=input.parentElement;parent && parent!==form;parent=parent.parentElement)if(parent.tagName==='DETAILS')parent.open=true;
            const editor = window.markdownEditors?.[id];
            editor?.codemirror.refresh();
            const target = editor?.codemirror.getWrapperElement() || form.querySelector(`[data-field="${CSS.escape(id)}"]`) || input;
            target.scrollIntoView({block:'center'});
            if (editor) { if(editor.isPreviewActive())window.EasyMDE.togglePreview(editor); editor.codemirror.focus(); } else if (input.matches('select[data-enhanced-multiselect]')) document.getElementById(id+'-control')?.focus(); else (input.matches('fieldset') ? input.querySelector('input,select,textarea,button') : input)?.focus();
        };
        form.querySelectorAll('[data-field]').forEach(field => {
            const id = field.dataset.field, input = field.querySelector(`[id="${CSS.escape(id)}"]`), error = field.querySelector('[data-field-error]');
            if (!input) return;
            const described = [document.getElementById(id+'-help')?.id, error && !error.hidden ? error.id : null].filter(Boolean).join(' ');
            const visible = window.markdownEditors?.[id]?.codemirror.getInputField() || document.getElementById(id+'-control') || input;
            if (described) visible.setAttribute('aria-describedby', described);
            if (error && !error.hidden) visible.setAttribute('aria-invalid','true');
            input.addEventListener('input', () => { if(error?.dataset.clientError){error.hidden=true;delete error.dataset.clientError;} });
        });
        const updateMode = () => {
            const mode = form.querySelector('[name=answer_mode]:checked')?.value;
            const answer = form.querySelector('[data-short-answer]');
            if (answer && mode) {
                answer.disabled = mode !== 'short'; answer.required = mode === 'short';
                answer.closest('[data-field]').hidden = mode !== 'short';
            }
            const achievement = form.querySelector('[data-authoring-achievement]');
            const instruction = form.querySelector('[data-authoring-achievement-instruction]');
            if (achievement && instruction) {
                instruction.disabled = !achievement.checked;
                instruction.closest('[data-field]').hidden = !achievement.checked;
            }
        };
        updateMode(); form.addEventListener('change', updateMode);
        form.querySelectorAll('[data-authoring-optional]').forEach(details=>details.addEventListener('toggle',()=>{
            if(details.open)details.querySelectorAll('[data-markdown-editor]').forEach(input=>window.markdownEditors?.[input.id]?.codemirror.refresh());
        }));
        const dirty = () => { if (state) state.textContent = 'Есть несохранённые изменения'; };
        form.addEventListener('input',dirty); form.addEventListener('change',dirty);
        (form.matches('[data-registration-flow]') ? [] : form.closest('[data-authoring-page], [data-form-page]')?.querySelectorAll('[data-field-error-link]') || []).forEach(link => link.addEventListener('click',event=>{event.preventDefault();focusField(decodeURIComponent(link.hash.slice(1)));}));
        const submitButtons = () => [...form.querySelectorAll('button[type=submit]'), ...document.querySelectorAll(`button[form="${form.id}"][type=submit]`)];
        form.addEventListener('submit', event => {
            if (event.defaultPrevented) return;
            form.setAttribute('aria-busy','true');
            submitButtons().forEach(button=>button.disabled=true);
            if(state)state.textContent='Сохраняем…';
        });
        window.addEventListener('pageshow',()=>{form.removeAttribute('aria-busy');submitButtons().forEach(button=>button.disabled=false);});
    });
}
