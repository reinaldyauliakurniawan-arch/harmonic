# Interface system: The Harmonic System

Applied patterns on top of `DESIGN.md`. `DESIGN.md` owns tokens, type scale, and component rules; this file records how they were put together on real screens so new screens match. Tokens live in `resources/css/app.css`.

Dial: ENERGY 2 / RHYTHM 3 / MOTION 1 (from `DESIGN.md`).

## Direction

A conductor's score: paper, engraving ink, staff lines, one brass accent. Calm and exact. The person is a team leader on Monday morning or a member entering numbers on a phone; every screen answers one question and the list or grid that answers it is the focal point.

## Depth

Borders only. Hairlines (`--color-line`) separate rows and regions; `--color-bar` is the stronger line for grid columns, inputs, and the nav staff. The one shadow (`--shadow-float`) is for things that float: dialogs, popovers, the side panel. No card shadows.

## Spacing

4 px base: 4, 8, 12, 16, 24, 32, 48. Page padding 40 px desktop, 16 px phone. 32 px between page regions, 24 between groups, 8 to 16 inside components. Content max width 1120 px; grids and boards use the full width (`<x-app wide>`).

## Shell

- Desktop: no top bar. Sidebar 240 px on paper with a hairline right edge. Top: wordmark "Harmonic" (Instrument Serif 24 px) and the organization name (or the switcher when the person has several). Bottom: account menu (avatar, name) that opens upward.
- Navigation signature: each PRD section is a **staff**. Its items sit on a 1 px `--color-bar` line (`.staff`); the current item gets a 2 px brass bar on that line, ink text at 500, `--color-hover` background. Section label above in caption, ink-muted. Groups without a PRD name (Tasks/Calendar, Diagnostic/360, Teams) are unlabeled staffs.
- Phone: 52 px top bar (wordmark, org name, avatar), bottom bar (Home, Tasks, Calendar, My Numbers, Menu). Menu is a full-screen sheet that closes on Escape.

## Page anatomy

1. Optional breadcrumb in small ink-secondary (detail pages only).
2. Serif title + one sentence of description; at most one brass button, aligned right.
3. The "add" form is folded behind that one primary button (`<details>` with a `.btn-primary` summary, opens automatically when the form has errors). The list below it stays the focal point.
4. Tabs (2 px brass underline) sit on a hairline; the filter row goes directly under them.
5. The list, table, grid, or board.

## Filters

`<form class="filters" x-data="autoSubmit" @change="submit">` with `<x-filter>` per field: inline label + compact select (`.input-sm`, 32 px, 44 px on touch). They apply on change; the "Apply" button stays in the markup for no-JS and is visually hidden by `autoSubmit`. On phones the row is one horizontal swipe line so the list starts high.

## Rows and actions

- Lists are bordered rows (`.rows`), not cards. Tables use `.tbl`.
- Status is a small label (`.pill-status`, 4 px radius, never pill-shaped): neutral, `is-doing` (brass), `is-done` (verdigris), `is-off` (oxblood). Off track always also carries the filled triangle.
- More than one action on a row or card goes into a "…" menu (`<details>` + `.icon-btn` summary, `.menu-item` entries). Inside scroll containers the menu expands in place, never absolutely positioned, so it can't be clipped.
- Reorderable lists use `x-data="sortable"`: drag by the grip handle, plus up/down `.icon-btn` arrows for keyboard and touch, revealed on row hover/focus (`.row-tools`, always visible on touch). Order saves via JSON POST and reports in a `[data-sort-status]` live region.

## The score (metrics grid)

Week columns divided by `--color-bar`, current week brass on brass-subtle, double bar before the quarter roll-up. Owner-seat group rows in label weight. Cells: verdigris on, oxblood + triangle off, dashed outline only for ended weeks after the metric was created. Inline edit keeps the column width (`size=1` input); saving happens in the background while focus moves on. Notes: hover/focus a cell to reveal a small note button; a brass dot marks a saved note.

## Forms and dialogs

Labels above fields, help or error below. Dialogs are native `<dialog>` (centered, 520 px max, Escape closes). Destructive actions are secondary buttons with oxblood text and a confirmation naming the item.

## Copy

English, sentence case, no "successfully", "please", exclamation marks, emoji, or em dashes. Buttons are verbs ("Add a task", "Save note", "Move seat").
