# DESIGN.md — The Harmonic System

Dial: ENERGY 2 / RHYTHM 3 / MOTION 2

## Intent

- **Who**: a team leader opening the app on a laptop on Monday morning before the Weekly Meeting; a team member entering this week's numbers on a phone in two minutes.
- **What they must do**: see what is off track, run the meeting, decide who owns what, close problems.
- **How it should feel**: a conductor's score. Calm, exact, every part knowing when it comes in. Comfortable to look at every week for years, not impressive once.
- **Finish level**: the care of a first-party app from a large design team. That comes from precision, not from borrowing anyone's look: every width, gap, and state is decided; feedback is immediate; motion is small and physical; nothing shifts, clips, or waits without saying so.

## Identity

The world of the product is the printed orchestral score: paper, engraving ink, staff lines, bar lines, the brass section, the patina on old brass, the oxblood seats of a concert hall. The metaphor lives in structure and language, never in decoration.

**Signature**: the Metrics Dashboard is drawn like a score. Each week is a measure separated by a thin bar line, the current week is marked in brass, and a double bar line closes the quarter. These lines are the column dividers the grid needs anyway; they are given meaning, not added.

**Rejected defaults**

1. Blue or purple SaaS accent, gradients, glows, glass.
2. Dashboards built from rows of identical KPI cards with soft shadows.
3. Music clip art: notes, clefs, piano keys, staff-line backgrounds, sheet-music textures.
4. Template chrome: all-caps eyebrow labels, meta strings joined with middle dots, arrows appended to buttons, monospace for small labels, pill-shaped everything.

A known risk: warm paper plus a serif title is a common generated look. It stays because it comes from the score, and it is held in check by three rules: the serif appears only in page titles, the paper is never a decorative hero surface, and the product's character is carried by the score grid and the structure, not by the background.

## Color tokens

One action color (brass). Two status colors (verdigris, oxblood), used only for status. Everything else is paper, ink, and lines.

```css
@theme {
  --color-paper: #F3EFE6;          /* page background, one step below content */
  --color-surface: #FFFDF9;        /* cards, boxes, dialogs, kanban cards, inputs */
  --color-field: #EFEADF;          /* read-only and inset areas */
  --color-hover: #ECE7DB;          /* row hover, segmented track */
  --color-sidebar: #1C1B19;        /* the dark concert hall: sidebar and phone menu */

  --color-ink: #1D2027;            /* primary text */
  --color-ink-secondary: #5A5D64;  /* labels, descriptions */
  --color-ink-muted: #65686E;      /* placeholders, timestamps */

  --color-line: #E3DCCB;           /* staff line: default hairline */
  --color-bar: #CFC6B2;            /* bar line: grid column dividers, input borders */
  --color-line-strong: #A79E89;    /* focused input border, strong divider */

  --color-brass: #7A5718;          /* the only action color */
  --color-brass-hover: #654712;
  --color-brass-subtle: #F1E8D6;   /* current-week column, selected tab background */
  --color-on-brass: #FAF7F0;

  --color-on-track: #2F6F62;       /* verdigris */
  --color-on-track-subtle: #E3EFEC;
  --color-off-track: #8E2F2F;      /* oxblood: off track, errors, destructive */
  --color-off-track-subtle: #F6E6E4;

  --font-title: "Instrument Serif", Georgia, "Times New Roman", serif;
  --font-sans: "Instrument Sans", system-ui, -apple-system, "Segoe UI", sans-serif;

  --radius-control: 4px;
  --radius-panel: 8px;
}

@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) { /* same values as [data-theme="dark"] below */ }
}

:root[data-theme="dark"] {
  --color-paper: #15171C;          /* the dark concert hall */
  --color-surface: #1C1F25;
  --color-field: #23262E;
  --color-hover: #262A32;
  --color-ink: #ECE6D8;            /* ivory */
  --color-ink-secondary: #A8A49A;
  --color-ink-muted: #959188;
  --color-line: #2E323A;
  --color-bar: #3A3F49;
  --color-line-strong: #5A5E68;
  --color-brass: #D1A85A;
  --color-brass-hover: #DDB86F;
  --color-brass-subtle: #2E2818;
  --color-on-brass: #15171C;
  --color-on-track: #6FB3A3;
  --color-on-track-subtle: #1A2B27;
  --color-off-track: #E08A80;
  --color-off-track-subtle: #2F1D1C;
}
```

Implement the dark block once with both selectors so the values are not duplicated. Theme choice (System, Light, Dark) lives in the profile and sets `data-theme` on `<html>`.

**Checked contrast (WCAG 2.2 AA)**

| Pair | Light | Dark |
| --- | --- | --- |
| ink on paper | 15.2 | 14.4 |
| ink-secondary on paper | 6.2 | 7.2 |
| ink-muted on field | 4.9 | about 4.7 |
| brass on paper | 6.1 | 8.1 |
| on-brass on brass (buttons) | 6.1 | 8.1 |
| on-track on paper | 5.5 | 7.4 |
| off-track on paper | 7.6 | 6.9 |
| brass on brass-subtle | 5.4 | — |

Status never relies on color alone: off track also shows a small filled-triangle icon and the accessible label "Off track"; missing shows a dashed outline and the label "Not entered".

## Typography

Load from Google Fonts: Instrument Serif (400) and Instrument Sans (400, 500, 600). Numbers everywhere use `font-variant-numeric: tabular-nums`.

| Token | Font | Size / line height | Weight | Use |
| --- | --- | --- | --- | --- |
| `title` | Instrument Serif | 44/48 (34/38 under 768 px), tracking -0.01em | 400 | Page titles only, one per page |
| `heading` | Instrument Sans | 20/28 | 600 | Section headings inside a page |
| `figure` | Instrument Sans | 34/38, tracking -0.02em | 500 | A few real counts read at a glance (Home) |
| `subheading` | Instrument Sans | 15/22 | 600 | Panel titles, table group headers |
| `body` | Instrument Sans | 15/23 | 400 | Default text, forms |
| `small` | Instrument Sans | 13/19 | 400 | Table cells, helper text |
| `label` | Instrument Sans | 13/18 | 500 | Field labels, column headers, nav items |
| `caption` | Instrument Sans | 12/16 | 400 | Timestamps, hints |

Rules: sentence case everywhere, no uppercase labels, no letter-spaced eyebrows. Emphasis inside text uses 500, never bold. Body text lines stay under 75 characters (`max-width: 68ch` on prose).

## Space, shape, depth

- Spacing scale on a 4 px base: 4, 8, 12, 16, 24, 32, 48. Inside components 8–16; between groups 24; between page regions 32–48.
- Radius: 8 px on buttons, inputs, nav items, and segment buttons; 16 px on cards, boxes, grouped lists, and tables; 12 px on banners, side panels, and dialogs; 4 px on badges and kanban cards; full circle only for avatars. Nothing is pill-shaped.
- The page (`paper` `#F3EFE6`) sits one step below the content. Content lives on `surface` cards: `.card` for a titled section (header row with the title, hairline under it, lists inside run edge to edge), `.box` for a padded block (forms on reading pages, the fact column on detail pages). Lists and tables inside a card or box lose their own group edge.
- Grouped surfaces: lists (`.rows`) and tables sit on one `surface` group with 16 px radius and the group edge (`0 1px 2px` plus a 1 px `line` ring), rows separated by hairlines inside. This is the single resting elevation; it says "these belong together".
- Popovers, dropdowns, and dialogs float with one shadow: `0 8px 24px rgb(29 32 39 / 0.12)`.
- No gradients, glass, glow, textures, or background patterns.

## Layout

- Desktop: left sidebar 240 px on `paper` with a hairline right border. The content column is centered in the space right of the sidebar, padding 40 px, at one of three measures:
  - `reading` 720 px: forms, settings, profile, the Fundamentals, a procedure's steps.
  - `standard` 1040 px: lists, Home, and detail pages. The default.
  - `wide` full width: the metric grid, kanban, Winning Formation, calendar, the meeting runner, matrices.
- Detail pages (a task, problem, mission, procedure, metric) use two columns at 1024 px and up: the content, and a 300 px fact column (`dl` rows on hairlines) that stays in view while the content scrolls. Under 1024 px the facts come first, then the content.
- Home uses two columns at 1024 px and up: what needs action (tasks, metrics, setup) on the left, time (today, meetings) in a 320 px column on the right.
- Page header: serif title, one sentence of description in `ink-secondary` below it, primary action aligned right on the title's baseline. At most one brass button per view.
- Under 768 px: sidebar becomes the bottom bar (Home, Tasks, Calendar, My Numbers, Menu); page padding 16 px; side panels open full screen.
- Lists are rows inside one grouped surface, not separate cards. Cards appear only where an object is physically moved (kanban) or stands alone (event panel).

## Components

**Buttons.** Primary: brass fill, `on-brass` text, 4 px radius, 40 px tall (44 px on touch). Secondary: transparent with a 1 px `bar` border, ink text. Ghost: text only, `hover` background. Destructive: secondary style with off-track text. Labels are verbs: "Save changes", "Start meeting". No icons-only buttons without `aria-label`; no arrows in labels.

**Inputs.** `surface` background, 1 px `bar` border, 8 px radius; hover border `line-strong`; focus border `line-strong` with a soft 3 px brass halo (16% brass) and the focus ring. The `field` token stays for read-only and inset areas only, so a page of forms reads light, not filled. Label above, helper or error text below in `small`. Errors: off-track border and text, focus moves to the first error.

**Focus ring.** `outline: 2px solid var(--color-brass); outline-offset: 2px` on `:focus-visible` for every interactive element.

**Tables.** No zebra striping. Rows separated by `line`; header row in `label` style on `paper`; row hover `hover`. Numeric columns right-aligned. Sticky first column on horizontal scroll.

**Metric grid (signature).** Week columns divided by 1 px `bar` vertical lines. The current week: header in brass at 600, column background `brass-subtle`. Quarter end: a double bar line (`border-left: 3px double var(--color-ink)`) after W13. Cell values: on-track or off-track color, 500 weight for the current week; missing cells show a 1 px dashed off-track outline and the label "Not entered" for screen readers. A small dot after a value marks a note. Inline editing replaces the cell text with an input of the same width, so nothing shifts.

**Status.** On track: verdigris text and a small check icon. Off track: oxblood text and a small triangle icon. Pairs with `-subtle` backgrounds only for banners and badges.

**Navigation.** Daily places sit at the top with icons, in the same order as the phone bar: Home, Tasks, Calendar, My Numbers stays inside Measurements. Then the six sections, each with its two instruments (Team also lists Teams), then Assessments (Diagnostic, 360), then Admin for Org Admins. Section groups are plain `label` text in `ink-muted` (sentence case, no uppercase). The active item follows the Sidebar rule below. The list scrolls inside the sidebar with a 24 px fade at the bottom edge; the account menu stays pinned below it.

**Tabs.** View switches (Mine / Team / All, Active / Departed) are a segmented control: a `hover` track with 3 px padding, the current segment on `surface` with a 1 px shadow. The meeting runner keeps its underlined segment rail.

**Sidebar.** The dark concert hall in both themes (`sidebar` `#1C1B19`, in dark theme `#0E0F12`). Its color tokens are re-declared on `.sidebar` (ivory ink, warm lines, lighter brass `#D6AE60`) so the org switcher, account menu, and their dropdowns read on it without their own styles. Every item has a 16 px Lucide icon chosen for its meaning (Formation is a network, Problems an alert, Solve a puzzle). Items are 8 px blocks; the current one is a lifted block (`#34312A`) with a 3 px brass edge and a brass icon. No staff lines in the sidebar: the score grid carries the identity.

**Collapsing.** A button at the top of the sidebar folds it into a 64 px rail of icons (labels stay for screen readers, a tooltip names each icon, hairlines separate the groups); the content column widens with it. Each section group is a `details` that starts closed and opens from its name, with a chevron that turns; the group holding the current page is always open, so the sidebar shows the daily places, the section names, and only the instruments around where you are. Both choices are remembered in two plain cookies (`nav_rail`, `nav_open`) that the server reads, so pages render already folded with no flash; their values are checked against the known group names. The width animates in 150 ms only while the person toggles it, never on load or resize.

**At a glance (Home).** One grouped strip of real counts (tasks due, numbers to enter, team metrics off track or missing, meetings coming up), each a link to its list, separated by hairlines. Zero shows in `ink-muted`. One strip, not a grid of cards.

**Side panel and dialog.** `surface` background, 8 px radius on the free edges, the one floating shadow. Panel width 440 px on desktop. Dialogs max 520 px, title in `heading`, actions right-aligned with the primary last.

**Kanban.** Columns are `paper` with a `line` hairline between them, titled in `label` with a count. Cards on `surface`, 1 px `line` border, 4 px radius, 12 px padding: title, then a row with the Responsible's initials, due date, and step progress "2/5". Dragged card lifts with the floating shadow; drop targets show a 2 px brass line where the card will land.

**Meeting runner.** Segment rail across the top: seven segments as plain text, the current one in brass with a brass underline, completed ones in `ink-secondary`. The countdown sits right of the title in `heading` with tabular numbers; overtime turns oxblood with a leading "+". Under an hour it reads `4:05`; from an hour on it reads `1 h 12 m` so it never grows into a long number.

**I-S.O.L.V.E stepper.** A numbered vertical list of the six steps on the left (a real sequence, so numbers are earned); the current step in brass; completed steps show a check.

**Charts.** One Blade component, `<x-line-chart>`, that renders SVG on the server; no chart library and no JavaScript. Series lines in ink at 1.5 px; goal as a dashed `line-strong` line, or a `brass-subtle` band for between-goals; points colored by status. Axes in `caption`, gridlines in `line`. Every chart has a text summary for screen readers.

**Avatars.** 28 px circle with initials on `hover` background in `ink`. No photos in v1.

**Empty states.** A heading naming the space ("Start your Problem Queue"), one sentence on what goes here, one button. No illustrations.

**Loading.** Skeleton rows in the final layout using the `hover` color, no shimmer animation.

**Saved state.** Autosaving fields confirm in place: a 14 px check and "Saved" in `ink-secondary` next to the field for 2 s; a failure stays until the next attempt and says what to do.

**Meta lines.** Facts about an item sit in separate spans with 12 px between them, or in a `dl`. Never join them with middle dots.

**Toasts.** Bottom-left, `ink` background with `paper` text, 4 px radius, disappear after 3 s, never stacked more than two. Copy states the result: "Saved", "Task created".

## Icons

Lucide icons (ISC license), copied as SVG files into `resources/icons/` and rendered by `<x-icon name="check" />`; only the icons in use are copied. 1.5 px stroke, 16 px inline and 20 px in navigation, colored with `currentColor`. Decorative icons get `aria-hidden`. Never use icons as decoration in headings.

## Motion

Only in response to an action, always ease-out, never longer than 200 ms:

- Hover and focus changes on buttons, rows, and nav items transition color and background in 120 ms.
- Buttons press: `scale(0.98)` while held, 80 ms.
- Menus and folded forms (`details`) fade and move 4 px in 120 ms when they open.
- Panels fade and move 8 px in 150 ms; dialogs fade and scale from 0.97 in 150 ms; the kanban drop settles in 150 ms.
- Page to page: the browser's cross-document view transition cross-fades the content column in 120 ms; the sidebar and phone bar stay still. Browsers without it simply navigate.

Nothing animates on page load. `prefers-reduced-motion` removes all transitions.

## Copy voice

Plain, direct, specific. Sentence case, no "successfully", "please", or exclamation marks. Errors say what happened and what to do. Buttons keep the same verb through the flow: "Publish" leads to "Published". Use the instrument names from PRD section 3 exactly.

## Component checkpoint

Before writing UI code for any screen, state in the task notes: who uses this screen and what they must finish; which tokens it uses and why; which states exist (loading, empty, error, validation); how it works at 360 px; and which one element on the screen gets attention. If any answer is "it's common", choose again.
