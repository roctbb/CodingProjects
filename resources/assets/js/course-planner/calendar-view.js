import { esc, date, shift } from "./format.js";
import { renderDeadlineStrip } from "./deadline-strip.js";

export function createCalendarView({ root, getState, sessionMarkup, onRendered }) {
  const $ = selector => root.querySelector(selector);
  return function renderCalendar() {
    const { data, from, to } = getState();
    const openDates = new Set([...root.querySelectorAll('[data-deadline-date][open]')].map(group => group.dataset.deadlineDate));
    const deadlineIndexes = new Map(data.deadlines.map((deadline, index) => [deadline.key, index]));
    const sessions = new Map(data.sessions.filter(s => s.calendar_slot_id).map(s => [s.calendar_slot_id, s]));
    const weeks = new Map();
    const weekOf = day => shift(day, -((new Date(day + "T12:00:00Z").getUTCDay() + 6) % 7));
    const addDay = day => { const week = weekOf(day); if (!weeks.has(week)) weeks.set(week, new Set()); weeks.get(week).add(day); };
    data.slots.filter(slot => slot.state !== "retired" || sessions.has(slot.id)).forEach(slot => addDay(slot.local_date));
    data.sessions.filter(s => !s.calendar_slot_id && s.record?.held_on >= from && s.record.held_on <= to).forEach(s => addDay(s.record.held_on));
    data.deadlines.filter(d => d.due_on >= from && d.due_on <= to).forEach(d => { const week = weekOf(d.due_on); if (!weeks.has(week)) weeks.set(week, new Set()); });
    let visibleCount = 0;
    const html = [...weeks].sort(([a],[b]) => a.localeCompare(b)).map(([week, days]) => {
      const columns = [...days].sort().map(day => {
        let content = "";
        for (const slot of data.slots.filter(s => s.local_date === day && (s.state !== "retired" || sessions.has(s.id)))) {
          const session = sessions.get(slot.id);
          if (slot.state !== "available") {
            const exception = data.exceptions.find(e => e.starts_on <= day && e.ends_on >= day && (!e.rule_key || e.rule_key === slot.rule_key));
            content += `<div class="planner-excluded">${esc(slot.reason || "Занятие исключено")}${exception ? `<button type="button" class="planner-inline-button" data-action="remove-exception" data-exception="${exception.id}">Восстановить</button>` : ""}</div>`;
          }
          if (session) { content += sessionMarkup(session, slot); visibleCount++; }
          else if (slot.state === "available") content += `<section class="planner-session ${slot.editable === false ? '' : 'planner-drop'} is-empty" data-slot="${slot.id}" aria-label="Занятие ${esc(day)}"><div class="planner-session-date">${esc(slot.start_time || "")} · ${slot.duration_minutes} мин</div><h3 class="planner-session-title">Занятие</h3><p class="planner-empty">Материалов пока нет</p>${slot.editable === false ? '' : `<footer class="planner-session-footer"><button type="button" class="planner-inline-button" data-action="select-slot" data-slot="${slot.id}">Добавить материалы</button></footer>`}</section>`;
        }
        for (const session of data.sessions.filter(s => !s.calendar_slot_id && s.record?.held_on === day)) { content += sessionMarkup(session); visibleCount++; }
        const dayDate = new Date(day + "T12:00:00");
        const weekday = dayDate.toLocaleDateString("ru-RU", {weekday:"long"});
        const month = new Intl.DateTimeFormat("ru-RU", {day:"numeric",month:"long"}).formatToParts(dayDate).find(part => part.type === "month").value;
        return `<section class="planner-board-day ${day === data.today ? 'is-today' : ''}"><h3 class="planner-board-day-title"><time datetime="${day}" ${day === data.today ? 'aria-current="date"' : ''}><strong>${dayDate.getDate()}</strong><span>${esc(weekday)}<small class="${day === data.today ? 'planner-today-label' : ''}">${day === data.today ? 'Сегодня' : esc(month)}</small></span></time></h3>${content}</section>`;
      }).join("");
      const deadlines = data.deadlines.filter(d => d.due_on >= week && d.due_on <= shift(week,6) && d.due_on >= from && d.due_on <= to);
      const due = renderDeadlineStrip(deadlines, {
        escape: esc, formatDate: date, openDates,
        lessonName: id => data.summary.rows.find(row => row.lesson_id === id)?.name || '',
        indexFor: deadline => deadlineIndexes.get(deadline.key),
      });
      return `<section class="planner-board-week">${weeks.size>1?`<h3 class="planner-week">${esc(date(week))} — ${esc(date(shift(week,6)))}</h3>`:""}<div class="gc-board-grid">${columns}</div>${due}</section>`;
    }).join("");
    $("#planner-calendar").innerHTML = html || '<div class="gc-card p-4"><h3>Занятий на эту неделю нет</h3><p class="gc-help-text mt-2 mb-3">Выберите другую неделю или настройте расписание курса.</p><button class="btn btn-outline-secondary btn-sm" type="button" data-action="settings">Настроить расписание</button></div>';
    const backlog = data.sessions.filter(s => !s.calendar_slot_id && !s.record);
    $("#planner-backlog").innerHTML = backlog.length ? backlog.map(s => sessionMarkup(s)).join("") : '<p class="gc-help-text">Здесь появятся занятия без выбранной даты.</p>';
    $("#planner-backlog-count").textContent = backlog.length;
    $("#planner-backlog-panel").hidden = backlog.length === 0;
    $("#planner-range-caption").textContent = `Занятий в периоде: ${visibleCount}`;
    $("#planner-layer-caption").textContent = "";
    onRendered();
  };
}
