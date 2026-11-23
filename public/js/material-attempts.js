document.querySelectorAll('[data-material-attempt]').forEach(form=>{
    const input=form.querySelector('[name=text]'),key=`material-attempt:${form.action}`;
    if(!input)return;
    const saved=sessionStorage.getItem(key);if(saved!==null)input.value=saved;
    input.addEventListener('input',()=>sessionStorage.setItem(key,input.value));
    form.addEventListener('submit',async event=>{
        event.preventDefault();sessionStorage.setItem(key,input.value);
        let message=form.querySelector('[data-material-error]');if(!message){message=document.createElement('p');message.dataset.materialError='';message.className='alert alert-warning';message.setAttribute('role','alert');form.append(message);}
        try{
            const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json'},body:new FormData(form)});
            const data=await response.json();if(!response.ok){message.textContent=data.message||Object.values(data.errors||{}).flat().join('\n')||'Не удалось отправить работу.';if(response.status===409){const button=document.createElement('button');button.type='button';button.className='btn btn-outline-primary ms-2';button.textContent='Обновить условие';button.onclick=()=>location.reload();message.append(button);}return;}
            sessionStorage.removeItem(key);location.reload();
        }catch{message.textContent='Связь прервалась. Ваш ответ сохранён в этом браузере.';}
    });
});
