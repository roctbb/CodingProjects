import { esc, date, shift } from "./format.js";

export function createSummaryView({ root, getState }) {
  const $ = selector => root.querySelector(selector);
  function renderSummary() {
    const { data, layer } = getState();
    const providers = data.providers.filter(
      (p) => p.show_in_summary && p.configured,
    );
    $("#planner-summary-caption").textContent =
      layer === "plan"
        ? "Все занятия курса"
        : "Весь курс · подтверждённый факт · дедлайны текущие";
    $("#planner-summary-head").innerHTML =
      "<tr><th>Урок программы</th><th>Занятия</th>" +
      providers.map((p) => "<th>" + esc(p.name) + "</th>").join("") +
      "<th>Дедлайны</th></tr>";
    $("#planner-summary-body").innerHTML = data.summary.rows
      .map(
        (row) =>
          '<tr class="planner-summary-row" data-lesson="' +
          (row.lesson_id || 0) +
          '"><td><button type="button" data-action="highlight-lesson" data-lesson="' +
          (row.lesson_id || 0) +
          '">' +
          esc(row.name) +
          "</button></td><td>" +
          row.session_count +
          "</td>" +
          providers
            .map((p) => {
              const count = row.providers[p.key];
              return (
                '<td><button type="button" data-action="summary-detail" data-lesson="' +
                (row.lesson_id || 0) +
                '" data-provider="' +
                esc(p.key) +
                '">' +
                (count?.materials ?? 0) +
                (count?.partial
                  ? " <small>(" + count.partial + " част.)</small>"
                  : "") +
                "</button></td>"
              );
            })
            .join("") +
          '<td><button type="button" data-action="lesson-deadlines" data-lesson="' +
          (row.lesson_id || 0) +
          '">' +
          (row.task_count ? row.deadline_count + " / " + row.task_count : "—") +
          (row.other_deadline_count
            ? " + " + row.other_deadline_count + " срок."
            : "") +
          "</button></td></tr>",
      )
      .join("");
    const options = [
      ["deadlines", "Количество дедлайнов"],
      ...providers.map((p) => ["provider:" + p.key, "Материалы · " + p.name]),
      ...[
        ...new Set(data.summary.weeks.flatMap((w) => Object.keys(w.points))),
      ].map((unit) => ["points:" + unit, "Баллы к сдаче · " + unit]),
    ];
    const metric = $("#planner-metric").value;
    $("#planner-metric").innerHTML = options
      .map(
        ([key, title]) =>
          '<option value="' + esc(key) + '">' + esc(title) + "</option>",
      )
      .join("");
    if (options.some(([key]) => key === metric))
      $("#planner-metric").value = metric;
    renderChart();
  }
  function renderChart() {
    const { data, from, to } = getState();
    if (!data) return;
    const metric = $("#planner-metric").value;
    const weeks = [];
    const monday = shift(
      from,
      -((new Date(from + "T12:00:00Z").getUTCDay() + 6) % 7),
    );
    for (let week = monday; week <= to; week = shift(week, 7)) {
      weeks.push(
        data.summary.weeks.find((w) => w.week === week) || {
          week,
          providers: {},
          deadlines: 0,
          points: {},
        },
      );
    }
    const values = weeks.map((w) =>
      metric === "deadlines"
        ? w.deadlines
        : metric.startsWith("provider:")
          ? (w.providers[metric.slice(9)]?.materials ?? 0)
          : (w.points[metric.slice(7)] ?? 0),
    );
    const max = Math.max(...values, 1);
    $("#planner-chart").innerHTML = weeks.length
      ? '<div class="planner-chart" role="img" aria-label="' +
        esc($("#planner-metric").selectedOptions[0]?.textContent) +
        '">' +
        weeks
          .map(
            (w, i) =>
              '<div class="planner-chart-column"><span class="planner-chart-value">' +
              values[i] +
              '</span><div class="planner-chart-bar"><span style="height:' +
              Math.max(1, (values[i] / max) * 100) +
              'px"></span></div><span>' +
              esc(date(w.week)) +
              "</span></div>",
          )
          .join("") +
        "</div>"
      : '<p class="small text-secondary">За выбранный период материалов и сроков пока нет.</p>';
  }
  return { renderSummary, renderChart };
}
