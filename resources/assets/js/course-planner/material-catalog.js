import { esc, date } from './format.js';
import { nativeLessonOverlap } from './resource-overlap.js';
import { initialCatalogChapter } from './catalog-context.js';

export function createMaterialCatalog({ root, getState, setTarget, request, ensureSession, mutate, sessionTitle, status, addResource }) {
  const $ = selector => root.querySelector(selector);
  let catalogReturnFocus = null, searchTimer, catalogAbort, requestNumber = 0;
  const catalog = { provider: 'codingprojects', chapter: null, rememberedChapter: undefined, query: '', type: 'lesson', cursor: null,
    previous: [], next: null, parent: [], items: [], manifest: null, selection: new Map(), loading: false };
  const cache = new Map();
  const sessionById = id => getState().data.sessions.find(s => s.id === Number(id));
  const slotById = id => getState().data.slots.find(s => s.id === Number(id));
  function closeCatalog() {
    const dialog = $("#planner-catalog");
    if (dialog.open) dialog.close();
    if (catalogReturnFocus?.isConnected) catalogReturnFocus.focus({ preventScroll: true });
    catalogReturnFocus = null;
  }
  function openCatalog(button) {
    let { data, selected, selectedSlot } = getState();
    if (!selected && !selectedSlot) {
      selectedSlot = data.slots.find(s => s.state === "available" && s.local_date >= data.today)?.id
        ?? data.slots.find(s => s.state === "available")?.id ?? null;
      setTarget(selected, selectedSlot);
    }
    if (!selected && !selectedSlot) throw new Error("Добавьте занятие или настройте расписание курса.");
    catalog.selection.clear();
    const changedChapter = syncChapter();
    const menu = button?.closest('details.planner-entry-menu');
    catalogReturnFocus = menu?.querySelector('summary') || button || document.activeElement;
    if (menu) menu.open = false;
    $("#planner-catalog-error").textContent = "";
    renderTarget();
    const dialog = $("#planner-catalog");
    if (!dialog.open) dialog.showModal();
    $("#planner-search").focus();
    if (!catalog.manifest || changedChapter) loadCatalog().catch(error => $("#planner-catalog-error").textContent = error.message);
  }
  function catalogSelection() {
    const count = catalog.selection.size;
    $("#planner-catalog-selected").textContent = count ? `Выбрано: ${count}` : "Ничего не выбрано";
    const submit = root.querySelector('[data-action="catalog-attach-selected"]');
    submit.disabled = !count || getState().busy || catalog.loading;
    submit.textContent = count ? `Добавить (${count})` : 'Добавить';
    $("#planner-catalog-selection").hidden = !count;
    $("#planner-catalog-selection-list").innerHTML = [...catalog.selection].map(([key, item]) =>
      `<li><span>${esc(item.title)}<small>${esc(item.type_label || item.resource_type)}</small></span><button type="button" class="planner-quiet-button" data-action="catalog-remove" data-key="${esc(key)}" aria-label="Убрать из выбранного: ${esc(item.title)}"><i class="fas fa-xmark" aria-hidden="true"></i></button></li>`).join('');
  }
  function syncChapter() {
    const { data, selected } = getState();
    const chapter = initialCatalogChapter({ data, sessionId: selected,
      contextChapter: document.querySelector('[data-course-context]')?.dataset.courseChapter,
      rememberedChapter: catalog.rememberedChapter });
    if (catalog.chapter === chapter) return false;
    catalog.chapter = chapter;
    catalog.parent = [];
    catalog.query = '';
    catalog.type = catalog.provider === 'codingprojects' ? 'lesson' : '';
    $("#planner-search").value = '';
    resetCatalog();
    return true;
  }
  function renderChapter() {
    const native = catalog.provider === 'codingprojects';
    const select = $("#planner-chapter"), { data } = getState();
    select.hidden = !native;
    root.querySelector('[data-catalog-chapter-label]').hidden = !native;
    select.innerHTML = '<option value="">Все главы</option>' + (data.chapters || []).map((chapter, index) =>
      `<option value="${esc(chapter.id)}">${index + 1}. ${esc(chapter.name)}</option>`).join('') +
      (data.lessons.some(lesson => !lesson.chapter_id) ? '<option value="0">Без главы</option>' : '');
    select.value = catalog.chapter ?? '';
    $("#planner-search").placeholder = native && catalog.chapter !== '' ? 'Поиск в главе…' : 'Найти материал…';
  }
  function renderTarget() {
    const { data, selected, selectedSlot, layer } = getState();
    const session = sessionById(selected);
    const slot = slotById(session?.calendar_slot_id || selectedSlot);
    $("#planner-target-title").textContent = session
      ? date(
          layer === "fact"
            ? session.record?.held_on || slot?.local_date
            : slot?.local_date,
        ) + (slot?.start_time ? " · " + slot.start_time : "")
      : slot
        ? date(slot.local_date) + (slot.start_time ? ' · ' + slot.start_time : '')
        : "Выберите занятие";
    $("#planner-target-subtitle").textContent = session
      ? [sessionTitle(session), session.audience_name || data.course.audience_name, layer === "fact" ? 'в журнал' : ''].filter(Boolean).join(' · ')
      : slot
        ? ['Свободное место', data.course.audience_name].filter(Boolean).join(' · ')
        : "Или перетащите материал на нужную дату";
    if (catalog.manifest) renderCatalog();
  }
  function resetCatalog() {
    catalog.cursor = null;
    catalog.previous = [];
    catalog.next = null;
  }
  function syncNavigation() {
    const previous = root.querySelector('[data-action="catalog-prev"]');
    const next = root.querySelector('[data-action="catalog-next"]');
    const up = !catalog.previous.length && catalog.parent.length > 0;
    previous.textContent = up ? '← На уровень выше' : '← Предыдущая страница';
    previous.setAttribute('aria-label', up ? 'На уровень выше в каталоге материалов' : 'Предыдущая страница материалов');
    previous.disabled = catalog.loading || (!catalog.previous.length && !up);
    next.disabled = catalog.loading || !catalog.next;
    const paged = catalog.previous.length > 0 || !!catalog.next;
    $("#planner-page").hidden = !paged;
    next.hidden = !paged;
    previous.closest('.planner-pagination').hidden = !paged && !up;
    catalogSelection();
  }
  async function parentCatalog() {
    const parent = catalog.parent.pop();
    if (!parent) return;
    Object.assign(catalog, parent.browse || {type: 'lesson', query: '', cursor: null, previous: [], next: null});
    $("#planner-search").value = catalog.query;
    await loadCatalog();
  }
  async function loadCatalog(force = false) {
    clearTimeout(searchTimer);
    if (catalog.chapter === null) syncChapter();
    renderChapter();
    const number = ++requestNumber;
    catalogAbort?.abort();
    catalogAbort = new AbortController();
    catalog.loading = true;
    const context = catalog.parent.at(-1);
    const query = new URLSearchParams({ limit: "25" });
    if (catalog.provider === 'codingprojects' && catalog.chapter !== '') query.set('chapter', catalog.chapter);
    if (catalog.query) query.set("query", catalog.query);
    if (catalog.type) query.set("type", catalog.type);
    if (catalog.cursor) query.set("cursor", catalog.cursor);
    if (context) {
      query.set("parent_type", context.type);
      query.set("parent_external_id", context.external_id);
    }
    const key = catalog.provider + "?" + query;
    const node = $("#planner-catalog-list");
    node.setAttribute("aria-busy", "true");
    node.replaceChildren();
    $("#planner-catalog-error").textContent = '';
    $("#planner-catalog-status").textContent = "Загрузка материалов…";
    root
      .querySelectorAll("[data-action^=catalog-]")
      .forEach((b) => (b.disabled = true));
    try {
      let result = cache.get(key);
      if (!result || force) {
        result = await request(
          "/providers/" +
            encodeURIComponent(catalog.provider) +
            "/resources?" +
            query,
          { signal: catalogAbort.signal },
        );
        cache.set(key, result);
        if (cache.size > 20) cache.delete(cache.keys().next().value);
      }
      if (number !== requestNumber) return;
      catalog.manifest = result.manifest;
      catalog.items = result.catalog.items;
      catalog.next = result.catalog.page.next_cursor;
      renderCatalog();
      $("#planner-catalog-status").textContent = catalog.items.length
        ? "Материалов на странице: " + catalog.items.length
        : catalog.provider === 'codingprojects' && catalog.chapter !== '' && !catalog.query
          ? 'В этой главе пока нет материалов.' : 'По вашему запросу ничего не найдено.';
    } catch (error) {
      if (error.name === "AbortError" || number !== requestNumber) return;
      $("#planner-catalog-status").innerHTML =
        esc(error.message) +
        ' <button type="button" class="planner-inline-button" data-action="catalog-retry">Повторить</button>';
      if (error.code === "cursor_expired") resetCatalog();
    } finally {
      if (number === requestNumber) {
        catalog.loading = false;
        node.removeAttribute("aria-busy");
        syncNavigation();
      }
    }
  }
  const resourceIdentity = (provider, type, id) => JSON.stringify([provider, type, String(id)]);
  function renderCatalog() {
    renderChapter();
    const { selected } = getState();
    const types = catalog.manifest?.resource_types || [];
    $("#planner-type").innerHTML = '<option value="">Все типы</option>' + types.map(t => `<option value="${esc(t.key)}">${esc(t.label)}</option>`).join("");
    $("#planner-type").value = catalog.type;
    $("#planner-page").textContent = `Страница ${catalog.previous.length + 1}`;
    $("#planner-catalog-breadcrumb").innerHTML = catalog.parent.length ? `<button type="button" class="planner-inline-button small" data-action="catalog-parent">← Назад · ${esc(catalog.parent.at(-1).title)}</button>` : "";
    const entries = sessionById(selected)?.entries || [];
    $("#planner-catalog-list").innerHTML = catalog.items.map((r,i) => {
      const key = resourceIdentity(catalog.provider,r.type,r.external_id);
      const added = entries.some(e => e.kind === "resource" && resourceIdentity(e.resource.provider_key,e.resource.type,e.resource.external_id) === key);
      const overlap = entries.some(e => e.kind === "resource" && nativeLessonOverlap({...r, provider_key:catalog.provider}, e.resource));
      if (added || overlap) catalog.selection.delete(key);
      const children = types.find(t => t.key === r.type)?.capabilities?.children;
      const points = r.public_metadata?.points;
      const unavailableLabel = added ? 'Добавлено' : overlap ? (r.type === 'lesson' ? 'Ступени уже добавлены' : 'Урок уже добавлен') : '';
      return `<div class="planner-catalog-card"><label class="planner-catalog-choice"><input type="checkbox" class="form-check-input" data-catalog-select="${i}" ${added ? "checked disabled" : overlap ? "disabled" : catalog.selection.has(key) ? "checked" : ""} aria-label="Выбрать ${esc(r.title)}"><span class="planner-catalog-card-body"><strong>${esc(r.title)}</strong><small>${esc(r.type_label || r.type)}${points ? ` · ${esc(points.value)} ${esc(points.unit)}` : ""}${r.public_metadata?.is_hidden ? " · скрыто" : ""}${unavailableLabel ? ` · ${unavailableLabel}` : ''}</small></span>${added ? '<span class="planner-added"><i class="fas fa-check" aria-hidden="true"></i></span>' : ""}</label>${children ? `<button type="button" class="planner-browse" data-action="catalog-children" data-index="${i}">Открыть материалы →</button>` : ""}</div>`;
    }).join("");
    syncNavigation();
  }
  function syncProviders(providers) {
    const options = providers
      .map(
        (p) =>
          '<option value="' +
          esc(p.key) +
          '" ' +
          (!p.configured || !p.enabled ? "disabled" : "") +
          ">" +
          esc(p.name) +
          (!p.configured ? " · не настроен" : "") +
          "</option>",
      )
      .join("");
    if ($("#planner-provider").innerHTML !== options)
      $("#planner-provider").innerHTML = options;
    $("#planner-provider").value = catalog.provider;
  }
  async function reconcileProviders(providers) {
    const available = providers.find((p) => p.enabled && p.configured);
    if (!providers.some((p) => p.key === catalog.provider && p.enabled && p.configured)) {
      catalog.provider = available?.key || "";
      catalog.items = [];
      catalog.manifest = null;
      resetCatalog();
      renderCatalog();
      if (available) await loadCatalog();
    }
  }
  const actionNames = new Set(['open-catalog','session-materials','close-catalog','catalog-attach-selected','catalog-remove',
    'catalog-next','catalog-prev','catalog-retry','catalog-parent','catalog-children','catalog-add']);
  async function handleAction(button) {
    switch (button.dataset.action) {
      case "open-catalog":
        openCatalog(button);
        break;
      case "session-materials":
        setTarget(Number(button.dataset.session));
        openCatalog(button);
        break;
      case "close-catalog":
        closeCatalog();
        break;
      case 'catalog-remove': {
        catalog.selection.delete(button.dataset.key);
        renderCatalog();
        const remaining = $("#planner-catalog-selection-list").querySelector('button');
        (remaining || $("#planner-search")).focus({ preventScroll: true });
        break;
      }
      case "catalog-attach-selected": {
        const resources = [...catalog.selection.values()];
        if (!resources.length) break;
        $("#planner-catalog-error").textContent = "";
        const submit = root.querySelector('[data-action="catalog-attach-selected"]');
        submit.disabled = true;
        try {
          const sessionId = await ensureSession();
          await mutate("attach_many", {session_id: sessionId, layer: "plan", items: resources.map(({provider_key, resource_type, external_id}) => ({provider_key, resource_type, external_id}))}, "Материалы добавлены на занятие.");
          catalog.selection.clear();
          closeCatalog();
          root.querySelector(`.planner-session[data-session="${getState().selected}"] [data-action="session-materials"]`)?.focus({preventScroll:true});
        } catch (error) {
          $("#planner-catalog-error").textContent = error.message;
        } finally { catalogSelection(); }
        break;
      }
      case "catalog-next":
        if (catalog.next) {
          catalog.previous.push(catalog.cursor);
          catalog.cursor = catalog.next;
          await loadCatalog();
        }
        break;
      case "catalog-prev":
        if (catalog.previous.length) {
          catalog.cursor = catalog.previous.pop();
          await loadCatalog();
        } else if (catalog.parent.length) await parentCatalog();
        break;
      case "catalog-retry":
        await loadCatalog(true);
        break;
      case "catalog-parent":
        await parentCatalog();
        break;
      case "catalog-children": {
        const r = catalog.items[Number(button.dataset.index)];
        catalog.parent.push({...r, browse: {type: catalog.type, query: catalog.query, cursor: catalog.cursor, previous: [...catalog.previous], next: catalog.next}});
        catalog.type = "";
        catalog.query = "";
        $("#planner-search").value = "";
        resetCatalog();
        await loadCatalog();
        break;
      }
      case "catalog-add":
        await addResource(catalog.items[Number(button.dataset.index)]);
        break;
    }
  }
  function handleChange(t) {
    if (t.hasAttribute("data-catalog-select")) {
      const resource = catalog.items[Number(t.dataset.catalogSelect)];
      const key = resourceIdentity(catalog.provider,resource.type,resource.external_id);
      if (t.checked && catalog.selection.size >= 50) {
        t.checked = false;
        $("#planner-catalog-error").textContent = "За один раз можно выбрать до 50 материалов.";
      } else if (t.checked && [...catalog.selection.values()].some(item => nativeLessonOverlap({...resource, provider_key:catalog.provider}, item))) {
        t.checked = false;
        $("#planner-catalog-error").textContent = "Выберите урок целиком или его ступени. Они не должны повторять друг друга на занятии.";
      } else if (t.checked) {
        catalog.selection.set(key, {provider_key: catalog.provider, resource_type: resource.type, external_id: resource.external_id,
          title: resource.title, type_label: resource.type_label, public_metadata: resource.public_metadata});
        $("#planner-catalog-error").textContent = '';
      }
      else catalog.selection.delete(key);
      catalogSelection();
      return true;
    }
    if (t.id === 'planner-chapter') {
      catalog.chapter = t.value;
      catalog.rememberedChapter = t.value;
      catalog.parent = [];
      catalog.type = 'lesson';
      resetCatalog();
      loadCatalog().catch(error => status(error.message, true));
      return true;
    }
    if (t.id === "planner-provider") {
      catalogAbort?.abort();
      catalog.provider = t.value;
      catalog.type = catalog.provider === 'codingprojects' ? 'lesson' : '';
      catalog.query = "";
      catalog.parent = [];
      catalog.items = [];
      $("#planner-catalog-list").innerHTML = "";
      $("#planner-search").value = "";
      resetCatalog();
      loadCatalog().catch((error) => status(error.message, true));
      return true;
    } else if (t.id === "planner-type") {
      catalog.type = t.value;
      resetCatalog();
      loadCatalog();
      return true;
    }
    return false;
  }
  $("#planner-search").addEventListener("input", (e) => {
    catalog.query = e.target.value.trim();
    catalogAbort?.abort();
    requestNumber++;
    clearTimeout(searchTimer);
    resetCatalog();
    searchTimer = setTimeout(() => loadCatalog(), 300);
  });
  $("#planner-search").addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      e.preventDefault();
      clearTimeout(searchTimer);
      loadCatalog();
    }
  });
  $("#planner-catalog").addEventListener("click", e => {
    if (e.target !== e.currentTarget) return;
    const box = e.currentTarget.getBoundingClientRect();
    if (e.clientX < box.left || e.clientX > box.right || e.clientY < box.top || e.clientY > box.bottom) closeCatalog();
  });
  $("#planner-catalog").addEventListener("cancel", e => { e.preventDefault(); closeCatalog(); });
  $("#planner-catalog").addEventListener("close", () => {
    catalog.selection.clear();
    catalogSelection();
    if (catalogReturnFocus?.isConnected) catalogReturnFocus.focus({preventScroll:true});
    catalogReturnFocus = null;
  });
  return {
    open: openCatalog, close: closeCatalog, load: loadCatalog, refreshTarget: renderTarget,
    syncProviders, reconcileProviders, handleAction, handleChange,
    canHandle: name => actionNames.has(name),
    get provider() { return catalog.provider; },
    get items() { return catalog.items; },
  };
}
