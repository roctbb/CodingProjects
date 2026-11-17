import { createProviderSettings } from "./provider-settings.js";
import { createEntryActions } from "./entry-actions.js";
import { createWorkspaceNavigation } from "./workspace-navigation.js";
import { createScheduleSettings } from "./schedule-settings";
import { createEntryDialogs } from "./entry-dialogs";
import { createSessionActions } from "./session-actions";
import { esc } from './format';
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
  let from = "", to = "", stateRequestNumber = 0;
  const getState = () => ({ data, layer, selected, selectedSlot, from, to, busy, scope: root.dataset.subgroup || 'all' });
  const setRange = (start, end) => { from = start; to = end; };
  const setTarget = (sessionId, slotId = null) => { selected = sessionId; selectedSlot = slotId; };
  const { sessionTitle, sessionMarkup } = createSessionView({ getState });
  const renderCalendar = createCalendarView({ root, getState, sessionMarkup, onRendered: renderTarget });
  const { renderSummary, renderChart } = createSummaryView({ root, getState });
  const dialog = createPlannerDialog({ root, onOpen: () => catalog.close() });
  const { modal } = dialog;
  const operationNames = {
    configure: "Изменено расписание",
    providers: "Изменены провайдеры курса",
    add_exception: "Исключена дата",
    remove_exception: "Восстановлена дата",
    add_slot: "Добавлено занятие",
    add_session: "Добавлено занятие без даты",
    move_session: "Перенесено занятие",
    update_session: "Изменено занятие",
    session_bonus: "Бонус всем задачам занятия",
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
  const request = createPlannerRequest(api, root.dataset.subgroup);
  const catalog = createMaterialCatalog({ root, getState, setTarget, request, ensureSession, mutate, sessionTitle, status, addResource });
  function renderTarget() { catalog.refreshTarget(); }
  const settingsModal = createScheduleSettings({ root, getState, setRange, request, mutate, dialog });
  const entryDialogs = createEntryDialogs({ root, getState, request, mutate, dialog, sessionTitle });
  const sessionActions = createSessionActions({ root, getState, setTarget, setRange, loadState, ensureSession, mutate, request, dialog, renderCalendar, renderTarget, revealSession });

  const providerSettings = createProviderSettings({ root, getState, mutate, dialog, catalog });
  const entryActions = createEntryActions({ getState, mutate, dialog, entryDialogs });
  const navigation = createWorkspaceNavigation({ root, getState, setRange, setTarget, loadState, request, dialog, catalog, sessionTitle, renderCalendar, revealSession, status });

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
    if (!data.sessions.some((s) => s.id === selected && s.editable !== false))
      selected =
        data.sessions.find((s) => s.editable !== false &&
          data.slots.some(
            (slot) =>
              slot.id === s.calendar_slot_id &&
              slot.local_date >= root.dataset.today &&
              slot.state === "available",
          ),
        )?.id ??
        data.sessions.find((s) => s.editable !== false && s.calendar_slot_id)?.id ??
        data.sessions.find((s) => s.editable !== false)?.id ??
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
          revision: payload.destination_revision ?? data?.revision ?? 0,
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
    if (selected) {
      if (data.sessions.find(s => s.id === selected)?.editable === false) throw new Error('Для изменения выберите подгруппу этого занятия.');
      return selected;
    }
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
    if (entryActions.canHandle(name)) { await entryActions.handle(button); return; }
    if (navigation.canHandle(name)) { await navigation.handle(button); return; }
    switch (name) {
      case "providers":
        providerSettings();
        break;
      case "history":
        modal("История изменений", $("#planner-history").innerHTML, null);
        break;
      case "settings":
        settingsModal();
        break;
      case "undo":
        await mutate(
          "undo",
          { change_id: data.history[0].id },
          "Последнее изменение отменено.",
        );
        break;
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
    if (t.id === "planner-view") navigation.setView(t.value);
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
