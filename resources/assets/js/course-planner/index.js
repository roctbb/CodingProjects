import { createScheduleSettings } from "./schedule-settings";
import { createEntryDialogs } from "./entry-dialogs";
import { createSessionActions } from "./session-actions";
import { esc, date, shift } from './format';
import { createPlannerRequest } from './request';
import { createSessionView } from './session-view';
import { createCalendarView } from './calendar-view';
import { createSummaryView } from './summary-view';
import { createPlannerDialog } from './dialog';
import { initPlannerDragDrop } from './drag-drop';
import { createMaterialCatalog } from './material-catalog';
const root = document.getElementById("course-planner");
if (root && root.querySelector("#planner-calendar")) {
  const api = root.dataset.api;
  const $ = (s) => root.querySelector(s);
  let data = null,
    layer = "plan",
    selected = null,
    selectedSlot = null,
    busy = false;
  let activeView = "calendar", from = "", to = "", stateRequestNumber = 0;
  const getState = () => ({ data, layer, selected, selectedSlot, from, to, busy });
  const setRange = (start, end) => { from = start; to = end; };
  const setTarget = (sessionId, slotId = null) => { selected = sessionId; selectedSlot = slotId; };
  const { sessionTitle, sessionMarkup } = createSessionView({ getState });
  const renderCalendar = createCalendarView({ root, getState, sessionMarkup, onRendered: renderTarget });
  const { renderSummary, renderChart } = createSummaryView({ root, getState });
  const dialog = createPlannerDialog({ root, onOpen: () => catalog.close() });
  const { select, modal, values } = dialog;
  const operationNames = {
    configure: "Изменено расписание",
    providers: "Изменены провайдеры курса",
    add_exception: "Исключена дата",
    remove_exception: "Восстановлена дата",
    add_slot: "Добавлено занятие",
    add_session: "Добавлено занятие без даты",
    move_session: "Перенесено занятие",
    update_session: "Изменено занятие",
    attach: "Добавлен материал",
    attach_many: "Добавлены материалы",
    comment: "Комментарий к занятию",
    refresh_entry: "Обновлён материал из источника",
    split_lesson: "Разделён урок",
    relations: "Изменена связь с уроком",
    add_text: "Добавлена запись",
    update_entry: "Изменён материал",
    delete_entry: "Убрана привязка",
    move_entry: "Перенесён материал",
    set_deadline: "Изменён дедлайн",
    record: "Зафиксировано занятие",
    undo: "Отменено изменение",
  };
  const status = (text, error = false) => {
    $("#planner-status").textContent = text;
    $("#planner-status").classList.toggle("text-danger", error);
  };
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
  function revealSession(id) {
    const node = root.querySelector(
      '.planner-session[data-session="' + id + '"]',
    );
    if (node?.closest("#planner-backlog-panel"))
      $("#planner-backlog-panel").open = true;
    requestAnimationFrame(() =>
      node?.scrollIntoView({ behavior: "smooth", block: "center" }),
    );
  }
  function updateTopOffset() {
    const height =
      document.querySelector(".gc-topbar")?.getBoundingClientRect().height || 0;
    root.style.setProperty("--planner-top-offset", height + "px");
  }
  const request = createPlannerRequest(api);
  const catalog = createMaterialCatalog({ root, getState, setTarget, request, ensureSession, mutate, sessionTitle, status, addResource });
  function renderTarget() { catalog.refreshTarget(); }
  const settingsModal = createScheduleSettings({ root, getState, setRange, request, mutate, dialog });
  const { deadlineModal, relationsModal, splitModal, targetOptions } = createEntryDialogs({ root, getState, request, mutate, dialog, sessionTitle });
  const sessionActions = createSessionActions({ root, getState, setTarget, setRange, loadState, ensureSession, mutate, dialog, renderCalendar, renderTarget, revealSession });

  async function loadState() {
    const number = ++stateRequestNumber;
    const query = new URLSearchParams({ layer: "workspace" });
    if (from) query.set("from", from);
    if (to) query.set("to", to);
    const result = await request("/state?" + query);
    if (number !== stateRequestNumber) return;
    data = result;
    root.dataset.today = data.today;
    from = data.range.from;
    to = data.range.to;
    $("#planner-from").value = from;
    $("#planner-to").value = to;
    if (!data.sessions.some((s) => s.id === selected))
      selected =
        data.sessions.find((s) =>
          data.slots.some(
            (slot) =>
              slot.id === s.calendar_slot_id &&
              slot.local_date >= root.dataset.today &&
              slot.state === "available",
          ),
        )?.id ??
        data.sessions.find((s) => s.calendar_slot_id)?.id ??
        data.sessions[0]?.id ??
        null;
    catalog.syncProviders(data.providers);
    render();
  }
  async function mutate(
    operation,
    payload = {},
    message = "Изменение сохранено.",
  ) {
    if (busy) throw new Error("Дождитесь сохранения предыдущего изменения.");
    busy = true;
    root.setAttribute("aria-busy", "true");
    $("#planner-save-state").textContent = "Сохранение…";
    status("Сохранение…");
    const id =
      globalThis.crypto?.randomUUID?.() ??
      "operation-" + Date.now() + "-" + Math.random().toString(16).slice(2);
    try {
      const result = await request("/commands/" + operation, {
        method: "POST",
        body: JSON.stringify({
          ...payload,
          unified: true,
          revision: data?.revision ?? 0,
          operation_id: id,
        }),
      });
      await loadState();
      status(message);
      $("#planner-save-state").textContent = "Все изменения сохранены";
      return result.result;
    } catch (error) {
      if (error.status === 409) await loadState().catch(() => {});
      $("#planner-save-state").textContent = "Изменение не сохранено";
      status(error.message, true);
      throw error;
    } finally {
      busy = false;
      root.removeAttribute("aria-busy");
    }
  }
  const sessionById = (id) => data.sessions.find((s) => s.id === Number(id));
  const slotById = (id) => data.slots.find((s) => s.id === Number(id));
  const entryById = (kind, id) => {
    for (const session of data.sessions) {
      const entry = session.entries.find(
        (e) => e.kind === kind && e.id === Number(id),
      );
      if (entry) return { entry, session };
    }
    return null;
  };
  function render() {
    root.querySelectorAll("[data-layer]").forEach((b) => {
      b.setAttribute("aria-pressed", String(b.dataset.layer === layer));
    });
    const formatter = new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'short', year: 'numeric' });
    $("#planner-period-label").textContent = formatter.formatRange(new Date(from + 'T12:00:00'), new Date(to + 'T12:00:00'));
    renderCalendar();
    renderSummary();
    const latest = data.history[0];
    root.querySelector("[data-action=undo]").disabled =
      !latest?.undoable || latest.revision_after !== data.revision;
    $("#planner-history").innerHTML =
      data.history
        .map(
          (h) =>
            '<div class="planner-history-line">' +
            "<time>" +
            esc(
              new Date(h.created_at).toLocaleString("ru-RU", {
                day: "numeric",
                month: "short",
                hour: "2-digit",
                minute: "2-digit",
              }),
            ) +
            "</time><span>" +
            esc(operationNames[h.operation_type] || h.operation_type) +
            "</span>" +
            "</div>",
        )
        .join("") || '<p class="small">Изменений пока нет.</p>';
  }
  async function ensureSession() {
    if (selected) return selected;
    if (selectedSlot) {
      const result = await mutate("add_session", {
        title: "Занятие",
        slot_id: selectedSlot,
      });
      selected = result.session_id;
      selectedSlot = null;
      return selected;
    }
    throw new Error("Сначала выберите занятие или создайте занятие без даты.");
  }
  async function addResource(resource, target = null) {
    if (target) selected = Number(target);
    const sessionId = await ensureSession();
    await mutate(
      "attach",
      {
        session_id: sessionId,
        layer,
        provider_key: catalog.provider,
        resource_type: resource.type,
        external_id: resource.external_id,
      },
      "Материал добавлен на занятие.",
    );
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
    const group = data.summary.rows.find((r) => (r.lesson_id || 0) === id);
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
    const row = data.summary.rows.find((r) => (r.lesson_id || 0) === lessonId);
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
  async function action(button) {
    const name = button.dataset.action;
    if (name === "close-dialog") {
      $("#planner-dialog").close();
      return;
    }
    button.closest("details.planner-entry-menu")?.removeAttribute("open");
    if (busy) return;
    if (catalog.canHandle(name)) { await catalog.handleAction(button); return; }
    if (sessionActions.canHandle(name)) { await sessionActions.handle(button); return; }
    const session = sessionById(button.dataset.session);
    const [kind, id] = (button.dataset.entry || "").split(":");
    const context = id ? entryById(kind, id) : null;
    switch (name) {
      case "history":
        modal("История изменений", $("#planner-history").innerHTML, null);
        break;
      case "apply-range": {
        const previous = { from, to };
        from = $("#planner-from").value;
        to = $("#planner-to").value;
        try {
          if (!from || !to) throw new Error("Выберите начало и конец периода.");
          await loadState();
        } catch (error) {
          from = previous.from;
          to = previous.to;
          $("#planner-range-picker").open = true;
          throw error;
        }
        break;
      }
      case "settings":
        settingsModal();
        break;
      case "providers":
        modal(
          "Провайдеры курса",
          data.providers
            .map(
              (p) =>
                '<fieldset class="mb-3" data-provider-settings="' +
                esc(p.key) +
                '"><legend class="small fw-semibold">' +
                esc(p.name) +
                '</legend><label class="d-block small"><input type="checkbox" data-provider-enabled ' +
                (p.enabled ? "checked" : "") +
                '> Добавлять материалы на занятия</label><label class="d-block small"><input type="checkbox" data-provider-summary ' +
                (p.show_in_summary ? "checked" : "") +
                "> Показывать столбец в сводке</label>" +
                (!p.configured
                  ? '<p class="small text-secondary">Подключение настраивает администратор на сервере.</p>'
                  : "") +
                "</fieldset>",
            )
            .join(""),
          async () => {
            await mutate("providers", {
              providers: [
                ...root.querySelectorAll("[data-provider-settings]"),
              ].map((row) => ({
                key: row.dataset.providerSettings,
                enabled: row.querySelector("[data-provider-enabled]").checked,
                show_in_summary: row.querySelector("[data-provider-summary]")
                  .checked,
              })),
            });
            await catalog.reconcileProviders(data.providers);
          },
        );
        break;
      case "select-session":
        selected = session.id;
        selectedSlot = null;
        renderCalendar();
        root
          .querySelector(
            '[data-action="select-session"][data-session="' + selected + '"]',
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
        selected = null;
        selectedSlot = Number(button.dataset.slot);
        renderCalendar();
        catalog.open(button);
        break;
      case "today":
        from = shift(
          root.dataset.today,
          -((new Date(root.dataset.today + "T12:00:00Z").getUTCDay() + 6) % 7),
        );
        to = shift(from, 6);
        await loadState();
        break;
      case "previous-range":
      case "next-range":
        from = shift(from, name === "next-range" ? 7 : -7);
        to = shift(from, 6);
        await loadState();
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
      case "undo":
        await mutate(
          "undo",
          { change_id: data.history[0].id },
          "Последнее изменение отменено.",
        );
        break;
      case "highlight-lesson":
        if (activeView === "overview") {
          const group = data.summary.rows.find(
            (r) => r.lesson_id === Number(button.dataset.lesson),
          );
          const first = group?.study_sessions.find((s) => s.date);
          if (first && (first.date < from || first.date > to)) {
            from = first.date;
            to = shift(from, 6);
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
          from = actualDate;
          to = shift(from, 6);
          await loadState();
        }
        selected = Number(button.dataset.session);
        renderCalendar();
        revealSession(selected);
        break;
      }
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
      }
    }
  }
  root.addEventListener("click", (e) => {
    const b = e.target.closest("button[data-action]");
    if (b) {
      e.preventDefault();
      action(b).catch((error) => status(error.message, true));
    }
    const tab = e.target.closest("[data-layer]");
    if (tab && !busy) {
      layer = tab.dataset.layer;
      loadState().catch((error) => status(error.message, true));
    }
    const card = e.target.closest(".planner-session[data-session]");
    if (
      card &&
      !busy &&
      !e.target.closest(
        "button,a,input,select,textarea,summary,details,[draggable=true]",
      ) &&
      !window.getSelection()?.toString()
    ) {
      selected = Number(card.dataset.session);
      selectedSlot = null;
      root.querySelectorAll(".planner-session").forEach((n) => {
        n.classList.toggle("is-selected", n === card);
        const select = n.querySelector('[data-action="select-session"]');
        if (select) select.textContent = n === card ? "Выбрано" : "Выбрать";
      });
      renderTarget();
    }
  });
  root.addEventListener("change", (e) => {
    const t = e.target;
    if (catalog.handleChange(t)) return;
    if (t.id === "planner-view") setView(t.value);
    if (t.id === "planner-metric") renderChart();
    else if (t.dataset.entryState) {
      const [kind, id] = t.dataset.entryState.split(":");
      mutate(
        "update_entry",
        { kind, entry_id: Number(id), fact_state: t.value },
        "Состояние материала сохранено в черновике журнала.",
      ).catch((error) => status(error.message, true));
    }
  });
  initPlannerDragDrop({ root, getState, catalog, setTarget, ensureSession, mutate, status });
  root.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      if ($("#planner-catalog").open) {
        e.preventDefault();
        e.stopPropagation();
        catalog.close();
      }
      root.querySelectorAll("details.planner-entry-menu[open]").forEach(n => n.open = false);
    }
  });
  document.addEventListener("click", (e) => {
    root
      .querySelectorAll("details.planner-entry-menu[open]")
      .forEach((menu) => {
        if (!menu.contains(e.target)) menu.open = false;
      });
  });
  updateTopOffset();
  if (globalThis.ResizeObserver) {
    const topbar = document.querySelector(".gc-topbar");
    if (topbar) new ResizeObserver(updateTopOffset).observe(topbar);
  }
  loadState()
    .then(() => {
      $("#planner-save-state").textContent = "Сохранено";
      status("");
      return catalog.load();
    })
    .catch((error) => status(error.message, true));
}
