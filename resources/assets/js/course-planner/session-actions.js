import { esc, date, shift } from "./format.js";

export function createSessionActions({ root, getState, setTarget, setRange, loadState, ensureSession, mutate, dialog, renderCalendar, renderTarget, revealSession }) {
  const $ = selector => root.querySelector(selector);
  const { field, select, modal, values } = dialog;
  const slotById = id => getState().data.slots.find(s => s.id === Number(id));
  function recordModal(session) {
    if (!session.can_comment) throw new Error("Комментарий доступен после окончания занятия.");
    modal("Как прошло занятие · " + date(slotById(session.calendar_slot_id)?.local_date || session.record?.held_on),
      '<div class="planner-field"><label for="planner-session-comment">Комментарий к занятию</label><textarea id="planner-session-comment" class="form-control" name="comment" rows="5" maxlength="10000" placeholder="Что успели, что изменилось, что нужно продолжить">' + esc(session.comment ?? session.record?.note ?? "") + '</textarea></div>',
      async () => { await mutate("comment", {session_id: session.id, comment: values().comment}, "Комментарий сохранён."); });
  }
  const names = new Set(["add-session", "add-text", "record", "edit-session", "add-slot", "exclude", "remove-exception"]);
  async function handle(button) {
    const { data } = getState();
    const name = button.dataset.action;
    const session = data.sessions.find(s => s.id === Number(button.dataset.session));
    switch (name) {
      case "add-session": {
        const result = await mutate("add_session", {
          title: "Занятие без даты",
        });
        setTarget(result.session_id, getState().selectedSlot);
        $("#planner-backlog-panel").open = true;
        renderCalendar();
        break;
      }
      case "add-text": {
        if (session) {
          setTarget(session.id);
          renderTarget();
        }
        const sessionId = await ensureSession();
        modal(
          "Произвольная запись",
          '<div class="planner-field"><label for="planner-note-text">Что запланировать</label><textarea id="planner-note-text" name="text" class="form-control" rows="4" required maxlength="10000"></textarea></div>',
          async () => {
            await mutate(
              "add_text",
              { session_id: sessionId, layer: getState().layer, text: values().text },
              "Запись добавлена.",
            );
          },
        );
        break;
      }
      case "record":
        recordModal(session);
        break;
      case "edit-session":
        modal(
          "Занятие",
          field(
            "title",
            "Название",
            "text",
            session.title,
            'required maxlength="255"',
          ) +
            '<div class="planner-field"><label for="planner-plan-note">Комментарий к плану</label><textarea class="form-control" id="planner-plan-note" name="plan_note" rows="3">' +
            esc(session.plan_note || "") +
            '</textarea></div><label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="pinned" value="1" ' +
            (session.pinned ? "checked" : "") +
            '><span class="form-check-label small">Закрепить дату при перестройке расписания</span></label>' +
            select(
              "slot_id",
              "Перенести занятие",
              [
                ["", "Без даты"],
                ...data.slots
                  .filter((s) => s.state === "available")
                  .map((s) => [
                    s.id,
                    date(s.local_date) +
                      (s.start_time ? " " + s.start_time : ""),
                  ]),
              ],
              session.calendar_slot_id || "",
            ),
          async () => {
            const v = values();
            await mutate("update_session", {
              session_id: session.id,
              title: v.title,
              plan_note: v.plan_note,
              pinned: v.pinned === "1",
            });
            if (String(v.slot_id) !== String(session.calendar_slot_id || ""))
              await mutate("move_session", {
                session_id: session.id,
                slot_id: v.slot_id ? Number(v.slot_id) : null,
              });
          },
        );
        break;
      case "add-slot":
        modal(
          "Дополнительное занятие",
          field("date", "Дата", "date", root.dataset.today, "required") +
            field("start_time", "Начало", "time", "") +
            field(
              "duration_minutes",
              "Длительность, минут",
              "number",
              data.settings.default_duration_minutes || 90,
              'required min="5" max="480"',
            ) +
            field("title", "Название", "text", "Дополнительное занятие"),
          async () => {
            const v = values();
            const result = await mutate("add_slot", {
              ...v,
              start_time: v.start_time || null,
              duration_minutes: Number(v.duration_minutes),
            });
            setTarget(result.session_id);
            const { from, to } = getState();
            if (v.date < from || v.date > to) {
              setRange(v.date, shift(v.date, 6));
              await loadState();
            }
            renderCalendar();
            revealSession(getState().selected);
          },
        );
        break;
      case "exclude": {
        const slot = slotById(button.dataset.slot);
        modal(
          "Исключить дату",
          field("starts_on", "С", "date", slot.local_date, "required") +
            field("ends_on", "По", "date", slot.local_date, "required") +
            field("reason", "Причина", "text", "", 'required maxlength="500"'),
          async () => {
            await mutate(
              "add_exception",
              values(),
              "Дата исключена. Содержимое доступно в разделе «Без даты».",
            );
          },
        );
        break;
      }
      case "remove-exception":
        await mutate(
          "remove_exception",
          { exception_id: Number(button.dataset.exception) },
          "Дата восстановлена. Материалы без даты можно перенести обратно.",
        );
        break;
    }
  }
  return { canHandle: name => names.has(name), handle };
}
