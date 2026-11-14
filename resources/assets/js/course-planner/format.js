export const esc = (v) =>
    String(v ?? "").replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    );
export const date = (v) =>
    v
      ? new Date(v.slice(0, 10) + "T12:00:00").toLocaleDateString("ru-RU", {
          day: "numeric",
          month: "short",
        })
      : "Без даты";
export const shift = (v, n) => {
    const d = new Date(v + "T12:00:00Z");
    d.setUTCDate(d.getUTCDate() + n);
    return d.toISOString().slice(0, 10);
  };
