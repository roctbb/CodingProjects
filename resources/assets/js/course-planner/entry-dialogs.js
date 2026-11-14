import { esc, date } from "./format.js";

export function createEntryDialogs({ root, getState, request, mutate, dialog, sessionTitle }) {
  const $ = selector => root.querySelector(selector);
  const { field, select, modal, values } = dialog;
  const slotById = id => getState().data.slots.find(s => s.id === Number(id));
  function targetOptions() {
    const { data, layer } = getState();
    return data.sessions.map((s) => [
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
