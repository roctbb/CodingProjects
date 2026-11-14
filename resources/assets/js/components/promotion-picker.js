import axios from 'axios';

export default function initPromotionPickers() {
    document.querySelectorAll('[data-promotion-picker]').forEach(root => {
        const dialog = root.querySelector('[data-promotion-picker-modal]');
        const opener = root.querySelector('[data-promotion-open]');
        const selection = root.querySelector('[data-promotion-selection]');
        const list = root.querySelector('[data-promotion-list]');
        const search = root.querySelector('[data-promotion-search]');
        const kind = root.querySelector('[data-promotion-kind]');
        const status = root.querySelector('[data-promotion-status]');
        const previous = root.querySelector('[data-promotion-prev]');
        const next = root.querySelector('[data-promotion-next]');
        const pageSelect = root.querySelector('[data-promotion-page-select]');
        let selected = new Map([...selection.querySelectorAll('[data-selected-promotion-item]')].map(item => [item.dataset.itemId, {id:item.dataset.itemId, label:item.dataset.itemLabel}]));
        let staged = new Map(), page = 1, hasMore = false, requestId = 0, controller, timer, currentItems = [];
        const count = () => {
            root.querySelector('[data-promotion-staged-count]').textContent = `Выбрано: ${staged.size}`;
            pageSelect.textContent = currentItems.length && currentItems.every(item => staged.has(item.id)) ? 'Убрать страницу' : 'Выбрать страницу';
        };
        const renderSelection = () => {
            selection.replaceChildren();
            let container = selection;
            [...selected.values()].forEach((item, index) => {
                if (index === 6) {
                    const details = document.createElement('details'); details.className = 'gc-promotion-selection__more gc-disclosure';
                    const summary = document.createElement('summary'); summary.textContent = `Ещё ${selected.size - 6} товаров`;
                    container = document.createElement('div'); container.className = 'gc-promotion-selection mt-2';
                    details.append(summary, container); selection.append(details);
                }
                const chip = document.createElement('span'); chip.className = 'gc-promotion-selection__item';
                chip.dataset.selectedPromotionItem = ''; chip.dataset.itemId = item.id; chip.dataset.itemLabel = item.label;
                const input = document.createElement('input'); input.type='hidden'; input.name='settings[promotion][items][]'; input.value=item.id;
                const label = document.createElement('span'); label.textContent=item.label;
                const remove = document.createElement('button'); remove.type='button'; remove.className='gc-text-action'; remove.dataset.promotionRemove=item.id; remove.setAttribute('aria-label', `Убрать ${item.label} из акции`); remove.textContent='×';
                chip.append(input,label,remove); container.append(chip);
            });
            root.querySelector('[data-promotion-selection-count]').textContent = selected.size ? `Выбрано: ${selected.size} · обычных: ${[...selected.keys()].filter(id => id.startsWith('good:')).length} · цифровых: ${[...selected.keys()].filter(id => id.startsWith('digital:')).length}` : 'Выберите товары, на которые будет действовать скидка.';
        };
        renderSelection();
        const load = async () => {
            controller?.abort(); controller = new AbortController();
            const current = ++requestId;
            status.textContent='Загрузка…'; list.replaceChildren(); previous.disabled=true; next.disabled=true; pageSelect.disabled=true; currentItems=[];
            try {
                const {data} = await axios.get(root.dataset.source, {params:{type:kind.value,q:search.value,page},signal:controller.signal});
                if (current !== requestId || !dialog.open) return;
                hasMore=data.has_more;
                currentItems=data.items;
                data.items.forEach(item => {
                    const label=document.createElement('label'); label.className='gc-promotion-picker__item';
                    const check=document.createElement('input'); check.type='checkbox'; check.className='form-check-input'; check.checked=staged.has(item.id); check.dataset.itemId=item.id;
                    const text=document.createElement('span'); text.textContent=item.label;
                    const group=document.createElement('small'); group.textContent=item.group; text.append(group);
                    check.addEventListener('change',()=>{if(check.checked)staged.set(item.id,item);else staged.delete(item.id);count();});
                    label.append(check,text); list.append(label);
                });
                status.textContent=data.items.length ? '' : 'Ничего не найдено.';
                root.querySelector('[data-promotion-page]').textContent=`Страница ${page}`;
                previous.disabled=page===1; next.disabled=!hasMore;
                pageSelect.disabled=!currentItems.length; count();
            } catch(error) {
                if(current!==requestId || error.code==='ERR_CANCELED')return;
                status.textContent='Не удалось загрузить товары.';
                const retry=document.createElement('button'); retry.type='button'; retry.className='btn btn-outline-secondary btn-sm'; retry.textContent='Повторить'; retry.addEventListener('click',load); list.append(retry);
            }
        };
        opener.addEventListener('click',()=>{staged=new Map(selected);page=1;dialog.showModal();count();load();});
        root.querySelectorAll('[data-promotion-cancel]').forEach(button=>button.addEventListener('click',()=>dialog.close()));
        dialog.addEventListener('close',()=>{controller?.abort();requestId++;clearTimeout(timer);opener.focus();});
        root.querySelector('[data-promotion-apply]').addEventListener('click',()=>{selected=new Map(staged);renderSelection();dialog.close();});
        root.addEventListener('click',event=>{const button=event.target.closest('[data-promotion-remove]');if(button){selected.delete(button.dataset.promotionRemove);renderSelection();}});
        search.addEventListener('input',()=>{clearTimeout(timer);page=1;timer=setTimeout(load,250);});
        search.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();clearTimeout(timer);page=1;load();}});
        kind.addEventListener('change',()=>{clearTimeout(timer);page=1;load();});
        previous.addEventListener('click',()=>{if(page>1){page--;load();}});
        next.addEventListener('click',()=>{if(hasMore){page++;load();}});
        pageSelect.addEventListener('click',()=>{
            const allSelected=currentItems.every(item=>staged.has(item.id));
            currentItems.forEach(item=>{if(allSelected)staged.delete(item.id);else staged.set(item.id,item);});
            list.querySelectorAll('input[type=checkbox]').forEach(check=>check.checked=staged.has(check.dataset.itemId));count();
        });
        dialog.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                dialog.close();
            } else if (event.key === 'Enter' && event.target.matches('input[type=checkbox]')) {
                event.preventDefault();
                event.target.checked = !event.target.checked;
                event.target.dispatchEvent(new Event('change'));
            }
        });
    });
}
