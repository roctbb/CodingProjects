import { esc, date, shift } from "./format.js";

export function createWorkspaceNavigation({ root, getState, setRange, setTarget, loadState, request, dialog, catalog, sessionTitle, renderCalendar, revealSession, status }) {
  const $ = selector => root.querySelector(selector);
  const { modal } = dialog;
  let activeView = "calendar";
  const slotById = id => getState().data.slots.find(slot => slot.id === Number(id));
  function setView(view) {
    activeView = view;
    root.dataset.view = view;
    $("#planner-view").value = view;
    $("#planner-calendar-panel").hidden = view !== "calendar";
    $("#planner-overview-panel").hidden = view !== "overview";
    root
      .querySelectorAll("[data-calendar-only]")
      .forEach((n) => (n.hidden = view !== "calendar"));
    if (view !== "calendar" && !$("#planner-status").classList.contains("text-danger")) $("#planner-status").textContent = "";
    catalog.close();
  }
  function highlightLesson(id) {
    root
      .querySelectorAll(".planner-highlight")
      .forEach((n) => n.classList.remove("planner-highlight"));
    root
      .querySelectorAll(".planner-summary-row")
      .forEach((n) =>
        n.classList.toggle("is-highlighted", Number(n.dataset.lesson) === id),
      );
    const group = getState().data.summary.rows.find((r) => (r.lesson_id || 0) === id);
    for (const s of group?.study_sessions || [])
      root
        .querySelector('.planner-session[data-session="' + s.session_id + '"]')
        ?.classList.add("planner-highlight");
    status("Выбран урок: " + (group?.name || ""));
  }
  async function summaryDetails(button) {
    const lessonId = Number(button.dataset.lesson);
    const provider = button.dataset.provider;
    const page = Number(button.dataset.page || 1);
    const row = getState().data.summary.rows.find((r) => (r.lesson_id || 0) === lessonId);
    const result = await request(
      "/summary/details?" +
        new URLSearchParams({ layer: "workspace", lesson_id: lessonId, provider, page }),
    );
    const pageButton = (n, title) =>
      '<button type="button" class="btn btn-sm btn-outline-secondary" data-action="summary-detail" data-lesson="' +
      lessonId +
      '" data-provider="' +
      esc(provider) +
      '" data-page="' +
      n +
      '">' +
      title +
      "</button>";
    modal(
      row.name,
      '<p class="small mb-3">Уникальных материалов: ' +
        (row.providers[provider]?.materials || 0) +
        " · использований: " +
        (row.providers[provider]?.uses || 0) +
        "</p>" +
        (result.items.length
          ? result.items
              .map(
                (item) =>
                  '<div class="mb-3"><strong>' +
                  esc(item.title) +
                  '</strong><br><button type="button" class="planner-inline-button small" data-action="jump-session" data-session="' +
                  item.session_id +
                  '" data-date="' +
                  esc(item.date || "") +
                  '">' +
                  esc(date(item.date) + " · " + item.session_title) +
                  "</button></div>",
              )
              .join("")
          : "<p>Материалов пока нет.</p>") +
        '<div class="d-flex gap-2 mt-3">' +
        (page > 1 ? pageButton(page - 1, "Назад") : "") +
        (result.has_more ? pageButton(page + 1, "Далее") : "") +
        "</div>",
      null,
    );
    highlightLesson(lessonId);
  }
  const names = new Set(["apply-range", "select-session", "select-slot", "today", "previous-range", "next-range", "highlight-lesson", "summary-detail", "jump-session"]);
  async function handle(button) {
    const { data, layer, from, to } = getState();
    const name = button.dataset.action;
    const session = data.sessions.find(s => s.id === Number(button.dataset.session));
    switch (name) {
      case "apply-range": {
        const previous = { from, to };
        const start = $("#planner-from").value;
        const end = $("#planner-to").value;
        try {
          if (!start || !end) throw new Error("Выберите начало и конец периода.");
          setRange(start, end);
          await loadState();
        } catch (error) {
          setRange(previous.from, previous.to);
          $("#planner-range-picker").open = true;
          throw error;
        }
        break;
      }
      case "select-session":
        setTarget(session.id);
        renderCalendar();
        root
          .querySelector(
            '[data-action="select-session"][data-session="' + session.id + '"]',
          )
          ?.focus({ preventScroll: true });
        status(
          "Выбрано занятие: " +
            date(
              layer === "fact"
                ? session.record?.held_on ||
                    slotById(session.calendar_slot_id)?.local_date
                : slotById(session.calendar_slot_id)?.local_date,
            ) +
            " · " +
            sessionTitle(session),
        );
        break;
      case "select-slot":
        setTarget(null, Number(button.dataset.slot));
        renderCalendar();
        catalog.open(button);
        break;
      case "today": {
        const start = shift(
          root.dataset.today,
          -((new Date(root.dataset.today + "T12:00:00Z").getUTCDay() + 6) % 7),
        );
        setRange(start, shift(start, 6));
        await loadState();
        break;
      }
      case "previous-range":
      case "next-range": {
        const next = shift(from, name === "next-range" ? 7 : -7);
        setRange(next, shift(next, 6));
        await loadState();
        break;
      }
      case "highlight-lesson":
        if (activeView === "overview") {
          const group = data.summary.rows.find(
            (r) => r.lesson_id === Number(button.dataset.lesson),
          );
          const first = group?.study_sessions.find((s) => s.date);
          if (first && (first.date < from || first.date > to)) {
            setRange(first.date, shift(first.date, 6));
            await loadState();
          }
          setView("calendar");
          if (first) revealSession(first.session_id);
        }
        highlightLesson(Number(button.dataset.lesson));
        break;
      case "summary-detail": {
        await summaryDetails(button);
        break;
      }
      case "jump-session": {
        $("#planner-dialog").close();
        setView("calendar");
        const actualDate = button.dataset.date;
        if (actualDate && (actualDate < from || actualDate > to)) {
          setRange(actualDate, shift(actualDate, 6));
          await loadState();
        }
        const selected = Number(button.dataset.session);
        setTarget(selected, getState().selectedSlot);
        renderCalendar();
        revealSession(selected);
        break;
      }
    }
  }
  return { canHandle: name => names.has(name), handle, setView };
}
