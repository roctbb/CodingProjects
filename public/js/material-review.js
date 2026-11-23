(() => {
    const root=document.querySelector('[data-material-review]'); if(!root)return;
    const errorBox=root.querySelector('[data-review-error]'),editor=document.querySelector('[data-review-editor]');
    const text=editor.querySelector('[data-editor-text]'),reason=editor.querySelector('[data-editor-reason]'),editorError=editor.querySelector('[data-editor-error]');
    const parts=editor.querySelector('[data-editor-parts]'),addCell=editor.querySelector('[data-editor-add-cell]');
    const draft=root.dataset.draft,version=Number(root.dataset.version),key=`material-review:${draft}:editor`;
    const imageDialog=document.createElement('dialog');imageDialog.className='gc-dialog mr-image-zoom';const closeForm=document.createElement('form');closeForm.method='dialog';const closeImage=document.createElement('button');closeImage.className='btn-close float-end';closeImage.setAttribute('aria-label','Закрыть изображение');closeForm.append(closeImage);const enlargedImage=document.createElement('img');imageDialog.append(closeForm,enlargedImage);document.body.append(imageDialog);
    const proposals=JSON.parse(document.querySelector('#review-proposal-data')?.textContent||'[]');
    let editing=null,busy=false;
    const navigation=root.querySelector('[data-review-navigation]'),proposalPanel=root.querySelector('[data-review-proposals]');
    let compactNavigation,compactProposals;
    const responsivePanels=()=>{const width=root.clientWidth,navCompact=width<=1050,proposalsCompact=width<=700;if(navigation&&navCompact!==compactNavigation)navigation.open=!navCompact;if(proposalPanel&&proposalsCompact!==compactProposals)proposalPanel.open=!proposalsCompact;compactNavigation=navCompact;compactProposals=proposalsCompact;};
    new ResizeObserver(responsivePanels).observe(root);responsivePanels();
    root.querySelectorAll('a[href="#publication-preview"]').forEach(link=>link.addEventListener('click',()=>{const final=root.querySelector('[data-final-result]');if(final)final.open=true;}));
    const request=async(url,data)=>{const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify(data)});const body=await response.json();if(!response.ok)throw new Error(body.message||Object.values(body.errors||{}).flat().join('\n')||'Не удалось сохранить изменение.');return body;};
    const action=(name,data={})=>request(`/insider/material-review/drafts/${draft}/${name}`,{version,...data});
    const showError=e=>{errorBox.textContent=e.message;errorBox.hidden=false;};
    const source=value=>Array.isArray(value)?value.join(''):(value||'');
    const partValues=()=>[...parts.querySelectorAll('textarea')].map(el=>el.value);
    const remember=()=>{if(editing)sessionStorage.setItem(key,JSON.stringify({editing,text:text.value,reason:reason.value,parts:partValues()}));};
    const renderParts=values=>{
        parts.replaceChildren();const multiple=!!editing?.book||!!editing?.proposal;
        text.hidden=multiple;editor.querySelector('[data-editor-text-label]').hidden=multiple;addCell.hidden=!editing?.book;
        const items=editing?.book?editing.book.cells.map((cell,i)=>({label:`Ячейка ${i+1} · ${cell.cell_type==='code'?'Код':'Текст'}`,value:source(cell.source)})):(editing?.proposal?editing.proposal.payload.operations.map((op,i)=>({label:`Часть ${i+1}`,value:source(op.op==='fragment'?op.new:op.value)})):[]);
        items.forEach((item,i)=>{const label=document.createElement('label');label.className='form-label d-block mt-3';label.textContent=item.label;const input=document.createElement('textarea');input.className='form-control';input.rows=5;input.value=values?.[i]??item.value;input.addEventListener('input',remember);label.append(input);parts.append(label);});
    };
    addCell.addEventListener('click',()=>{const values=partValues();editing.book.cells.push({id:crypto.randomUUID(),cell_type:'markdown',metadata:{},source:''});values.push('');renderParts(values);remember();});
    text.addEventListener('input',remember);reason.addEventListener('input',remember);
    root.addEventListener('click',async event=>{
        const button=event.target.closest('button');if(!button||busy)return;
        if(button.dataset.imagePreview){enlargedImage.src=button.dataset.imagePreview;enlargedImage.alt=button.querySelector('img')?.alt||'Иллюстрация';imageDialog.showModal();return;}
        if(button.dataset.scope){const scope=button.dataset.scope;root.querySelectorAll('[data-review-scope]').forEach(el=>el.hidden=scope!=='all'&&el.dataset.reviewScope!==scope);root.querySelectorAll('[data-review-original]').forEach(el=>el.hidden=scope==='all'||el.dataset.reviewOriginal===scope);root.querySelectorAll('[data-scope]').forEach(el=>el.setAttribute('aria-pressed',String(el===button)));if(compactProposals&&proposalPanel)proposalPanel.open=true;return;}
        if(button.dataset.locate){const pid=button.dataset.locate,target=root.querySelector(`[data-proposal-inline="${CSS.escape(pid)}"]`);root.querySelectorAll('.is-current').forEach(el=>el.classList.remove('is-current'));button.closest('.mr-proposal')?.classList.add('is-current');if(target){target.hidden=false;target.classList.add('is-current');if(target.tagName==='DETAILS')target.open=true;for(let p=target.parentElement;p&&p!==root;p=p.parentElement)if(p.tagName==='DETAILS')p.open=true;if(compactProposals&&proposalPanel)proposalPanel.open=false;target.scrollIntoView({behavior:'smooth',block:'center'});}return;}
        if(button.hasAttribute('data-edit-field')||button.hasAttribute('data-edit-book')){editing={entity:button.dataset.entity,id:button.dataset.id,field:button.dataset.field,expected:JSON.parse(button.dataset.expected)};if(button.hasAttribute('data-edit-book'))editing.book=JSON.parse(button.dataset.value);text.value=button.dataset.value;reason.value='Авторское уточнение материала.';editorError.textContent='';renderParts();editor.showModal();remember();return;}
        if(button.dataset.editProposal){const p=proposals.find(p=>p.id===button.dataset.editProposal);editing={proposal:p};reason.value=p.reason;editorError.textContent='';renderParts();editor.showModal();remember();return;}
        try{
            busy=true;
            if(button.dataset.transfer){await action('transfer',{source_draft_id:button.dataset.transfer,source_version:Number(button.dataset.sourceVersion),paths:[...new Set([...root.querySelectorAll('[data-transfer-path]:checked')].flatMap(el=>JSON.parse(el.dataset.transferPaths)))]});location.reload();}
            else if(button.dataset.decision||button.dataset.saveComment){const pid=button.dataset.proposal||button.dataset.saveComment;const comment=root.querySelector(`[data-comment="${CSS.escape(pid)}"]`)?.value||'';await action('decision',{proposal_id:pid,decision:button.dataset.decision||button.dataset.currentDecision,comment});location.reload();}
            else if(button.dataset.action){if(button.dataset.action==='publish'&&!root.querySelector('[data-publication-confirm]').checked)throw new Error('Просмотрите итоговый урок и отметьте подтверждение перед публикацией.');const result=await action(button.dataset.action,{publication_id:root.dataset.publication});if(button.dataset.action==='reverse')location.href='/insider/material-review?draft='+result.id;else if(button.dataset.next)location.href=button.dataset.next;else location.reload();}
        }catch(e){showError(e);}finally{busy=false;}
    });
    editor.querySelector('[data-editor-preview]').addEventListener('click',async()=>{try{const body=await request('/insider/material-review/preview',{text:editing?.book||editing?.proposal?partValues().join('\n\n---\n\n'):text.value});editor.querySelector('[data-editor-preview-body]').innerHTML=body.html;if(window.MathJax?.typesetPromise)await window.MathJax.typesetPromise([editor]);}catch(e){editorError.textContent=e.message;}});
    editor.querySelector('[data-editor-save]').addEventListener('click',async()=>{
        if(busy||!editing)return;busy=true;remember();
        try{
            if(editing.proposal){const p=editing.proposal,ops=structuredClone(p.payload.operations),values=partValues();ops.forEach((op,i)=>{if(op.op==='fragment')op.new=values[i];else op.value=values[i];});await action('proposal',{proposal:{...p.payload,external_id:p.external_id,reason:reason.value,operations:ops}});}
            else {let value=text.value;if(editing.book){const book=structuredClone(editing.book),values=partValues();book.cells.forEach((cell,i)=>{if(source(cell.source)!==values[i])cell.source=values[i];});value=JSON.stringify(book);}await action('update',{change:{reason:reason.value,operations:[{op:'replace',entity:editing.entity,id:/^\d+$/.test(editing.id)?Number(editing.id):editing.id,field:editing.field,expected:editing.expected,value}]}});}
            sessionStorage.removeItem(key);location.reload();
        }catch(e){editorError.textContent=e.message;}finally{busy=false;}
    });
    root.querySelector('[data-program-search]')?.addEventListener('input',event=>{const q=event.target.value.toLocaleLowerCase();root.querySelectorAll('[data-program-name]').forEach(el=>el.hidden=!el.dataset.programName.includes(q));});
    try{const saved=JSON.parse(sessionStorage.getItem(key));if(saved?.editing){editing=saved.editing;text.value=saved.text;reason.value=saved.reason;renderParts(saved.parts);editorError.textContent='Восстановлен несохранённый текст. Перед сохранением проверьте актуальную основу.';editor.showModal();}}catch{}
})();
