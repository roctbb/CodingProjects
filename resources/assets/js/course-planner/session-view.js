import { esc, date } from "./format.js";

export function createSessionView({ getState }) {
  function renderEntry(entry, session) {
    const { data, layer } = getState();
    const resource = entry.resource;
    const title = entry.kind === "text" ? entry.text : resource.title;
    const provider = resource
      ? resource.provider_key === "codingprojects"
        ? "CodingProjects"
        : data.providers.find((p) => p.key === resource.provider_key)?.name ||
          resource.provider_key
      : "";
    const icon = !resource
      ? "fa-align-left"
      : resource.provider_key === "codingprojects"
        ? "fa-layer-group"
        : "fa-arrow-up-right-from-square";
    const meta = resource
      ? [
          "<span>" + esc(resource.type_label || resource.type) + "</span>",
          ...(resource.provider_key !== "codingprojects" ? ["<span>" + esc(provider) + "</span>"] : []),
        ]
      : [];
    if (entry.deadline?.due_on)
      meta.push(
        '<span class="planner-deadline"><i class="far fa-clock" aria-hidden="true"></i> до ' +
          date(entry.deadline.due_on) +
          "</span>",
      );
    if (resource?.child_count)
      meta.push("<span>Ступеней: " + resource.child_count + "</span>");
    if (entry.class_bonus_gc) meta.push('<span class="planner-deadline"><i class="fas fa-coins" aria-hidden="true"></i> +' + entry.class_bonus_gc + ' GC за задачу на занятии</span>');
    if (resource && resource.provider_key !== "codingprojects") {
      const names = entry.lesson_links
        .map((r) => data.lessons.find((l) => l.id === r.lesson_id)?.name)
        .filter(Boolean);
      meta.push(
        "<span>" + esc(names.join(", ") || "Урок не указан") + "</span>",
      );
    }
    if (layer === "fact")
      meta.push(
        '<select class="form-select planner-state-select" data-entry-state="' +
          entry.kind +
          ":" +
          entry.id +
          '" aria-label="Статус: ' +
          esc(title) +
          '">' +
          Object.entries({
            unconfirmed: "Не подтверждено",
            done: "Пройдено",
            partial: "Частично",
            not_done: "Не проводили",
          })
            .map(
              ([key, label]) =>
                '<option value="' +
                key +
                '" ' +
                (entry.fact_state === key ? "selected" : "") +
                ">" +
                label +
                "</option>",
            )
            .join("") +
          "</select>",
      );
    const actions = [
      ["move-entry", "Переместить в…"],
      ["entry-up", "Выше"],
      ["entry-down", "Ниже"],
    ];
    if (resource) {
      actions.push(["relations", "Связать с уроком"]);
      if (entry.layer === "plan") {
        if (resource.provider_key === 'codingprojects' && ['task','step','lesson'].includes(resource.type)) actions.push(['class-bonus', 'Бонус за решение на занятии']);
        actions.push(["refresh-entry", "Обновить из источника"]);
        if (
          resource.type === "task" ||
          resource.capabilities?.course_deadline === "planner"
        )
          actions.push(["deadline", "Дедлайн"]);
      }
      if (resource.child_count) actions.push(["split", "Разделить урок"]);
    } else actions.push(["edit-text", "Изменить текст"]);
    actions.push(["delete-entry", "Убрать с занятия"]);
    return (
      '<div class="planner-entry" draggable="true" data-entry="' +
      entry.kind +
      ":" +
      entry.id +
      '" data-session="' +
      session.id +
      '">' +
      '<span class="planner-handle" aria-hidden="true">⠿</span><span class="planner-entry-icon"><i class="fas ' +
      icon +
      '" aria-hidden="true"></i></span>' +
      '<div class="planner-entry-body"><div class="planner-entry-title">' +
      (resource?.open_url
        ? '<a href="' +
          esc(resource.open_url) +
          '" target="_blank" rel="noopener noreferrer">' +
          esc(title) +
          "</a>"
        : esc(title)) +
      '</div><div class="planner-entry-meta">' +
      meta.join("") +
      "</div></div>" +
      '<details class="planner-entry-menu planner-material-menu"><summary aria-label="Действия: ' +
      esc(title) +
      '">⋮</summary><div class="planner-menu">' +
      actions
        .map(
          ([action, label]) =>
            '<button type="button" data-action="' +
            action +
            '" data-entry="' +
            entry.kind +
            ":" +
            entry.id +
            '">' +
            label +
            "</button>",
        )
        .join("") +
      "</div></details></div>"
    );
  }
  function sessionTitle(
    session,
    entries = session.entries,
  ) {
    const { data, layer } = getState();
    if (!["Занятие", "Занятие без даты"].includes(session.title))
      return session.title;
    const lessonIds = new Set(
      entries.flatMap((e) =>
        (e.lesson_links || [])
          .filter((r) => r.relation === "study")
          .map((r) => r.lesson_id),
      ),
    );
    const names = data.lessons
      .filter((l) => lessonIds.has(l.id))
      .map((l) => l.name);
    return (
      names.join(" / ") ||
      (entries.length
        ? "Занятие"
        : layer === "fact"
          ? "Факт не заполнен"
          : "Занятие")
    );
  }
  function sessionMarkup(session, slot = null) {
    const { data, selected } = getState();
    const held = slot?.local_date || session.record?.held_on;
    const entries = session.entries;
    const duration = slot?.duration_minutes || data.settings.default_duration_minutes || 90;
    const title = sessionTitle(session, entries);
    const action = (name, label, classes = "") => `<button type="button" class="${classes}" data-action="${name}" data-session="${session.id}">${label}</button>`;
    let menu = action("edit-session", "Настройки занятия") + action("add-text", "Текстовая запись")
      + (session.can_comment ? action("record", "Комментарий к занятию") : "")
      + (slot ? `<button type="button" data-action="exclude" data-slot="${slot.id}">Исключить дату</button>` : "");
    const hasBonusTasks = entries.some(entry => entry.kind === 'resource' && entry.layer === 'plan'
      && entry.resource.provider_key === 'codingprojects' && ['task', 'step', 'lesson'].includes(entry.resource.type));
    if (hasBonusTasks) menu += action('session-bonus', 'Бонус всем задачам занятия');
    const lessonIds = new Set(entries.flatMap(e => (e.lesson_links || []).filter(r => r.relation === "study").map(r => r.lesson_id)));
    const labels = data.summary.rows.filter(r => lessonIds.has(r.lesson_id)).map(r => `<button type="button" class="planner-inline-button" data-action="highlight-lesson" data-lesson="${r.lesson_id}">${esc(r.name)} · ${r.session_count} зан.</button>`).join("");
    if (labels) menu += `<div class="planner-menu-label">Уроки программы</div>${labels}`;
    const comment = session.comment ?? session.record?.note;
    return `<section class="planner-session planner-drop ${entries.length ? "" : "is-empty"} ${selected === session.id ? "is-selected" : ""}" data-session="${session.id}" ${slot ? `data-slot="${slot.id}"` : ""} aria-label="Занятие ${esc(held || "без даты")}">
      <header class="planner-session-head"><div class="min-width-0"><div class="planner-session-date"><span>${slot ? (slot.start_time ? `${esc(slot.start_time)} · ` : "") : `${esc(date(held))} · `}${duration} мин</span><span class="planner-handle" draggable="true" data-drag-session="${session.id}" aria-label="Перенести занятие">⠿</span></div><h3 class="planner-session-title">${esc(title)}</h3></div><details class="planner-entry-menu planner-session-menu"><summary aria-label="Действия занятия ${esc(date(held))}">⋮</summary><div class="planner-menu">${menu}</div></details></header>
      <div class="planner-entries">${entries.map(e => renderEntry(e, session)).join("")}</div>
      ${!entries.length ? `<p class="planner-empty">${comment ? 'Материалы к занятию не указаны.' : 'Добавьте материалы или текстовую запись.'}</p>` : ""}
      <div class="planner-drop-hint">Добавить материал в это занятие</div>
      ${session.plan_note ? `<p class="planner-session-note">${esc(session.plan_note)}</p>` : ""}
      ${comment ? `<div class="planner-session-comment"><span><i class="fas fa-comment" aria-hidden="true"></i> Как прошло занятие</span><p>${esc(comment)}</p></div>` : ""}
      <footer class="planner-session-footer">${action("session-materials", entries.length ? "+ Материал" : "Добавить материалы", "planner-inline-button")}${hasBonusTasks ? action('session-bonus', 'Бонус всем задачам', 'btn btn-coin btn-sm') : ''}${session.can_comment ? action("record", comment ? "Изменить комментарий" : "Как прошло занятие", "planner-quiet-button planner-comment-action") : ""}</footer></section>`;
  }
  return { sessionTitle, sessionMarkup };
}
