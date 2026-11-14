import { esc } from "./format.js";

export function createProviderSettings({ root, getState, mutate, dialog, catalog }) {
  const { modal } = dialog;
  return function openProviderSettings() {
    modal(
      "Провайдеры курса",
      getState().data.providers
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
        await catalog.reconcileProviders(getState().data.providers);
      },
    );
  };
}
