# Changelog

All notable changes to the βyblos ePortfolio plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.1] - 2026-06-09

Security patch closing a stored cross-site scripting hole in shared and public
pages.

### Security
- Fixed a stored XSS vulnerability (CWE-79). Text, text-and-image, quote and
  YouTube section bodies are stored as raw editor HTML and were written to the
  page without sanitisation, so a page author could embed an active payload (for
  example `<img src=x onerror=...>`) that ran in the browser of any teacher,
  reviewer or anonymous visitor who opened the shared or published page. All four
  body sinks, across both the live renderer and the editor preview, now pass
  through HTMLPurifier via a shared `section_helpers::clean_body()` helper, the
  same defence already used by the custom-HTML and reflection sections. Link
  targets in quote and citation sections are now validated with
  `clean_param(PARAM_URL)`, which strips `javascript:` and other dangerous URL
  schemes. Legitimate formatting (bold, lists, links, inline styles) is
  preserved.

## [1.2.0] - 2026-06-07

Artefacts gain real media types, and the codebase is hardened for general release
against the Moodle plugin-contribution checklist.

### Added

#### Artefacts: type-aware form and new media types
- The create/edit form is now a native Moodle form that adapts to the chosen
  type, replacing the fixed text-only form. New types alongside Text: **Image**
  and **File** (uploaded via the file picker), **Link** (a URL bookmark),
  **Audio** and **Video** (recorded in-browser with the satsrecorder, or
  uploaded), and **Embed** (a YouTube/Vimeo address played inline via Moodle's
  media manager). Each type renders correctly on the artefact view.
- New renderers (`audio`, `video`, `link`, `embed`), a `creatable_types()`
  registry that excludes the auto-imported types (badge, course completion,
  blog), and a dedicated `artefact` file area with an owner/manager/viewshared
  access gate in `local_byblos_pluginfile`.

### Fixed
- Artefacts: selecting any non-text type previously left the form unchanged and
  saved no real content (no file upload, no URL field). Also fixed the
  `type`/`artefacttype` column mismatch (saving an edit errored and the selector
  never preselected on edit) and a missing `artefacttype_image` string.

### Changed (pre-release compliance hardening)
- Replaced inline JavaScript (`onsubmit="return confirm(...)"` on
  delete/publish/revoke forms, plus a clipboard `onclick`) with data attributes
  wired by AMD modules: a new `local_byblos/confirm` module shows a confirm modal
  for any `form[data-byblos-confirm]`, and `local_byblos/share` handles the
  copy-link button. This follows Moodle's Output API and AMD guidance.
- Frankenstyle: renamed the global helper `byblos_upload_error` to
  `local_byblos_upload_error` so every plugin function is correctly prefixed.

### Notes
- Repository naming: the recommended pattern is `moodle-local_byblos` (underscore
  between type and name). The current repository is `moodle-local-byblos`; a
  rename is advisory and left to the maintainer.

## [1.1.0] - 2026-06-07

A pedagogy-driven release: four new tools turn the documented ePortfolio theory
into product — scaffolded multimodal **Reflection**, an **Outcome map** for
constructive alignment, a first-class **Goals** store with a dashboard tab, and
owner-moderated **page feedback** — alongside the new in-plugin documentation hub.
Adds three database tables and a column, two capabilities, four events, and full
privacy coverage (schema version `2026060702`).

### Added

#### Pedagogy-driven widgets (reflection, alignment, goals, feedback)
- **Reflection section** — scaffolded reflective writing with a selectable
  framework (*What? So what? Now what?*, Gibbs' cycle, DEAL, Kolb) that seeds the
  prompts, and **multimodal capture**: the writing field is a Moodle TinyMCE
  editor with the `tiny_satsrecorder` plugin, so students can record audio, video
  or screen captures inline. Body is sanitised through `format_text` (HTMLPurifier
  passes `<audio>`/`<video>`), media stored in a new `reflection` file area with a
  `local_byblos_pluginfile` access gate; edited via a `core/fragment` modal +
  `save_reflection` web service.
- **Outcome map section** — list programme outcomes / rubric criteria and map
  evidence (artefacts or pages) to each with a note (constructive alignment).
  Freeform by default, with optional one-click import from a core competency
  framework when site competencies are enabled (degrades silently when off).
- **Goals** — a first-class, dashboard-managed store (`local_byblos_goal` +
  `local_byblos_goal_link`): set goals, track 0–100% progress, mark
  active/achieved/archived, link evidence as it accrues. New **Goals** dashboard
  tab and a **Goals** section that surfaces a page owner's goals with progress
  bars. New capability `local/byblos:managegoals`; `goal_*` events; full privacy.
  The Goals tab also offers six **quick-start templates** (scaffolded starter
  goals, each grounded in a high-impact practice) that a learner can add in one
  click and then personalise.
- **Page feedback** — owner-moderated, logged-in feedback on a shared page with a
  per-page scope switch (**Off / Teachers only / Cohort**); no anonymous/public
  feedback (the public-token path renders no feedback UI and the externals require
  the system context). New table `local_byblos_pagefeedback`, capability
  `local/byblos:leavefeedback`, `page_feedback_left` event, full privacy.
- Schema: adds `local_byblos_goal`, `local_byblos_goal_link`,
  `local_byblos_pagefeedback` and a `feedback` column on `local_byblos_page`
  (upgrade savepoints `2026060701`, `2026060702`; version `2026060702`, release
  `1.1.0`). Docs hub updated (Building pages catalogue, Getting started Goals tab,
  Sharing feedback section).

#### In-plugin documentation hub
- New self-service help site at `/local/byblos/docs.php`, reachable from a
  "Help & guides" button on the portfolio dashboard. Two-pane layout (sidebar
  of topics + content) with prev/next navigation.
- Six how-to guides — Getting started, Building pages, Publishing, Collections,
  Sharing, Submitting for assessment — and three end-to-end use-case
  walkthroughs: programme-level exit portfolio, course summative assessment,
  professional/skills showcase. The assessment guide and use cases carry
  "For students" / "For teachers" role badges.
- Content is anchored to the real UI: button labels are pulled from the live
  lang strings (e.g. Publish, Share) so the docs stay in step with the
  interface. UI figures are labelled screenshot placeholders describing the
  intended image; drop real images into `pix/docs/` and swap the placeholder.
- Concept figures ship as scalable inline-SVG diagrams (no image asset needed):
  the "Why ePortfolios work" page renders the Collect → Curate → Connect → Share
  cycle with Reflection at its hub as a self-contained, theme-styled SVG.
- `\local_byblos\docs` topic registry (single source of truth for the sidebar,
  validation, prev/next and cross-links); `docs.mustache` shell + content
  partials under `templates/docs/`; `docs_*` lang strings; `.byblos-docs-*`
  styles (step lists, callouts, role badges, card grids, screenshot figures).
- A "Concepts & best practice" group in the docs hub with two pages:
  **Why ePortfolios work** (the pedagogy behind ePortfolios — reflection and
  metacognition, integration of learning, portfolio "for" vs "of" learning,
  learner agency, and the ePortfolio as a high-impact practice — with an
  accurate reference list drawn from Yancey, Barrett, Kuh & O'Donnell, Watson
  et al., Eynon & Gambino, Smith & Tillema, Wolf et al., Kolb, Schön and Dewey),
  and **Best practices** (ten high-level practices for building a strong
  portfolio, each tied back to the pedagogy and to the relevant Byblos feature).

## [1.0.1] - 2026-04-21

### Added

#### Announcement turnstile
- `go.php` endpoint that authenticates a student against a course, fires a per-student `answer_opened` log event in the course context, then redirects to a Byblos portfolio page. Destination is resolved server-side from an integer page id; the resolved URL is asserted to live under `$CFG->wwwroot`, and visibility is enforced via `share::can_view_page()` (same gate as the canonical page viewer). No request parameter can produce an off-site redirect.
- `\local_byblos\event\answer_opened` (CRUD `r`, LEVEL_PARTICIPATING, no `objecttable`, page id carried in `other`).
- "Get announcement link" picker on the page view, gated on `moodle/course:update` in at least one of the viewer's courses. Popover lists postable courses, builds the turnstile URL live, and offers copy-to-clipboard.
- New external WS `local_byblos_list_postable_courses`.

#### Chart widget — major feature expansion
- **Axis labels** (X / Y) for bar and line charts.
- **Unit suffix** appended to every value display and to the y-axis grid ticks (`%`, `hrs`, `$`, …).
- **Caption / source footer** rendered as italic small print below the chart.
- **Show / hide value labels** toggle.
- **Multiple series** — comma-separated series names; each data row gains N value inputs; bar charts render as grouped pairs, line charts as multiple polylines, both with an inline legend. Pie/donut ignore series (slices always derive from the first series).
- **Per-item colour override** picker on each row.
- **Bar orientation** toggle — horizontal (existing) or vertical columns.
- **Sort order** — as entered / largest first / smallest first.
- **Y-axis grid** with four tick lines + value labels.
- All new options are additive; existing single-series `{label, value}` charts render unchanged.

#### Chart editor — redesigned UI
- Two-pane layout: tabbed form on the left, **live preview** pane on the right that re-renders via the new `local_byblos_render_chart_preview` WS (debounced 220 ms).
- Visual chart-type picker — four SVG-icon tiles (bar / line / pie / donut) replacing the dropdown.
- Tabs: **Data / Appearance / Labels / Advanced**; opens on Data.
- Data rows become a proper table with column headers and **drag-to-reorder** handles.
- "Base Colour" renamed to **Palette colour** with inline help explaining its three remaining roles.

#### Gallery editor — redesigned UI
- Visual thumbnail grid that mirrors the published card layout (column-count tiles drive the grid template).
- Per-tile hover overlay with title caption and a quick-remove `✕`.
- Trailing **+ Add** tile that spawns a fresh data tile and opens the file picker immediately.
- Inline detail panel below the grid for editing title / description / image of the selected tile (writes back to the tile's hidden inputs in real time, refreshing the thumbnail).
- Drag-to-reorder data tiles; the add tile re-pins to the end after each drop.

#### Skills widget — named proficiency levels
- Replaced the meaningless 0–100 percentage with a 1–5 named scale: **Novice / Beginner / Intermediate / Proficient / Expert**. Bar fills proportionally (1=20% … 5=100%).
- Editor input format swapped from `Name:0-100` to `Name:1-5` with the level legend inline in the field hint.
- Renderer clamps legacy values >5 to Expert so old pages still render a recognisable bar.
- Template seed data converted to the new scale.

#### Custom HTML section — security and editor overhaul
- Server-side sanitisation: both the public and editor-preview renderers now pipe content through `format_text(…, FORMAT_HTML, ['noclean' => false])`. HTMLPurifier strips `<script>`, all `on*` event handlers, `javascript:` URLs, `<iframe>`/`<object>`/`<embed>`, `<meta http-equiv="refresh">`, and tightens inline `style`.
- New capability `local/byblos:editcustomhtml` (default: editingteacher + manager, with `RISK_XSS`). The custom-HTML tile is hidden from the section picker for users without it, and the `add_section` WS rejects the type at the API boundary.
- Edit field switched from a rich-text editor to a dark code editor with live HTML syntax highlighting (in-house regex tokeniser — no new dependencies). Tab inserts two spaces; horizontal scroll is preserved between the textarea and the highlighted overlay.
- Visible "HTML is sanitised on save" notice above the field listing what gets stripped.

#### Stats section
- Default placeholder values rewritten to be unmistakably placeholder (number `0`, label "Replace with your own count"). Removes the risk of pretend-real `98% Satisfaction`-style filler landing on a published page.

### Changed
- Public-share view (`publicview.php`) renders sections through `renderer::render_section()` instead of feeding the raw `content` column to the template, so shared pages now show the actual hero / text / gallery / chart instead of empty `<div class="byblos-section">` stubs.
- README updated to reflect the announcement-turnstile flow and the redesigned chart / gallery / skills / custom-HTML editors.

### Fixed
- Inline page title editor was eating space characters: the keydown handler on the heading element was `preventDefault`-ing on every Enter/Space event, including those that bubbled up from the inline `<input>`. Now guarded so the handler only fires when the heading itself is the event target.
- `moodle/course:announce` capability used by the announcement-link picker doesn't exist in Moodle core; replaced with `moodle/course:update` so the trigger actually appears for editing teachers / managers.
- `publicview.php` footer rendered `[[pluginfullname]]` (missing lang key); switched to the existing `pluginname` key.

### Security
- Documented threat model for the custom-HTML section in code comments (stored XSS via `<script>`, event handlers, `javascript:` URLs, `data:` SVG payloads, `<iframe>` phishing, `<form>` action hijacking, meta-refresh redirects, CSS-based phishing / keylogging). Mitigations land at sanitisation (HTMLPurifier) + capability gate (`local/byblos:editcustomhtml`).

## [1.0.0] - 2026-04-16

### Added

#### Core
- Plugin scaffold for Moodle 5.0+ (PHP 8.1+)
- 8 database tables with XMLDB schema (`local_byblos_artefact`, `_page`, `_section`, `_collection`, `_collection_page`, `_share`, `_page_course`, `_submission`)
- 7 capabilities with role-based defaults (`use`, `createpage`, `share`, `sharepublic`, `viewshared`, `managetemplates`, `manageall`)
- 9 admin settings (enable, default theme/layout, sharing, limits, auto-import, completion, PDF export)
- ~340 English language strings
- Full Moodle coding standards compliance

#### Artefacts
- 6 artefact types: text, file, image, course completion, badge, blog entry
- Artefact CRUD with type registry
- Auto-import of issued badges from `badge_issued` table
- Auto-import of course completions from `course_completions` table
- Deduplication via `sourceref` field

#### Page Builder
- 8 pre-designed page templates: Personal Portfolio, Academic CV, Project Showcase, Creative Work, Learning Journey, Professional Profile, Research Portfolio, Simple Page
- 12 section types: hero, text, text + image, gallery, skills, timeline, badges, completions, social links, call-to-action, divider, custom HTML
- 6 layouts: single column, two equal, wide-left, wide-right, three-column, hero + two-column
- 6 visual themes: Clean, Academic, Modern Dark, Creative, Corporate, Streaming
- Wix/Squarespace-style inline section editor (AJAX-driven, no page reloads)
- AMD JavaScript modules (`editor.js`, `editor_inline.js`) with Moodle external function integration
- Contenteditable inline editing for text sections with floating formatting toolbar
- Section reorder via up/down controls
- Theme picker with live preview
- Section type picker modal (12 types with icons and descriptions)

#### Image Upload
- Drag-and-drop and file browse upload
- Moodle native file API integration (`file_storage`, `stored_file`, `pluginfile.php`)
- Image MIME type validation (JPEG, PNG, GIF, WebP, SVG)
- 10MB file size limit
- AMD upload widget module (`upload.js`)
- Upload widgets in hero, text + image, and gallery section editors

#### Sharing
- Share pages/collections with specific users
- Share with course participants (all enrolled users)
- Share with group members
- Public token-based sharing (64-character hex token, gated by `sharepublic` capability)
- Share management page with add/remove controls
- "Shared with me" page listing received shares
- Public view page (no authentication required)

#### Course Integration
- Pages can be tagged with one or more courses
- Course Portfolios page shows all student pages for a course
- Teachers with `viewshared` capability see all tagged pages
- Students see their own pages + pages shared with them

#### Collections
- Group related pages into ordered collections
- Add/remove/reorder pages within collections
- Collection view with page navigation

#### Navigation
- "βyblos" link in user dropdown menu (all authenticated users)
- "Course Portfolios" link in course navigation (for enrolled users)
- Profile page link via `myprofile_navigation`

#### Events & Completion
- 5 Moodle events: `page_created`, `page_viewed`, `page_shared`, `artefact_created`, `portfolio_exported`
- Course completion integration: "created N portfolio pages" as completion condition
- Event observer on `page_created` triggers completion check

#### Privacy (GDPR)
- Full Privacy API implementation across all 8 database tables
- `get_metadata()` declares all stored personal data fields
- `export_user_data()` exports artefacts, pages, sections, collections, shares, submissions
- `delete_data_for_user()` cascading delete across all related records
- `get_contexts_for_userid()` for context discovery

#### Themes (CSS)
- 936-line `styles.css` with all 6 themes scoped to `.byblos-theme-{key}`
- Streaming theme: dark background (#0d0d0d), teal accent (#00d4aa), hover-zoom effects, cinematic typography
- Section-type-specific styles (hero banners, timeline tracks, skills bars, gallery grids)
- Editor styles (toolbars, drop zones, inline edit highlights)
- `!important` on key properties for Bootstrap 4 cascade compatibility

### Not Yet Implemented (Roadmap)
- Assignment submission integration (`assignsubmission_byblos`)
- PDF export
- Additional page templates
- Dashboard block
- Rubric-based portfolio assessment

[1.2.0]: https://github.com/sats/moodle-local_byblos/releases/tag/v1.2.0
[1.1.0]: https://github.com/sats/moodle-local_byblos/releases/tag/v1.1.0
[1.0.0]: https://github.com/sats/moodle-local_byblos/releases/tag/v1.0.0
