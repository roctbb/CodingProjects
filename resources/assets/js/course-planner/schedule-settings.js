import { esc, date, shift } from "./format.js";

export function createScheduleSettings({ root, getState, setRange, request, mutate, dialog }) {
  const $ = selector => root.querySelector(selector);
  const { field, modal, values } = dialog;
  function settingsModal() {
    const settings = getState().data.settings;
    const existing = settings.rules.length
      ? settings.rules
      : [
          { weekday: 1, start_time: "14:00", duration_minutes: 90 },
          { weekday: 4, start_time: "14:00", duration_minutes: 90 },
        ];
    const ruleRow = (r = { weekday: 1, duration_minutes: 90 }) =>
      '<div class="planner-rule" data-rule-key="' +
      esc(r.rule_key || crypto.randomUUID()) +
      '" data-valid-from="' +
      esc(r.valid_from || "") +
      '" data-valid-until="' +
      esc(r.valid_until || "") +
      '">' +
      [
        '<select class="form-select form-select-sm" data-rule-weekday aria-label="День недели">' +
          ["Пн", "Вт", "Ср", "Чт", "Пт", "Сб", "Вс"]
            .map(
              (d, i) =>
                '<option value="' +
                (i + 1) +
                '" ' +
                (Number(r.weekday) === i + 1 ? "selected" : "") +
                ">" +
                d +
                "</option>",
            )
            .join("") +
          "</select>",
        '<input class="form-control form-control-sm" type="time" data-rule-time aria-label="Начало" value="' +
          esc(r.start_time || "") +
          '">',
        '<input class="form-control form-control-sm" type="number" min="5" max="480" data-rule-duration aria-label="Минут" value="' +
          (r.duration_minutes || 90) +
          '">',
        '<button type="button" class="btn btn-sm btn-link" data-remove-rule aria-label="Убрать недельную позицию">×</button>',
      ].join("") +
      "</div>";
    modal(
      "Расписание курса",
      field(
        "starts_on",
        "Первый день курса",
        "date",
        settings.starts_on || root.dataset.today,
        "required",
      ) +
        field(
          "ends_on",
          "Последний день курса",
          "date",
          settings.ends_on || shift(root.dataset.today, 180),
          "required",
        ) +
        field(
          "timezone",
          "Часовой пояс",
          "text",
          settings.timezone || "Europe/Moscow",
          "required",
        ) +
        field(
          "default_duration_minutes",
          "Длительность по умолчанию, минут",
          "number",
          settings.default_duration_minutes || 90,
          'min="5" max="480" required',
        ) +
        '<div class="planner-field"><label>День · начало · минуты</label><div id="planner-rules">' +
        existing.map(ruleRow).join("") +
        '</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="planner-add-rule">Добавить недельную позицию</button></div><p class="small text-secondary mt-3">После расчёта проверьте затронутые занятия. Проведённые и закреплённые занятия сохранятся.</p>',
      async () => {
        const payload = {
          ...values(),
          default_duration_minutes: Number(values().default_duration_minutes),
          rules: [...root.querySelectorAll(".planner-rule")].map((row) => ({
            rule_key: row.dataset.ruleKey,
            valid_from: row.dataset.validFrom || null,
            valid_until: row.dataset.validUntil || null,
            weekday: Number(row.querySelector("[data-rule-weekday]").value),
            start_time: row.querySelector("[data-rule-time]").value || null,
            duration_minutes: Number(
              row.querySelector("[data-rule-duration]").value,
            ),
          })),
        };
        const preview = await request("/calendar/preview", {
          method: "POST",
          body: JSON.stringify(payload),
        });
        modal(
          "Проверить новое расписание",
          '<div class="planner-preview"><p>Доступных занятий: <strong>' +
            preview.available +
            "</strong></p><p>Исключённых дат: " +
            preview.excluded +
            "</p><p>Затронутых занятий: " +
            preview.affected_sessions.length +
            "</p>" +
            preview.affected_sessions
              .map(
                (s) =>
                  "<p>" +
                  esc(date(s.date)) +
                  " · " +
                  esc(s.title) +
                  " — " +
                  (s.preserved
                    ? "останется на месте"
                    : "перейдёт в «Без даты»") +
                  "</p>",
              )
              .join("") +
            "</div>",
          async () => {
            setRange(payload.starts_on, shift(payload.starts_on, 6));
            await mutate("configure", payload, "Расписание сохранено.");
          },
          "Применить расписание",
        );
        return false;
      },
      "Рассчитать",
    );
    $("#planner-add-rule").onclick = () =>
      $("#planner-rules").insertAdjacentHTML("beforeend", ruleRow());
    $("#planner-rules").onclick = (e) => {
      if (e.target.closest("[data-remove-rule]"))
        e.target.closest(".planner-rule").remove();
    };
  }
  return settingsModal;
}
