import { withCourseScope } from '../components/course-context';
import { esc, date } from "./format.js";
import { stageRow, initDeadlineEditor, deadlinePayload } from '../components/deadline-editor.js';

export function createEntryDialogs({ root, getState, request, mutate, dialog, sessionTitle }) {
  const $ = selector => root.querySelector(selector);
  const { field, select, modal, values } = dialog;
  const slotById = id => getState().data.slots.find(s => s.id === Number(id));
  function targetOptions() {
    const { data, layer } = getState();
    return data.sessions.filter(s => s.editable !== false).map((s) => [
      s.id,
      date(
        layer === "fact"
          ? s.record?.held_on || slotById(s.calendar_slot_id)?.local_date
          : slotById(s.calendar_slot_id)?.local_date,
      ) +
        " · " +
        sessionTitle(s),
    ]);
  }
  function deadlineModal(entry = null, existing = null) {
    const { data } = getState();
    const native = entry?.resource?.provider_key === "codingprojects";
    const deadline = existing || entry?.deadline;
    if (native) { nativeDeadlineModal(entry, deadline).catch(error => modal('Не удалось открыть сроки', '<p>' + esc(error.message) + '</p>', null)); return; }
    modal(
      "Дедлайн работы",
      (!entry
        ? field(
            "title",
            "Название работы",
            "text",
            deadline?.title || "",
            "required",
          )
        : "<p>" + esc(entry.resource.title) + "</p>") +
        field("due_on", "Сдать до конца дня", "date", deadline?.due_on || "") +
        (native
          ? field(
              "penalty",
              "Штраф, %",
              "number",
              (deadline?.penalty ?? 0) * 100,
              'min="0" max="100" step="1"',
            )
          : select(
              "lesson_id",
              "Урок программы",
              [
                ["", "Без привязки"],
                ...data.lessons.map((l) => [l.id, l.name]),
              ],
              deadline?.lesson_id || entry?.lesson_links?.[0]?.lesson_id || "",
            )) +
        '<p class="small text-secondary mt-3">Пустая дата снимает срок. Перенос занятия не меняет эту дату.</p>',
      async () => {
        const v = values();
        await mutate(
          "set_deadline",
          {
            resource_ref_id:
              entry?.resource_ref_id || deadline?.resource_ref_id || null,
            deadline_id: deadline?.id || null,
            task_id:
              native && !entry?.resource_ref_id
                ? Number(entry.resource.external_id)
                : null,
            title: v.title || entry?.resource?.title || deadline?.title,
            due_on: v.due_on || null,
            penalty: native ? Number(v.penalty) / 100 : null,
            lesson_id: v.lesson_id ? Number(v.lesson_id) : null,
          },
          "Дедлайн сохранён.",
        );
      },
    );
  }
  async function nativeDeadlineModal(entry, deadline) {
    const taskId = Number(entry.resource.external_id);
    const response = await fetch(withCourseScope(root.dataset.deadlineApi + '/' + taskId), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const policy = await response.json();
    if (!response.ok) throw new Error(policy.message || 'Не удалось загрузить сроки.');
    modal('Сроки работы', '<p>' + esc(entry.resource.title) + '</p><div data-deadline-editor>' +
      '<input type="hidden" name="revision" value="' + Number(policy.revision) + '"><input type="hidden" name="timezone" value="' + esc(policy.timezone) + '">' +
      select('mode', 'Правила сдачи', [['inherit', 'Сроки урока'], ['custom', 'Свои сроки'], ['none', 'Без дедлайна']], policy.mode).replace('name="mode"', 'name="mode" data-deadline-mode') +
      '<div data-deadline-stages><p class="gc-help-text">До первого срока — 100%. Время: ' + esc(policy.timezone) + '.</p><div class="d-grid gap-3" data-deadline-rows>' +
      policy.stages.map((stage, index) => stageRow(index, stage)).join('') + '</div><button class="btn btn-outline-secondary btn-sm mt-3" type="button" data-deadline-add>+ Ещё срок</button></div></div>' +
      '<p class="gc-help-text mt-3">Последний процент действует и дальше. Сдача остаётся открытой, минимум за отправленное решение — 1 балл. Перенос занятия не меняет сроки.</p>', async () => {
        await mutate('set_deadline', { task_id: taskId, ...deadlinePayload($('#planner-dialog-form')) }, 'Сроки сохранены.');
      });
    initDeadlineEditor(root.querySelector('[data-deadline-editor]'));
  }
  function relationsModal(entry) {
    const { data } = getState();
    const chosen = new Set(entry.lesson_links.map((l) => l.lesson_id));
    modal(
      "Связь материала с уроками",
      '<p class="small mb-3">' +
        esc(entry.resource.title) +
        '</p><div class="planner-checklist">' +
        data.lessons
          .map(
            (l) =>
              '<label><input type="checkbox" name="lesson_ids" value="' +
              l.id +
              '" ' +
              (chosen.has(l.id) ? "checked" : "") +
              ">" +
              esc(l.name) +
              "</label>",
          )
          .join("") +
        "</div>" +
        select(
          "relation",
          "Как связан материал",
          [
            ["related", "Относится к уроку · учитывается в сводке"],
            ["study", "Изучаем урок · учитывается в скобке"],
          ],
          entry.lesson_links[0]?.relation || "related",
        ),
      async () => {
        await mutate(
          "relations",
          {
            link_id: entry.id,
            lesson_ids: [
              ...new FormData($("#planner-dialog-form")).getAll("lesson_ids"),
            ].map(Number),
            relation: values().relation,
          },
          "Связи сохранены.",
        );
      },
    );
  }
  async function splitModal(entry, session) {
    const response = await request("/entries/" + entry.id);
    const children = response.resource.children_refs || [];
    const { data } = getState();
    modal(
      "Разделить урок по занятиям",
      '<p class="small mb-3">Выберите ступени для переноса. Остальные останутся на исходном занятии.</p><div class="planner-checklist">' +
        children
          .map(
            (c) =>
              '<label><input type="checkbox" name="selected_ids" value="' +
              esc(c.external_id) +
              '">' +
              esc(c.title) +
              "</label>",
          )
          .join("") +
        "</div>" +
        select(
          "target_session_id",
          "Куда перенести",
          targetOptions(),
          data.sessions.find((s) => s.id !== session.id)?.id || session.id,
        ),
      async () => {
        const v = values();
        await mutate(
          "split_lesson",
          {
            link_id: entry.id,
            selected_ids: new FormData($("#planner-dialog-form")).getAll(
              "selected_ids",
            ),
            target_session_id: Number(v.target_session_id),
          },
          "Урок разделён на ступени.",
        );
      },
    );
  }
  return { deadlineModal, relationsModal, splitModal, targetOptions };
}
