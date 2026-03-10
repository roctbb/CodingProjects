# Vendor Libraries

Локальные копии библиотек, ранее загружаемых с CDN.

## Библиотеки:

- **bootstrap** (4.3.1) - CSS фреймворк
- **easymde** - Markdown редактор
- **highlight** (9.12.0) - Подсветка синтаксиса
- **plotly** (1.58.5) - Графики и визуализация
- **prism** (1.5.1) - Подсветка кода
- **marked** (0.3.6) - Markdown парсер

## Обновление:

Для обновления библиотек скачайте новые версии с официальных CDN:
- Bootstrap: https://stackpath.bootstrapcdn.com/bootstrap/
- EasyMDE: https://cdn.jsdelivr.net/npm/easymde/
- Highlight.js: https://cdn.jsdelivr.net/gh/highlightjs/cdn-release/
- Plotly: https://cdn.plot.ly/
- Prism: https://cdnjs.cloudflare.com/ajax/libs/prism/
- Marked: https://cdnjs.cloudflare.com/ajax/libs/marked/

## Исключения:

Следующие библиотеки остаются на CDN:
- **MathJax** - большой размер (~10MB), редко используется
- **Google Fonts** - динамическая загрузка шрифтов
- **Monaco Editor** (games/ide.blade.php) - используется только в одном месте
