let editorModule;

function loadEditor() {
    if(globalThis.EasyMDE)return Promise.resolve(globalThis.EasyMDE);
    return editorModule ||= import('easymde').then(module=>module.default).catch(error=>{editorModule=null;throw error;});
}

// Keep the textarea usable while the editor loads, and release its handlers on attempt changes.
export function commentEditor(textarea) {
    let instance,disposed=false;
    const sync=()=>textarea.dispatchEvent(new Event('input',{bubbles:true}));
    const controller={
        get ready(){return !!instance;},
        refresh(){if(instance && textarea.isConnected)instance.codemirror.refresh();},
        focus(){if(instance)instance.codemirror.focus();else textarea.focus();},
        destroy(){disposed=true;if(instance){instance.codemirror.off('change',sync);instance.cleanup();instance.toTextArea();instance=null;}},
    };
    loadEditor().then(MDE=>{
        if(disposed || !textarea.isConnected)return;
        const focused=document.activeElement===textarea,start=textarea.selectionStart,end=textarea.selectionEnd;
        instance=new MDE({element:textarea,spellChecker:false,forceSync:true,autoDownloadFontAwesome:false,
            autofocus:false,status:false,minHeight:'190px',inputStyle:'contenteditable',previewImagesInEditor:false,toolbarButtonClassPrefix:'gc-comment',
            shortcuts:{togglePreview:null,toggleSideBySide:null,toggleFullScreen:null},
            insertTexts:{table:['','\n\n| Критерий | Комментарий |\n| --- | --- |\n|  |  |\n\n']},
            toolbar:[
                {name:'bold',action:MDE.toggleBold,className:'fa fa-bold',title:'Полужирный'},
                {name:'italic',action:MDE.toggleItalic,className:'fa fa-italic',title:'Курсив'},
                {name:'heading',action:MDE.toggleHeadingSmaller,className:'fa fa-heading',title:'Заголовок'},
                '|',
                {name:'unordered-list',action:MDE.toggleUnorderedList,className:'fa fa-list-ul',title:'Маркированный список'},
                {name:'ordered-list',action:MDE.toggleOrderedList,className:'fa fa-list-ol',title:'Нумерованный список'},
                '|',
                {name:'link',action:MDE.drawLink,className:'fa fa-link',title:'Ссылка'},
                {name:'code',action:MDE.toggleCodeBlock,className:'fa fa-code',title:'Код'},
                {name:'table',action:MDE.drawTable,className:'fa fa-table',title:'Таблица'},
            ],
        });
        const cm=instance.codemirror,input=cm.getInputField();
        input.setAttribute('role','textbox');input.setAttribute('aria-multiline','true');
        input.setAttribute('aria-label','Комментарий ученику');input.setAttribute('aria-describedby',textarea.id+'-help');
        instance.gui.toolbar.setAttribute('aria-label','Форматирование комментария');
        instance.gui.toolbar.querySelectorAll('button').forEach(button=>button.type='button');
        cm.on('change',sync);textarea.dataset.markdownEditorReady='1';
        if(focused){cm.focus();cm.setSelection(cm.posFromIndex(start),cm.posFromIndex(end));}
    }).catch(()=>{if(!disposed)textarea.dataset.markdownEditorReady='fallback';});
    return controller;
}
