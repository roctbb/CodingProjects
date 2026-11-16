import {node,button,request,openDialog} from './ui';

export function awardPanel(record, {student=false,onChange=()=>{}}={}) {
    if (record.awarded_xp === null || record.awarded_xp === undefined) return null;
    const award = record.award_breakdown || {}, penalty = Number(award.deadline_penalty || 0);
    const panel = node('section', {class:'gc-award'+(student?' is-student':''), 'aria-label':'Начисление опыта'});
    if(award.xp_booster>0)panel.append(node('p',{class:'gc-help-text mb-0'},`Бустер: +${award.xp_booster} баллов до применения штрафа.`));
    const confirm = kind => {
        const action = award.actions?.[kind]; if (!action) return;
        const booster = kind === 'booster';
        const details = node('div',{},node('p',{},booster ? `Бустер добавит до ${action.added_points} баллов до применения штрафа, в пределах максимума задачи.` : action.teacher ? 'Штраф будет снят бесплатно. Монетки ученика сохранятся.' : `Цена снятия: ${award.late_days} дн. опоздания × 3 GC.`),
            node('div',{class:'gc-award-preview'},node('span',{},'Зачтённый опыт'),node('strong',{},`${record.awarded_xp} → ${action.preview_xp} XP`)),
            node('p',{class:'gc-help-text'},booster ? 'Один раз для этой сдачи. Награда GC за решение с бустером не начисляется.' : 'Баллы за работу не меняются.'),
            node('p',{class:'gc-help-text'},action.teacher ? 'Бесплатное действие преподавателя' : action.cost===0 ? 'Подарок питомца · 0 GC' : `К списанию: ${action.cost} GC · баланс: ${award.balance} GC`));
        const status = node('p',{class:'gc-help-text',role:'status'});details.append(status);
        let dialog;
        const commit = button(booster?'Применить бустер':'Снять штраф',async()=>{
            commit.disabled=true;
            try {const value=await request(action.url,'POST',{expected_price:action.cost});dialog.close();await onChange(value);}
            catch(error) {status.textContent=error.message;commit.disabled=false;}
        },action.teacher?'primary':'coin');
        commit.disabled=!action.available;
        if (!action.available) status.textContent='Недостаточно GC. Монетки не списаны.';
        dialog = openDialog(booster?'Применить бустер':'Снять штраф за опоздание',details,[button('Отмена',()=>dialog.close()),commit]);
    };
    if (penalty) {
        const note = node('div',{class:'gc-award__penalty'},node('i',{class:'fas fa-calendar-days','aria-hidden':'true'}),node('div',{},
            node('strong',{},`Применён штраф${award.penalty_percent!==null&&award.penalty_percent!==undefined?' −'+award.penalty_percent+'%':''}: −${penalty} XP`),
            node('span',{},`Сдача ${new Date(record.created_at).toLocaleString('ru-RU')} · опоздание ${award.late_days || 1} дн.`)));
        if (award.actions?.penalty?.available) note.append(button(award.actions.penalty.teacher?'Снять штраф':`Снять штраф · ${award.actions.penalty.cost} GC`,()=>confirm('penalty'),award.actions.penalty.teacher?'outline-secondary':'coin'));
        panel.append(note);
    } else if (award.penalty_removed) panel.append(node('p',{class:'gc-help-text mb-0'},node('i',{class:'fas fa-circle-check me-1','aria-hidden':'true'}),award.penalty_removed==='teacher'?'Штраф снят преподавателем · GC ученика не списывались.':'Штраф снят за GC. Баллы за работу не менялись.'));
    if (student) {
        const foot = node('div',{class:'gc-award__foot'});
        if (award.reward>0) foot.append(node('span',{class:award.reward_eligible?'gc-award__reward':'gc-help-text'},award.reward_eligible?node('i',{class:'fas fa-coins','aria-hidden':'true'}):null,award.booster_used?'Бустер применён · награда GC за эту сдачу не начисляется':award.reward_eligible?`Награда за полное решение: +${award.reward} GC`:`Награда ${award.reward} GC — за полное решение без бустера`));
        const boost = award.actions?.booster;
        if (boost?.available) {
            const booster = button(`Бустер · ${boost.cost} GC`, () => confirm('booster'), 'coin');
            booster.classList.add('gc-award__action');
            foot.append(booster);
        }
        if ((boost?.available || award.actions?.penalty?.available) && award.balance !== null && award.balance !== undefined) {
            foot.append(node('span', {class: 'gc-help-text'}, `Баланс: ${award.balance} GC`));
        }
        if (foot.children.length) panel.append(foot);
    }
    return panel.children.length?panel:null;
}
