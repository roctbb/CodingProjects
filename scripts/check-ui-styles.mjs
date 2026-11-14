import { readFileSync, readdirSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const styles = path.join(root, "resources/assets/sass");
// Vendor, foundation and existing screen styles have explicit migration boundaries.
const colorOwners = new Set([
  "_vendor.scss",
  "_tokens.scss",
  "_legacy.scss",
  "notebook.scss",
  // Purchased avatar decorations are artwork, independent of interface branding.
  "components/_avatar-decoration.scss",
]);
const failures = [];
const retiredLayouts = /\.(?:course-chapter-switcher(?:[-_]|\b)|course-lesson-card(?:[-_]|\b)|pending-review-card(?:[-_]|\b))/;

// Migrated primitives have one owner; legacy must not reintroduce another cascade.
const legacy = readFileSync(path.join(styles, "_legacy.scss"), "utf8");
legacy.split("\n").forEach((line, index) => {
  if (/^\.(?:profile-avatar-frame|gc-avatar-frame|profile-custom-title)(?![\w-])/.test(line)) {
    failures.push(`_legacy.scss:${index + 1}: avatar decorations belong in their shared component`);
  }
  if (/\.(?:markdown(?:-big)?|perform)(?![\w-])|\.step-media-|\.gc-multiselect(?:__|--|(?=[^\w-]|$))|\.solution-feedback__content\b/.test(line)) {
    failures.push(`_legacy.scss:${index + 1}: reading and selection controls belong in shared components`);
  }
  if (/^\s*\.(?:gc-layout|gc-main|gc-content|gc-backdrop|form-control(?:-sm)?|form-select(?:-sm)?|form-label|form-text|table|btn-options|gc-section-header(?:--[\w-]+)?|modal-(?:content|header|body|footer))(?=[\s,:.{])/.test(line)
      || /^\s*\[data-bs-theme="(?:dark|light)"\]\s+\.modal-(?:content|header|body|footer)(?=[\s,:.{])/.test(line)) {
    failures.push(`_legacy.scss:${index + 1}: migrated primitive belongs in base or components`);
  }
});

function styleFiles(directory, prefix = "") {
  return readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
    const name = prefix + entry.name;
    return entry.isDirectory() ? styleFiles(path.join(directory, entry.name), name + "/")
      : entry.name.endsWith(".scss") ? [name] : [];
  });
}

for (const file of styleFiles(styles)) {
  const source = readFileSync(path.join(styles, file), "utf8");
  source.split("\n").forEach((line, index) => {
    if (retiredLayouts.test(line)) {
      failures.push(`${file}:${index + 1}: retired layout has no template; use the shared component`);
    }
    if (!colorOwners.has(file) && /#(?:[a-f\d]{3,8})\b|\b(?:rgba?|hsla?|oklch|lab)\s*\(/i.test(line)) {
      failures.push(`${file}:${index + 1}: use a semantic color token`);
    }
  });
}

const brand = readFileSync(
  path.join(root, "resources/views/layouts/partials/brand-theme.blade.php"),
  "utf8",
);
const inlineStyles =
  brand.match(/<style\b[^>]*>([\s\S]*?)<\/style>/)?.[1] ?? "";
const unexpected = inlineStyles
  .replace(/^\s*--[\w-]+:.*;\s*$/gm, "")
  .replace(/:root\s*\{|\[data-bs-theme="dark"\]\s*\{|\}/g, "")
  .trim();
if (unexpected || !inlineStyles) {
  failures.push(
    "brand-theme.blade.php: publish only palette variables, keep component rules in Sass",
  );
}

if (failures.length) {
  console.error(failures.join("\n"));
  process.exitCode = 1;
} else {
  console.log(
    "UI style boundaries passed: components use tokens; runtime branding contains values only.",
  );
}
