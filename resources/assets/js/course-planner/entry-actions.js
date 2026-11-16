import { esc, date } from "./format.js";

export function createEntryActions({ getState, mutate, dialog, entryDialogs }) {
  const { modal, select, field, values } = dialog;
  const { deadlineModal, relationsModal, splitModal, targetOptions } = entryDialogs;
  const entryById = (kind, id) => {
    for (const session of getState().data.sessions) {
      const entry = session.entries.find(
        (e) => e.kind === kind && e.id === Number(id),
      );
      if (entry) return { entry, session };
    }
    return null;
  };
  const names = new Set(["class-bonus", "edit-text", "delete-entry", "move-entry", "entry-up", "entry-down", "relations", "refresh-entry", "split", "deadline", "add-deadline", "lesson-deadlines", "calendar-deadline"]);
  async function handle(button) {
    const { data, layer } = getState();
    const name = button.dataset.action;
    const [kind, id] = (button.dataset.entry || "").split(":");
    const context = id ? entryById(kind, id) : null;
    switch (name) {
      case 'class-bonus':
        modal('Бонус за решение на занятии', '<p>' + esc(context.entry.resource.title) + '</p>' + field('class_bonus_gc', 'GC за каждую полностью решённую задачу', 'number', context.entry.class_bonus_gc || 0, 'min="0" max="10000" step="1" required') +
          '<p class="gc-help-text mt-3">0 — без бонуса. Учитывается время сдачи от начала до конца занятия по расписанию. У занятия должно быть задано время. За одну задачу бонус начисляется один раз в курсе, даже если проверка завершилась позже. Для урока и ступени правило охватывает их задачи.</p>', async () => {
            await mutate('update_entry', { kind, entry_id: Number(id), class_bonus_gc: Number(values().class_bonus_gc) }, 'Бонус сохранён.');
          }, 'Сохранить', 'coin');
        break;
      case "edit-text":
        modal(
          "Изменить запись",
          '<div class="planner-field"><label for="planner-note-text">Текст</label><textarea id="planner-note-text" class="form-control" name="text" rows="4" required maxlength="10000">' +
            esc(context.entry.text) +
            "</textarea></div>",
          async () => {
            await mutate("update_entry", {
              kind,
              entry_id: Number(id),
              text: values().text,
            });
          },
        );
        break;
      case "delete-entry":
        await mutate(
          "delete_entry",
          { kind, entry_id: Number(id) },
          "Привязка убрана. Исходный материал и срок сохранены.",
        );
        break;
      case "move-entry":
        modal(
          "Переместить материал",
          select(
            "target_session_id",
            "Занятие",
            targetOptions(),
            context.session.id,
          ),
          async () => {
            await mutate("move_entry", {
              kind,
              entry_id: Number(id),
              target_session_id: Number(values().target_session_id),
            });
          },
        );
        break;
      case "entry-up":
      case "entry-down": {
        const siblings = context.session.entries;
        const i = siblings.findIndex(
          (e) => e.kind === kind && e.id === Number(id),
        );
        if (
          (name === "entry-up" && i === 0) ||
          (name === "entry-down" && i === siblings.length - 1)
        )
          break;
        const before = name === "entry-up" ? siblings[i - 1] : siblings[i + 2];
        await mutate("move_entry", {
          kind,
          entry_id: Number(id),
          target_session_id: context.session.id,
          before: before ? { kind: before.kind, id: before.id } : null,
        });
        break;
      }
      case "relations":
        relationsModal(context.entry);
        break;
      case "refresh-entry":
        await mutate(
          "refresh_entry",
          { entry_id: Number(id) },
          "Снимок материала в плане обновлён.",
        );
        break;
      case "split":
        await splitModal(context.entry, context.session);
        break;
      case "deadline":
        deadlineModal(context.entry);
        break;
      case "add-deadline":
        deadlineModal();
        break;
      case "lesson-deadlines": {
        const lessonId = Number(button.dataset.lesson);
        const deadlines = data.deadlines.filter(
          (d) => (d.lesson_id || 0) === lessonId,
        );
        modal(
          "Дедлайны урока",
          deadlines.length
            ? deadlines
                .map(
                  (d) =>
                    '<p class="mb-2">' +
                    esc(date(d.due_on)) +
                    " · " +
                    esc(d.title) +
                    "</p>",
                )
                .join("")
            : "<p>По этому уроку сроки пока не установлены.</p>",
          null,
        );
        break;
      }
      case "calendar-deadline": {
        const d = data.deadlines[Number(button.dataset.deadline)];
        const related = data.sessions
          .flatMap((s) => s.entries)
          .find(
            (e) =>
              e.kind === "resource" &&
              (e.resource_ref_id === d.resource_ref_id ||
                (e.resource.provider_key === d.provider_key &&
                  e.resource.external_id === d.external_id)),
          );
        if (related && layer === "plan") deadlineModal(related, d);
        else if (d.provider_key === "codingprojects" && layer === "plan")
          deadlineModal(
            {
              resource: {
                provider_key: "codingprojects",
                external_id: d.external_id,
                title: d.title,
              },
            },
            d,
          );
        else if (d.key.startsWith("planner:") && layer === "plan")
          deadlineModal(null, d);
        else
          modal(
            "Дедлайн",
            "<p>" +
              esc(d.title) +
              " · " +
              date(d.due_on) +
              "</p>" +
              (d.open_url
                ? '<a href="' +
                  esc(d.open_url) +
                  '" target="_blank" rel="noopener">Открыть работу</a>'
                : ""),
            null,
          );
        break;
      }    }
  }
  return { canHandle: name => names.has(name), handle };
}
