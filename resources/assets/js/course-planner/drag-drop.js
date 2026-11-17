export function initPlannerDragDrop({ root, getState, catalog, setTarget, ensureSession, mutate, status }) {
  const $ = selector => root.querySelector(selector);
  const isBusy = () => getState().busy;
  let dragged = null;
  root.addEventListener("dragstart", (e) => {
    if (isBusy() || e.target.closest("button,input,select,a,[data-read-only]")) return;
    const header = e.target.closest("[data-drag-session]");
    const entry = e.target.closest(".planner-entry");
    const source = e.target.closest(".planner-catalog-card");
    if (header)
      dragged = { kind: "session", id: Number(header.dataset.dragSession) };
    else if (entry) {
      const [kind, id] = entry.dataset.entry.split(":");
      dragged = { kind: "entry", entryKind: kind, id: Number(id) };
    } else if (source)
      dragged = {
        kind: "catalog",
        resource: catalog.items[Number(source.dataset.catalogIndex)],
        provider: catalog.provider,
      };
    if (dragged) {
      root.classList.add("is-dragging");
      e.dataTransfer.setData("application/json", JSON.stringify(dragged));
      e.dataTransfer.effectAllowed =
        dragged.kind === "catalog" ? "copy" : "move";
    }
  });
  root.addEventListener("dragover", (e) => {
    if (
      dragged?.kind === "session" &&
      e.target.closest("#planner-backlog-panel")
    )
      $("#planner-backlog-panel").open = true;
    const zone = e.target.closest(".planner-drop");
    if (!zone || !dragged || isBusy()) return;
    e.preventDefault();
    zone.classList.add("is-dragover");
    e.dataTransfer.dropEffect = dragged.kind === "catalog" ? "copy" : "move";
    if (zone.closest("#planner-calendar")) {
      if (e.clientY < 90) window.scrollBy(0, -14);
      else if (e.clientY > window.innerHeight - 90) window.scrollBy(0, 14);
    }
  });
  root.addEventListener("dragleave", (e) => {
    const zone = e.target.closest(".planner-drop");
    if (zone && !zone.contains(e.relatedTarget))
      zone.classList.remove("is-dragover");
  });
  root.addEventListener("dragend", () => {
    dragged = null;
    root.classList.remove("is-dragging");
    root
      .querySelectorAll(".is-dragover")
      .forEach((n) => n.classList.remove("is-dragover"));
  });
  root.addEventListener("drop", async (e) => {
    const zone = e.target.closest(".planner-drop");
    if (!zone || !dragged || isBusy()) return;
    e.preventDefault();
    const payload = dragged;
    dragged = null;
    root.classList.remove("is-dragging");
    root
      .querySelectorAll(".is-dragover")
      .forEach((n) => n.classList.remove("is-dragover"));
    try {
      if (payload.kind === "session") {
        await mutate(
          "move_session",
          {
            session_id: payload.id,
            slot_id: zone.dataset.slot ? Number(zone.dataset.slot) : null,
          },
          "Занятие перенесено.",
        );
        return;
      }
      if (zone.hasAttribute("data-drop-backlog"))
        throw new Error(
          "Выберите занятие без даты или создайте новое в этом разделе.",
        );
      setTarget(
        zone.dataset.session ? Number(zone.dataset.session) : null,
        zone.dataset.slot ? Number(zone.dataset.slot) : null,
      );
      const target = await ensureSession();
      if (payload.kind === "catalog") {
        await mutate(
          "attach",
          {
            session_id: target,
            layer: getState().layer,
            provider_key: payload.provider,
            resource_type: payload.resource.type,
            external_id: payload.resource.external_id,
          },
          "Материал добавлен.",
        );
      } else {
        const before = e.target.closest(".planner-entry");
        const parts = before?.dataset.entry.split(":");
        await mutate(
          "move_entry",
          {
            kind: payload.entryKind,
            entry_id: payload.id,
            target_session_id: target,
            before: parts ? { kind: parts[0], id: Number(parts[1]) } : null,
          },
          "Материал перенесён. Дедлайн сохранён.",
        );
      }
    } catch (error) {
      status(error.message, true);
    }
  });
}
