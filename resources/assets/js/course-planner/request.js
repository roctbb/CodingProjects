export function createPlannerRequest(api) {
  return async function request(path, options = {}) {
    const response = await fetch(api + path, {
      credentials: "same-origin",
      ...options,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": document.querySelector("meta[name=csrf-token]").content,
        ...options.headers,
      },
    });
    const body = await response
      .json()
      .catch(() => ({ message: "Не удалось прочитать ответ сервера." }));
    if (!response.ok) {
      const error = new Error(
        Object.values(body.errors || {})
          .flat()
          .join(" ") ||
          body.message ||
          "Не удалось сохранить изменение.",
      );
      error.status = response.status;
      error.code = body.code;
      throw error;
    }
    return body;
  };
}
