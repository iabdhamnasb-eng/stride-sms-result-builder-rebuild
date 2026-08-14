# Result Builder Deployment Package

Adds three features to the **current site** — no existing code is replaced,
everything here is additive:

1. **Line split & format** — split a variable into 1–10 styled lines
   (`data-lines` / `data-line-{n}-*` attributes).
2. **Move / align** — one-click Left / Center / Right / Full width on any
   official result element.
3. **Grid layout** — rows × columns layout for blocks / variables
   (`data-grid-rows` / `data-grid-row-{n}-cols`).

Files:

```
deploy/
├── 01-builder-addon.js                  MANDATORY  JS: traits + logic (paste into the view)
├── 02-trait-styles.css                  MANDATORY  CSS for the three traits
├── 03-edits/
│   ├── 01-style-manager-position-sector.txt  OPTIONAL  Styles-tab cosmetics
│   ├── 02-model-available-variables.txt      OPTIONAL  add-if-missing keys
│   ├── 03-service-build-variables.txt        OPTIONAL  add-if-missing values
│   ├── 04-service-grading-key-alias.txt      OPTIONAL  {{GRADING_KEY}} alias
│   └── 05-service-render-hook.txt            MANDATORY  print-time renderer hook
└── 04-php/
    ├── ResultVariableFormatting.php     MANDATORY  server-side renderer (copy as-is)
    └── ResultCardService.diff           MANDATORY  exact hook change (patch or manual)
```

---

## Install

### Step 1 — CSS (`02-trait-styles.css`)

Either:

- **A)** Append the whole file to `public/css/result-builder.css`
  (it is already loaded via `asset('css/result-builder.css')`), or
- **B)** Paste it inside a `<style>…</style>` in the page `<head>` of
  `resources/views/builder/result-template.blade.php` (after the existing
  stylesheet link).

### Step 2 — Builder add-on (`01-builder-addon.js`)

In `resources/views/builder/result-template.blade.php`:

1. Open the file and find the `@verbatim` / `@endverbatim` block that wraps
   the editor script (the block containing `var editor = grapesjs.init`).
2. Paste the **entire contents of `01-builder-addon.js`** inside that block,
   wrapped in its own `<script>` tag, e.g. right after the closing `</script>`
   of the editor-init script but still before `@endverbatim`.

Why `@verbatim`: the add-on contains literal `{{variable}}` strings; outside
`@verbatim` Blade would try to evaluate them.

The add-on is fully self-contained: it registers its own trait types
(`rb-lines`, `rb-position`, `rb-grid`), its own event listeners
(`component:add` / `load` / `component:selected`) and a render mirror
(`window.resultBuilderRender`). It does not touch any existing code, so it is
safe even if the page already has its own traits/listeners.

### Step 3 — PHP renderer (MANDATORY)

Follow `03-edits/05-service-render-hook.txt`:

1. Copy `04-php/ResultVariableFormatting.php` → `app/Services/ResultVariableFormatting.php`.
2. In `app/Services/ResultCardService.php`, in `renderTemplate()`, add the
   single `ResultVariableFormatting::apply($html, $vars)` call right after
   `$html = $compiledHtml;` and before the placeholder substitution loop.
   (Or apply the diff: `patch -p0 < deploy/04-php/ResultCardService.diff`.)

### Step 4 — Optional add-if-missing edits

- `03-edits/02-model-available-variables.txt` — variables dropdown keys.
- `03-edits/03-service-build-variables.txt` — values for those keys.
- `03-edits/04-service-grading-key-alias.txt` — `{{GRADING_KEY}}` alias.
- `03-edits/01-style-manager-position-sector.txt` — Styles-tab cosmetics.

Add only what is not already present.

---

## Verification

1. Load the builder page (Ctrl+Shift+R to bypass cache) → no JS console errors.
2. Drag a **Result Variable** onto the canvas, pick e.g. `{{school_name}}` →
   **Settings → Traits** shows **Line split & format**. Set Lines = 2 →
   **Preview rendered** shows the sample value split across 2 lines; style
   each line independently (color / size / bold / align / max words).
3. Select the block → **Move / align** appears; click **Center** / **Left** /
   **Full width** → the block visibly moves.
4. Select a block (e.g. School Header) → **Grid layout** shows its variables
   in labeled cells; change **Rows** and per-row **Cols** → cells redistribute
   and persist after save + reload.
5. Mixed lines like `Tel: {{school_phone}} | {{school_email}}` get **no** grid
   cell and no line-split trait (expected: multi-variable elements are
   skipped).
6. Print / PDF a template that uses the formatted variable → lines render
   with the configured styles (DomPDF-safe `display:block` spans).
7. Reload the page → all trait values persist (they live in `data-*`
   attributes saved with the template).

## Rollback

Every change is additive: delete the `<script>` block from Step 2, remove the
CSS, revert `ResultCardService.php` (or `git checkout` the two lines), and
delete `app/Services/ResultVariableFormatting.php`.

## Reference

The reference implementation is `patched/result-template.blade.php` (full
working page — `01-builder-addon.js` is extracted from it verbatim) and the
standalone test harness at `standalone/test-builder.html` (serve with
`php -S localhost:8137` from the repo root and open
`http://localhost:8137/test-builder.html`).
