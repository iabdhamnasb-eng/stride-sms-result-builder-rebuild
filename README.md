# STRIDE Result Builder — Canvas Style Controls Fix

## What this fixes

Non-developer users can now adjust the **appearance** of official result
elements on the canvas (move, resize, font/size/weight/colour, alignment,
spacing, padding, borders, background, hide-on-result) but **cannot** rewrite
their content, labels, or `{{variables}}`.

- Style Manager is restricted to 5 approved sectors: Size & Position,
  Typography, Spacing, Background, Border.
- Official blocks are marked `data-result-block` / `data-result-protected`
  and become non-editable but fully stylable/resizable/movable.
- New blocks: **Line Box** (1–10 lines, style/thickness/spacing) and
  **Result Variable** (pick any approved variable from a dropdown).
- Toolbar `✥ / ⧉ / 🗑` commands (`tlb-move/clone/delete`) were missing in the
  real page — now registered.
- New variables (`{{principal_name}}`, `{{head_teacher_name}}`,
  `{{next_term_start_date}}`, `{{next_term_end_date}}`, `{{result_issue_date}}`,
  `{{GRADING_KEY}}`, `{{school_stamp}}`) resolve at print time.

## New: variable line-split & per-line formatting

Any **Result Variable** element (or any element whose content is a single
`{{variable}}`, optionally wrapped in quotes like `"{{school_motto}}"`) gets a
**Line split & format** trait in the Settings panel:

- **Lines** (1–10) — how many lines the real value renders as.
- **Per line**: max words (0 = auto), font, size (px), colour, weight, align —
  every line is configured and styled independently.

The trait is attached automatically: on load, when an element is added, and
when it is selected. Mixed-content lines (e.g. `Tel: {{school_phone}} | 
{{school_email}}`) are deliberately skipped — splitting them would drop the
surrounding text at print time.

The builder only stores configuration (`data-lines`,
`data-line-{n}-words|font|size|color|weight|align`) — the real value is
**never shown in the builder** (the element keeps showing its `{{variable}}`
text). The value is split and styled at print time by `ResultVariableFormatting`
(PHP) and the preview (JS, identical logic).

**Splitting rule** (JS mirror and PHP are identical):

- Lines are filled left to right, as evenly as possible.
- Each line takes up to its own **max words** cap (0 = no cap); words left
  over flow into the last line. Words are never dropped and never split
  mid-word.

## New: block grid layout (rows × columns)

Every official block (and standalone Result Variable elements) now has a
**Grid layout** trait in the Settings panel. Selecting the block shows its
variables segmented into a rows × columns diagram:

- **Rows** are set by you (1–10, stored as `data-grid-rows`). The block's
  variables are then distributed across the rows automatically (in document
  order, as evenly as possible) — the caption shows how many variables the
  block has.
- **Each row has a Cols input** (1–12): the column count for that row is
  stored as `data-grid-row-{n}-cols` and the row's variables are split across
  its columns the same way.
- **Every cell shows what's in it** — the `{{variable}}` key(s) assigned to
  that cell, so you can see at a glance what each row/column holds.

Rows are no longer derived from the variable elements — you decide how many
segments the block is split into. All stored settings survive save/reload.

## New: move / align elements

Every official block now has a **Move / align** trait in the Settings panel
with one-click **Left / Center / Right / Full width** buttons. Table cells
(cells inside the scores table) align their content instead. The Style Manager
also gained a **Position** sector (position, top/left/right/bottom, float) —
restoring the positioning controls that existed in the original default Style
Manager. All these styles are DomPDF-safe (block display + auto margins,
float, text-align).

## Files

| Path | What it is |
|---|---|
| `patched/result-template.blade.php` | **The fix** — drop-in replacement for `resources/views/builder/result-template.blade.php` in the STRIDE repo |
| `standalone/test-builder.html` | Self-contained test harness of the patched page — no PHP needed |
| `php-patches/ResultVariableFormatting.php` | **New** server-side formatter (drop into `app/Services/`) |
| `php-patches/ResultCardService.diff` | **One-line hook** in `renderTemplate()` to run the formatter |
| `php-patches/ResultCardService.original.php` | Pristine copy of the real service (reference only) |
| `result-generation/…` | Your reference snapshot of the real STRIDE files |

> Earlier draft files (`app/…`, `resources/…`, `routes/…` at this folder's root)
> were a from-scratch reimplementation and are **superseded** by
> `patched/result-template.blade.php`. Ignore them.

## Test it now (no PHP, no Laravel needed)

1. A server is already running: **open http://localhost:8137/test-builder.html**
2. GrapesJS loads from the CDN — internet required.
3. Save is **mocked into localStorage** (banner at the top says so). Reloading
   the page restores the last saved template, exactly like the real server.
4. Use the **Reset test data** button to start clean.

### Checklist (run this in the browser)

1. Drag **School Header** onto the canvas.
2. Click the school name → right panel shows only the 5 approved sectors;
   change font size/weight/colour/alignment live.
3. Double-click the school name → **cannot edit the text** `{{school_name}}`.
4. Drag **Student Info** → labels can't be renamed; cell styles can change.
5. Add **Line Box** → Traits tab shows Number of lines / style / thickness / spacing.
6. Add **Result Variable** → pick `{{next_term_start_date}}` from the dropdown; text updates.
7. Select any block → toolbar shows ✥ ⧉ 🗑; drag and resize works.
8. Click **Save Template** → status shows "Saved ✓".
9. Reload the page → the design is restored from the mocked save.
10. Switch paper A4/Letter + Portrait/Landscape → canvas re-lays out.

### Line-split checklist

1. Add a **Result Variable** block, pick `{{school_name}}`; it shows
   `{{school_name}}` on canvas — never the real name.
2. Select it → **Settings → Traits → Line split & format** appears.
3. Set Lines = 2. Click **Preview rendered** (top-right) → the sample school
   name ("Springfield Int'l Academy") renders on 2 lines, each with the same
   styles (no per-line style set yet).
4. Set **Max words = 2 on line 1** → preview: line 1 has 2 words, line 2 the
   rest.
5. Style line 1 red/bold/16px/center; line 2 blue/11px → preview again and
   confirm each line is styled independently.
6. Set Lines = 3, leave all max-words at 0 → 3 words across 3 lines.
7. Save, reload, re-select the element → the trait still shows the saved
   values (they persist in `data-lines` / `data-line-{n}-*` attributes).
8. Pre-existing elements: drag a plain Text block, paste `{{school_name}}`
   into it, select it → the trait appears automatically. Quoted variables
   like `"{{school_motto}}"` get the trait too; a line mixing several
   variables (e.g. `Tel: {{school_phone}} | {{school_email}}`) does not.

### Grid layout checklist

1. Drag **School Header** onto the canvas; select the block → Settings →
   Traits → **Grid layout** shows the block's variables (e.g.
   `Variables: 5`) segmented into rows, one variable per row by default.
2. Set **Rows = 2** → the 5 variables redistribute: row 1 keeps the first
   few, row 2 the rest — each cell is labeled with its `{{variable}}`.
3. Set Row 1 **Cols = 2** → row 1 splits into 2 labeled columns.
4. Save, reload, re-select the block → Rows and Cols values persist.
5. Reduce Rows → column settings for the removed rows are cleaned up.
6. A mixed line like `Tel: {{school_phone}} | {{school_email}}` doesn't count
   as a variable (multi-variable elements are skipped).

### Move / align checklist

1. Select the School Header block → **Settings → Traits → Move / align**.
2. Click **Center** then **Left** → the block visibly moves on the canvas.
3. Click **Full width** → margins reset.
4. Select a cell inside the Student Info table → **Move / align** shows; use
   **Right** → the cell's text aligns right.
5. Styles tab → **Position** sector: set position Absolute + left/top offsets,
   or float Left/Right → element moves on the canvas.

## Deploying the fix to the real app

> The deploy package in `deploy/` is the recommended path: it contains only
> additive, paste-ready code for the current site (`01-builder-addon.js`,
> `02-trait-styles.css`, exact edit snippets in `03-edits/`, and the PHP
> renderer + diff in `04-php/`), with an install guide in `deploy/README.md`.
> The steps below are the manual equivalent.

1. Replace `resources/views/builder/result-template.blade.php` with
   `patched/result-template.blade.php` (identical file with the fix inside;
   `@verbatim`/Blade intact, only the real files' conventions were preserved).
2. **Model** — in `app/Models/ResultTemplate.php`, inside
   `availableVariables()` add the missing keys:

   ```php
   // School
   '{{principal_name}}'    => 'Principal Name',
   '{{head_teacher_name}}' => 'Head Teacher Name',
   '{{school_stamp}}'      => 'School Stamp / Signature Image URL',
   // Dates
   '{{next_term_start_date}}' => 'Next Term Start Date',
   '{{next_term_end_date}}'   => 'Next Term End Date',
   '{{result_issue_date}}'    => 'Result Issue Date',
   // Dynamic blocks
   '{{GRADING_KEY}}' => 'Grading Key (alias of grading scale)',
   ```

3. **Service** — in `app/Services/ResultCardService.php`:

   In `buildVariables()` (add only keys not already present):
   ```php
   $nextTermDate  = SchoolConfig::getValue($school->id, 'general', 'next_term_date', '–');
   $nextTermStart = SchoolConfig::getValue($school->id, 'general', 'next_term_start_date', $nextTermDate);
   $nextTermEnd   = SchoolConfig::getValue($school->id, 'general', 'next_term_end_date', '–');
   $resultIssue   = SchoolConfig::getValue($school->id, 'general', 'result_issue_date', now()->format('d M Y'));

   '{{principal_name}}'    => SchoolConfig::getValue($school->id, 'general', 'principal_name', ''),
   '{{head_teacher_name}}' => SchoolConfig::getValue($school->id, 'general', 'head_teacher_name', ''),
   '{{school_stamp}}'      => SchoolConfig::getValue($school->id, 'general', 'school_stamp', ''),
   '{{next_term_start_date}}' => $nextTermStart,
   '{{next_term_end_date}}'   => $nextTermEnd,
   '{{result_issue_date}}'    => $resultIssue,
   ```

   In `renderTemplate()`, replace the GRADING_SCALE line with the alias pair:
   ```php
   $gradingKey = $this->buildGradingScale($student->school_id);
   $html = str_replace('{{GRADING_SCALE}}', $gradingKey, $html);
   $html = str_replace('{{GRADING_KEY}}',   $gradingKey, $html);
   ```

   **Line-split hook (new):** copy `php-patches/ResultVariableFormatting.php`
   into `app/Services/`, then in `renderTemplate()` right after
   `$html = $compiledHtml;` add:

   ```php
   $html = \App\Services\ResultVariableFormatting::apply($html, $vars);
   ```

   (This must run **before** the `{{placeholder}}` substitution loop — it
   replaces each configured element's placeholder with its styled lines, so
   nothing is substituted twice. The exact change is in
   `php-patches/ResultCardService.diff`.)

4. Save flow, routes, controller (`save`/`upload-image`) and permissions are
   unchanged — this fix touches only the view, the variables list, and the
   render pipeline.

## DomPDF caution

The PDF engine renders from the same `compiled_html`. Keep result blocks
table-based; avoid CSS grid/flex-heavy layouts. All official blocks shipped
here are plain tables/inline styles. Formatted variable lines use
`display:block` spans, which DomPDF handles reliably.
