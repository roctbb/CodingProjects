import { esc } from "./format.js";

export function createPlannerDialog({ root, onOpen }) {
  const $ = selector => root.querySelector(selector);
  let modalSubmit = null;
  function field(name, label, type = "text", value = "", attrs = "") {
    return (
      '<div class="planner-field"><label for="planner-field-' +
      name +
      '">' +
      label +
      '</label><input id="planner-field-' +
      name +
      '" class="form-control form-control-sm" name="' +
      name +
      '" type="' +
      type +
      '" value="' +
      esc(value) +
      '" ' +
      attrs +
      "></div>"
    );
  }
  function select(name, label, options, value) {
    return (
      '<div class="planner-field"><label for="planner-field-' +
      name +
      '">' +
      label +
      '</label><select id="planner-field-' +
      name +
      '" class="form-select form-select-sm" name="' +
      name +
      '">' +
      options
        .map(
          ([k, v]) =>
            '<option value="' +
            esc(k) +
            '" ' +
            (String(k) === String(value) ? "selected" : "") +
            ">" +
            esc(v) +
            "</option>",
        )
        .join("") +
      "</select></div>"
    );
  }
  function modal(title, html, submit, label = "Сохранить") {
    onOpen();
    const dialog = $("#planner-dialog");
    $("#planner-dialog-title").textContent = title;
    $("#planner-dialog-body").innerHTML = html;
    $("#planner-dialog-error").textContent = "";
    $("#planner-dialog-submit").textContent = label;
    $("#planner-dialog-submit").hidden = !submit;
    dialog.querySelector('footer [data-action="close-dialog"]').textContent =
      submit ? "Отмена" : "Закрыть";
    modalSubmit = submit;
    if (!dialog.open) dialog.showModal();
  }
  const values = () =>
    Object.fromEntries(new FormData($("#planner-dialog-form")));
  $("#planner-dialog-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    if (!modalSubmit) return;
    const button = $("#planner-dialog-submit");
    button.disabled = true;
    $("#planner-dialog-error").textContent = "";
    try {
      const close = await modalSubmit();
      if (close !== false) $("#planner-dialog").close();
    } catch (error) {
      $("#planner-dialog-error").textContent = error.message;
    } finally {
      button.disabled = false;
    }
  });
  return { field, select, modal, values };
}
