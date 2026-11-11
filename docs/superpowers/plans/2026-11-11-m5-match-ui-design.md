# Milestone 5: Match the Approved UI Design. Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every `/admin` and `/portal` screen and the four phase PDFs look like the approved design canvas, and change no behaviour.

**Architecture:** One shared Filament theme (Tailwind 4, `resources/css/filament/sta/theme.css`) is registered on both panels. It holds the canvas tokens, the navy shell, the Filament overrides and a small set of `sta-*` component classes. Anonymous Blade components (`<x-sta.*>`) and a tiny `App\Support\Sta` helper hold the shared markup and colour maps. Custom Blade pages are rewritten to the canvas layout, keeping every `wire:` binding. Filament resource tables and forms keep their builders and only get column, section and layout configuration. Two read-only helpers feed the new dashboard numbers: `App\Support\GameProgress` and `App\Support\PdfContext`.

**Tech Stack:** Laravel 12, Filament 4.16 (panels, tables, schemas), Livewire 3, Tailwind CSS 4 through `@tailwindcss/vite`, Pest 4, barryvdh/laravel-dompdf 3.

**Spec:** `docs/superpowers/specs/2026-11-04-sports-entry-system-design.md`, section 17 ("Match the approved UI design"). Read sections 16 and 17 before starting. The canvas screens are `docs/design/canvas/project/*.dc.html`. The `renderVals()` script in each file builds its sample data, and every style is inline.

## Global Constraints

- **Binding spec:** section 17. Where the design shows something the app does not do, the spec's behaviour wins, because the design decides look and layout only.
- **Behaviour:** permissions, validation, workflow and data rules do not change. The only additions are those spec 17 lists:
  - dashboard phase cards, the KPI counts, the "Last activity" column and the All / Pending / Submitted switch;
  - a Phase 1 note per answer;
  - the PDF phase chip, association logo, the prepared / approved / deadline block, "Page N of M", and X marks on Phase 1.
- **Tests:** every existing test keeps passing. Text a test asserts may change only where this plan lists the exact change. There are three such changes, in Tasks 10 and 13.
- **Fonts:** Inter for text and Space Grotesk for headings, from Google Fonts. Use `App\Support\Sta::FONTS_URL`.
- **Colours:**
  - page background `#F5F7FA` (the admin home and the person forms use `#E9EEF5`);
  - text `#0F172A`, secondary text `#475569`, borders `#E2E8F0`;
  - primary `#1F3A5F`;
  - menu `#0F2440`, active menu item `#23406A`.
- **Phase colours (bg / text / dot):**
  - 1: `#E0F2FE` / `#075985` / `#0369A1`
  - 2: `#CCFBF1` / `#115E59` / `#0F766E`
  - 3: `#FEF3C7` / `#92400E` / `#B45309`
  - 4: `#EDE9FE` / `#5B21B6` / `#6D28D9`
- **Status pills:**
  - Submitted: green `#DCFCE7` / `#166534`
  - Awaiting admin: amber `#FEF3C7` / `#92400E`
  - Draft / In progress: indigo `#E0E7FF` / `#1E3A8A`
  - Not started / No response: white with a `#CBD5E1` ring
  - Open: blue `#DBEAFE` / `#1E40AF`
  - Closed and Skipped: slate `#F1F5F9` / `#334155` (Skipped text `#475569`)
- **Shapes:** cards have a 12–14 px radius, controls 10 px. Every interactive target is at least 44 px. Keep every label, `aria-*` attribute and visible focus style, and the design's contrast.
- **Gender wording (spec 16.1):** say "Male", "Female" and "Mixed". The Phase 2 page HTML must never contain the words "Men" or "Women" (see `tests/Feature/GenderLabelsTest.php`).
- **Tailwind content scanning:** the theme `@source` covers:
  - `app/Filament/**`
  - `app/Providers/Filament/**`
  - `app/Support/**`
  - `resources/views/filament/**`
  - `resources/views/components/**`

  PDFs use inline CSS only, because dompdf does not read the theme.
- **Packages:** add no npm or composer packages. `npm install` only installs the existing `package.json`.
- **PHP binary:** `/c/Users/ababy/.config/herd/bin/php85/php.exe` (Git Bash). Every `php` in this plan means that binary.
- **Running tests:** `php vendor/bin/pest`. To run one file, use `php vendor/bin/pest tests/Feature/Ui/ShellTest.php`.
- **Dark mode:** off (`->darkMode(false)`). The canvas has no dark variant, and the menu is always navy.

---

## Canvas reference (extracted from the `.dc.html` files)

### Shared shell (every screen)

- **Root:** `display:flex; flex-wrap:wrap; background:#F5F7FA; font: 14px Inter; color:#0F172A`.
- **Menu (`<nav>`):**
  - layout: `flex:1 1 248px; background:#0F2440; padding:24px 16px; gap:24px`.
  - **Brand block:** a white rounded box 40×56 (`padding:4px; radius:10px`) holds the BOC logo. Beside it are three lines: "Bahrain Olympic Committee" (12px `#A9B8CC`), "Sports Entry" (Space Grotesk 600 16px white), and "Admin panel" or "Association portal" (12px `#A9B8CC`).
  - **Portal only:** an association block below the brand (`background:#1A3358; radius:12px; padding:10px 12px`) shows the logo box (64×40, white) or initials, the association name (600 13px white) and its code (12px `#A9B8CC`).
  - **Menu items:** `min-height:44px; padding:0 12px; radius:10px`. Text is 500 `#C7D2E0`. The active item is 600 white on `#23406A`. Items have no icons.
    - Admin order: Dashboard, Games, Associations, Sports & events, Clothing items, People, Reports, Audit log, STA users.
    - Portal order: Home, Games, Athletes & officials, Excel import, Team, Association profile.
  - **User block:** pinned to the bottom (`margin-top:auto; border-top:1px solid #23406A; padding:12px 8px`). It has a 36px initials circle (`#2B4C7E`, 600 13px), the name (600 13px white) and the role (12px `#A9B8CC`).
- **Main area:** `flex:999 1 560px; padding:32px 40px 48px; gap:20–24px`.
- **Page header:**
  - breadcrumb (13px `#475569`; links `#1F3A5F`);
  - h1 (Space Grotesk 700 30px, `letter-spacing:-0.01em`);
  - subtitle (`#475569`);
  - actions on the right.
- **Buttons:** primary `#1F3A5F`/white, no border; secondary white with a `1px #CBD5E1` border; outline `1px #1F3A5F` with `#1F3A5F` text; danger `1px #FCA5A5` with `#991B1B` text. All have `min-height:44px`, `radius:10px`, `font:600 14px Inter`.
- **Cards and tables:**
  - card: `background:#fff; border:1px solid #E2E8F0; radius:14px`;
  - card head: `padding:16–18px 20px; border-bottom:1px solid #E2E8F0`, with a Space Grotesk 600 17–20px title and a 13px `#475569` subtitle;
  - table head: 12px uppercase 600 `#475569`, `letter-spacing:.05em`, padding `12px 20px` on the first and last cells and `12px` elsewhere;
  - table rows: `border-top:1px solid #EEF2F6`;
  - table foot: `padding:14px 20px`, 13px `#475569`.
- **KPI tile:** `radius:12px; padding:16px 18px; gap:6px`, with a 13px label, a Space Grotesk 700 30px value in the tone colour and a 12px hint.
- **Phase chip:** a pill 26px high with an 8px dot, 600 12px, in the phase colours.
- **Status pill:** a 26px pill, 600 12px (see Global Constraints).
- **Segmented control:**
  - group: `background:#F1F5F9; radius:10px; padding:4px; gap:4px`;
  - each button: 36px high, `radius:8px`, 600 13px;
  - the pressed button is white with `box-shadow:0 1px 2px rgba(15,23,42,.08)`.
- **Inputs:** `min-height:44px; border:1px solid #CBD5E1; radius:10px; padding:0 12px; font:14px Inter`. Labels are 600 13px above the field, with a 6px gap.
- **Banners:**
  - warn: `#FFF7ED` / `#9A3412`, border `#FED7AA`;
  - info: `#EFF6FF` / `#1E3A8A`, border `#BFDBFE`;
  - ok: `#DCFCE7` / `#166534`.
- **Section bands (head / body / border / title / icon):** each card has a coloured head with a 32px rounded icon square.

  | Band | head | body | border | title | icon | used for |
  |---|---|---|---|---|---|---|
  | blue | `#EFF6FF` | `#F8FBFF` | `#BFDBFE` | `#1E40AF` | `#3B82F6` | Person form section; "Active games" (`#2563EB`) |
  | indigo | `#EEF2FF` | `#F7F8FF` | `#C7D2FE` | `#3730A3` | `#4338CA` | Passport; Associations on the home page |
  | violet | `#F5F3FF` | `#FAF9FF` | `#DDD6FE` | `#5B21B6` | `#8B5CF6` | CPR; Short List justification |
  | teal | `#F0FDFA` | `#F7FEFC` | `#99F6E4` | `#115E59` | `#115E59` | Contact; Registry health |
  | amber | `#FFFBEB` | `#FFFDF5` | `#FDE68A` | `#92400E` | `#D97706` | Guardian; Needs your attention; clothing setup |
  | slate | `#F1F5F9` | `#F8FAFC` | `#CBD5E1` | `#334155` | `#475569` | Clothing; Recent activity |
  | rose | `#FFF1F2` | `#FFF8F9` | `#FECDD3` | `#9F1239` | `#E11D48` | Medical / Health |
  | fuchsia | `#FDF4FF` | `#FEF9FF` | `#F5D0FE` | `#86198F` | `#86198F` | Consents / Declarations |
  | cyan | `#ECFEFF` | `#F6FEFF` | `#A5F3FC` | `#155E75` | `#0E7490` | Team official details |
  | green | `#F0FDF4` | `#F7FEF9` | `#BBF7D0` | `#166534` | `#16A34A` | Visas |

- **Association colours (initials circles):** `#9A3412 #3730A3 #15803D #86198F #0F766E #BE185D #374151 #854D0E #4D7C0F #0369A1`.

### Per screen

| Canvas file | Layout and components |
|---|---|
| `Main` (game dashboard) | **Header:** breadcrumb Games / game; h1 = game name; subtitle "place · dates · N associations invited · N sports"; actions Export Excel (secondary) and "Remind pending (19)" (primary).<br>**Phase cards:** a grid of 4 (`minmax(220px,1fr)`). Each card has a chip, a status pill, a name and a meta line; the active card has a `#1F3A5F` border and a 3px `#DCE6F2` ring.<br>**KPIs:** a grid of 5 (`minmax(170px,1fr)`): "Submitted to STA", "Awaiting association admin", "In draft", "Not started", "Flags to review".<br>**"Who has submitted" card:** an All / Pending / Submitted segmented switch and a search box. The table has the columns Association (logo or initials, name, sports), one phase-chip column per phase (pill + View PDF), Flags (`#9A3412` when present), Last activity and Action. The footer reads "Showing N of M". |
| `Phase2` | Phase-tinted header band (`#F0FDFA`/`#99F6E4`) with breadcrumb, h1 and the chip "Phase 2 of 4 · Open". Below the header is a stepper of the 4 phases: the current phase is solid `#0F766E` with white text; the others are tinted with dots. Next comes a deadline banner (warn).<br>**Split layout:** the main card per sport holds the "Total number of participants" fieldset (teal tint), the events table (Event with "Up to N athletes" in `#115E59`, and Male / Female columns where the other gender is greyed `#E2E8F0`) with an "Event entries" total row, and the "Expected medals" fieldset (Gold `#CA8A04`, Silver `#94A3B8`, Bronze `#B45309` dots).<br>**Aside:** a "Weightlifting limits" card with 3 meters ("Male participants", "Female participants", "Total participants": "x of y", 8px bar `#1F3A5F`, or `#C2410C` when over), an "Entry policy" card, and the submit block with Save as draft and Submit. |
| `LongList` / `LongListGrid` | Amber header band with the chips Phase 3 and "Draft · saved 10:42", and the subtitle "Deadline · days left · Clothing items for this game". Actions: Preview PDF, Save as draft, Submit for approval.<br>**"Compared with your Phase 2 numbers" card:** chips "Event · LL of P2", orange when over.<br>**Tabs row:** Athletes, Team officials, Warnings, plus the counts "4 male · 2 female · 3 team officials", Add athlete, Add team official and the List / Grid segmented switch.<br>**List:** a table (Athlete with avatar and code, Gender, Born · age, Event, one column per clothing item, Scans with View links, Warning).<br>**Grid:** cards with a 56px avatar, name, code, dob and age, a gender tag, an event tag, a 3-column size grid (orange when missing), scan links and a warning banner.<br>**Side panel:** a 480px panel for adding a person. |
| `LongListEvents` | Amber header band. **Sport card:** the head shows the sport, "N events · N athletes · N team officials", Add athlete and Add team official, then a help paragraph. A grid of event boxes (`minmax(440px,1fr)`); each box has the event label, "Long List x / Phase 2: y", an "Over the Phase 2 number" pill, and a 48px token field with person chips (24px avatar, name, code, ×) and a search input. Errors show in `#B91C1C`. Below the grid is a Team officials box with the same token field.<br>**"People on your Long List" table:** Person, Gender, Role, Sport · events. |
| `ShortList` | Violet header band and an info note.<br>**Main:** a per-sport card (h2, "Long List: … · Ticked: …", and a Within / Over pill) with an Athletes table (On Short List checkbox 22px `accent-color:#6D28D9` with avatar, name and code; Gender; Event; Passport View) and a Team officials table.<br>**Aside:** a "ticked vs maximum" card with violet meters, a violet "Justification needed" card (`#F5F3FF`, 2px `#6D28D9` border, textarea) or a green "Within all maximums" status. |
| `Registry` | Header "Athletes & officials" with the subtitle "Your permanent registry · reused for every game · N athletes, N team officials" and the actions Download template, Import Excel and Add person.<br>**List card:** search, an All / Athletes / Officials segmented switch, and a table with Name (avatar), Type, Date of birth, Passport expiry (orange when within 6 months) and Sizes (Complete green / "N missing" orange). The footer reads "Showing N of M · passport dates in orange expire within 6 months".<br>**Aside:** an edit panel (we keep the separate edit page; see Decisions). |
| `SetupInvite`, `SetupLimits`, `SetupShortList`, `SetupPhases` | **Shared header:** breadcrumb Games / game / Setup, h1 "Game setup", the pill "Draft · not visible to associations", the hint "Publish needs 1+ invited association and 1+ sport", and the actions Save as draft and Publish. Underlined tabs: "1 · Details & associations", "2 · Sports & limits", "3 · Clothing & short list", "4 · Phases".<br>**Details:** a card with Name, dates and Game logo, plus an invited-associations list with sport toggles.<br>**Limits:** a sports list on the left; a teal "Phase 2 limits" card with Max male / Max female / Max total steppers and age dates; an "Events offered" table.<br>**Clothing:** an amber clothing card grid; a violet "Short list maximums" table.<br>**Phases:** an info banner, then one tinted card per phase. Each has a 44px number square, a chip, the name, a status pill, a description, progress, a Used / Skipped switch, Opens and Deadline dates, and an Open / Close / Re-open button (danger style for Close). It is followed by the "Automatic reminders" and "Extensions" cards. |
| `AdminAssociations` | Header "Associations" with the counts subtitle and "New association".<br>**Table:** Association (logo or initials, name, email), Admin pill (Active name / "Invite pending" amber / "No admin" orange ring), Editors "n / 2", Sports.<br>**Aside:** details, Users and Invitations (we keep the Edit page with its relation managers). |
| `AdminSports` | Header "Sports & events". A sports list (name, association, event count, Active pill) beside a details card and an events table (Event, Gender pill Male `#E0E7FF`/`#1E3A8A`, Female `#FCE7F3`/`#9D174D`, Mixed slate; Used in games; Active switch). |
| `AdminPeople` | Header "People" with Export Excel. 4 KPI tiles: Athletes, Team officials, "Passport expires within 6 months" (orange), "Missing passport scan" (orange). The table has a search, an association select, the Type switch and an "Expiring passports" toggle, with the columns Name, Association, Type, Passport expiry and Scans pill. |
| `AdminDashboard` (home) | Background `#E9EEF5`. Header "Dashboard" with the date subtitle and "New game".<br>**"Active games" band (blue):** game cards with name, meta, phase chip, Deadline, "Time left" (orange Space Grotesk 20px), "Associations submitted x of y" with an 8px bar, a breakdown line and "Open game dashboard →".<br>**"Needs your attention" (amber):** a list of items, each with a 36px icon square, a title, a text and a link or button.<br>**"Registry health" (teal):** 4 numbers, a passports-expiring banner and per-association stacked bars.<br>**"Associations" (indigo):** a table with Code, Admin pill, Editors, People, Complete % (with a mini bar), Last activity and Active.<br>**"Recent activity" (slate):** a list of when / who / what, with "Medical data changed" and "Documents changed" pills. |
| `PlayerForm` / `OfficialForm` | Background `#E9EEF5`. **Header card:** a 64px avatar, breadcrumb, h1 = name, chips (phase, code `#E8EEF6`, Complete / Incomplete), the "Missing: …" text, and Cancel / Save as draft / Save.<br>A required-fields note, then a row of jump-link chips. Each section is a colour band (see the table above), numbered "1 · Person", and so on. Fields are 44px with "needed for Long List" hints. File rows show name, type and size with View and Replace. Switches are 52×44 hit areas. |
| `AdminReports` | Header "Reports" with a Game select and "New report".<br>**"Ready-made reports" band (blue):** a list (name, hint, phase chip, active row `#E8EEF6`) beside a preview card (chip, title, description, Export to Excel, a 6-row table with an "All N associations" total row, and a footer note).<br>**"Saved reports" band (indigo):** My reports / Shared tabs and a card grid (name, share pill, a Subject / Filters / Grouped list, meta, Open and Export), plus a dashed "New report" tile. |
| `AdminReportBuilder` | Breadcrumb Reports / Report builder. The h1 is an inline-editable name. Actions: a Share select, Export, Save as copy, Save and Delete (danger).<br>**Aside (320px):** sections Subject, Columns (grouped checkboxes), Filters (rule chips and "+ Add rule"), Group by (up to 2) and Sort, with uppercase 12px section labels.<br>**Results card:** the pill "Updates as you change settings"; group rows `#E8EEF6` and `#F8FAFC`; subtotal rows; a grand total row with a 2px `#1F3A5F` top border. |
| `PdfPhase1` (A4 portrait) | **Header:** the BOC logo and the texts "Bahrain Olympic Committee" / "National Olympic Committee of Bahrain" on the left; game name, place and dates and the game logo on the right; a 2px `#0F2440` rule.<br>A title row with the phase chip and h1 "Entry by Sport" on the left, and the association logo with "Association / name (code)" on the right. Then an intro paragraph.<br>**Table** with 1px `#94A3B8` borders: Sport, Yes, No, Maybe (X marks, 700 15px), Note, and a Total row.<br>**dl:** Prepared by, Approved by, Phase 1 deadline. Then the declaration, a Name / Position / Signature / Date grid with a dashed Stamp box, and a footer "Reference … · generated …" with "Page 1 of 1". |
| `PdfPhase2` (A4 portrait) | The same header. Title "Entry by Event — {sport}". Table: Event, Allowed ("Up to N"), Male, Female, plus Event entries and "All events (incl. mixed)" rows. Side-by-side "Total number of participants" and "Expected medals" tables, then the Entry policy and Age eligibility boxes, the declaration and the footer. |
| `PdfPhase3` / `PdfPhase4` (A4 landscape) | A one-row header (BOC logo; phase chip with h1 in the middle; game and logo on the right). An association row with a boxed Summary (Athletes Male, Athletes Female, Athletes Total, Team officials) and "Submitted … by …". Athletes and Team officials tables (S.No, Photo, …, size columns, Passport scan). Phase 4 adds a "Justification (over limit)" box with a 1px `#6D28D9` border. Then the declaration and the footer. |

## Screen → app page mapping

| Canvas screen | App page (class → view) | Kind |
|---|---|---|
| Main | `Resources/Games/Pages/GameDashboard` → `filament/resources/games/pages/game-dashboard` | custom view |
| AdminDashboard | new `App\Filament\Pages\Dashboard` (extends Filament Dashboard) → `filament/pages/dashboard` | custom view |
| Phase2 | `Portal/Pages/Phase2Entry` and `StaPhase2Edit` → `filament/portal/pages/phase2-entry` | custom view |
| (Phase 1, no canvas) | `Phase1Entry` and `StaPhase1Edit` → `phase1-entry` | custom view |
| LongList, LongListGrid, LongListEvents | `Phase3Entry` and `StaPhase3Edit` → `phase3-entry`, `filament/shared/long-list-people` | custom view (+ header actions) |
| ShortList | `Phase4Entry` and `StaPhase4Edit` → `phase4-entry` | custom view |
| Registry | `Portal/Resources/People` (List, Create, Edit) with `Shared/PersonTable` | Filament resource |
| PlayerForm, OfficialForm | `Shared/PersonForm` (portal and admin Create / Edit, and the Phase 3 add slide-over) | Filament form |
| AdminPeople | `Resources/People` (List, Edit) | Filament resource |
| SetupInvite, SetupLimits, SetupShortList, SetupPhases | `Resources/Games/Pages/EditGame` (`GameForm`) + `GameSportsRelationManager` + `PhasesRelationManager`, combined into tabs | Filament form + relation managers |
| AdminAssociations | `Resources/Associations` (List, Edit, plus Users and Invitations relation managers) | Filament resource |
| AdminSports | `Resources/Sports` (List, Edit, plus Events relation manager) | Filament resource |
| AdminReports | `Pages/Reports` → `filament/pages/reports`, and `Games/Pages/GameReports` → `game-reports` (the preview) | custom views |
| AdminReportBuilder | `Pages/ReportBuilder` → `filament/pages/report-builder` | custom view |
| PdfPhase1–4 | `resources/views/pdf/entry-by-sport`, `entry-by-event`, `long-list` (also used for the Short List) | dompdf Blade |
| (no canvas) | login (both panels), portal `Games` + new `Home`, `SubmissionView`, comments partial, `ExtraFields`, `GameExportFiles`, `ActivityLogs`, `Settings`, `StaUsers`, `ClothingItems`, `Members`, `Invitations`, `ImportPeople`, `EditAssociationProfile`, the public invitation pages, all modals | theme only + small tweaks |

## Decisions made in this plan

1. **One theme for both panels** (`resources/css/filament/sta/theme.css`). The panels differ only in Blade text, so a second CSS file would be a copy.
2. **The Filament page header stays.** Filament's header already gives breadcrumbs, title, subtitle and action buttons. The theme restyles it, and phase pages tint it through `getPageClasses()` and put their chips in `getHeading()`. We do not add an `x-sta.page-header` component, and "data table shell" is the `.sta-table` CSS class inside `<x-sta.card>`.
3. **The user block** uses Filament's sidebar user menu (`UserMenuPosition::Sidebar`) for the initials and name, so logout and profile keep working. A `SIDEBAR_FOOTER` hook adds the role line. The topbar stays for the mobile menu button and notifications.
4. **Initials avatars** are drawn locally (`InitialsAvatarProvider`) instead of through ui-avatars.com.
5. **Side panels in the canvas** use the app's existing screens:
   - the Registry and AdminAssociations side panels → the separate Edit pages;
   - the Long List add-person panel → the existing slide-over action.

   The Add athlete and Add team official buttons stay in the page header, because STA edit pages must not show them.
6. **Long List pickers** become chips with × and a native "Search athletes…" `<select>` that adds on change, through new `addAthlete` / `removeAthlete` / `addOfficial` / `removeOfficial` methods. The selection arrays and save validation are unchanged. Native `<select>` supports type-to-jump; there is no custom typeahead.
7. **Phase 2 counts** stay as 44px number inputs and are not turned into − / + steppers, because the live validation is tied to the input.
8. **Game setup** uses three tabs: "1 · Details & associations" (the form, including clothing), "2 · Sports & limits" (sports with Phase 2 and Phase 4 maximums) and "3 · Phases" (phase cards). The canvas tabs 3 and 4 are folded in, because clothing is a game form field and the Short List maximums live on each game sport. The reminder days stay in Details. Per-association extensions stay on the game dashboard, where they work today.
9. **Dashboard "Flags"** for the open phase:
   - Phase 3: events whose Long List count is above the submitted Phase 2 number;
   - Phase 4: Short List maximums exceeded;
   - other phases: none.

   "Last activity" is the latest `updated_at` of that association's submissions in the game. "Overdue" (an existing dashboard status) uses the orange warn pill. "Comments awaiting your reply" on the canvas home is left out, because comments go one way from STA to associations.
10. **Section numbers in the person form are left out.** The type switch reorders sections, so numbers would shift as you type. The jump-link chip row is also left out. The section colour bands are kept.
11. **PDFs** keep DejaVu Sans, which is embedded and has no new font files. They show the BOC logo from `public/images/boc-logo.png` when no NOC logo is set. "Page N of M" uses CSS `counter(page)` / `counter(pages)`, so PHP is not enabled in dompdf. Officials keep the combined "Sizes" column (`T-Shirt: M, …`), so the existing test and job data stay as they are.
12. **Navigation** is flat in the canvas order, without groups ("Games" and "Access" are removed). Icons are hidden by CSS, so the icon definitions stay. Settings and Extra fields come after STA users.
13. **The portal home** ("Home") is a Filament Dashboard subclass that shows the same game cards as the Games page.
14. **The QA data** is `Database\Seeders\DemoSeeder`. It refuses to run in production and is never called from `DatabaseSeeder`.

## File structure

**Create**
- `resources/css/filament/sta/theme.css`: tokens, shell, Filament overrides, the `sta-*` classes.
- `public/images/boc-logo.webp` (copied from the canvas assets) and `public/images/boc-logo.png` (made with GD, for dompdf).
- `app/Support/Sta.php`: colour maps, status → pill maps, initials, days-left text, heading HTML.
- `app/Support/InitialsAvatarProvider.php`: local SVG avatars.
- `app/Support/GameProgress.php`: per-game submission status, counts, last activity and flags (used by the game dashboard and the home page).
- `app/Support/PdfContext.php`: logos, prepared / approved / deadline data for the PDFs.
- `resources/views/filament/brand.blade.php` and `resources/views/filament/sidebar-role.blade.php`.
- `resources/views/components/sta/{phase-chip,pill,kpi,card,phase-card,assoc-avatar,person-avatar,meter,stepper,heading}.blade.php`.
- `resources/views/filament/portal/partials/phase-top.blade.php`: stepper, read-only banner, PDF link.
- `resources/views/filament/resources/games/phase-card.blade.php`: the phases relation manager card cell.
- `resources/views/filament/resources/people/kpis.blade.php`: the admin People KPI tiles.
- `app/Filament/Pages/Dashboard.php` + `resources/views/filament/pages/dashboard.blade.php`: the admin home.
- `app/Filament/Portal/Pages/Home.php`: the portal home.
- `resources/views/pdf/partials/{styles,header,signoff,footer}.blade.php`.
- `database/migrations/2026_11_16_000100_add_note_to_interest_answers.php`.
- `database/seeders/DemoSeeder.php`.
- Tests in `tests/Feature/Ui/`: `ShellTest`, `ComponentsTest`, `PhasePagesTest`, `Phase1NoteAndPhase2Test`, `LongListUiTest`, `ShortListUiTest`, `PeopleUiTest`, `GameSetupUiTest`, `AdminListsUiTest`, `GameDashboardUiTest`, `AdminHomeTest`, `ReportsUiTest`, `PdfDesignTest`, `DemoSeederTest`.

**Modify (main ones)**
- `vite.config.js`
- `tests/TestCase.php`
- both `*PanelProvider.php`
- `app/Models/Association.php`
- `app/Models/InterestAnswer.php`
- the navigation statics in resources and pages
- `HandlesSubmission`, `StaEditsSubmission`, `EditsPhase1`, `EditsPhase3`, `EditsPhase4`
- the four phase views and `long-list-people`
- `GameDashboard` + view
- `EditGame`, `GameForm`, both game relation managers
- `PersonForm`, `PersonTable`, both `ListPeople`, both `EditPerson`
- `AssociationsTable`, `SportsTable`, `EventsRelationManager`, `ActivityLogResource`
- the `reports`, `game-reports`, `report-builder` and `submission-view` views
- `SubmissionView::phase1Rows`
- the 4 PDF jobs and the 3 PDF views
- `resources/views/invitations/*.blade.php`
- `tests/Feature/GameDashboardTest.php`, `tests/Feature/LongListPdfTest.php` and `tests/Feature/ShortListPdfTest.php`, for the listed text changes only.

---
### Task 1: Theme, tokens and the navy shell (both panels)

**Files:**
- Create:
  - `resources/css/filament/sta/theme.css`
  - `app/Support/Sta.php`
  - `app/Support/InitialsAvatarProvider.php`
  - `resources/views/filament/brand.blade.php`
  - `resources/views/filament/sidebar-role.blade.php`
  - `public/images/boc-logo.webp`
  - `tests/Feature/Ui/ShellTest.php`
- Modify:
  - `vite.config.js`
  - `tests/TestCase.php`
  - `app/Providers/Filament/AdminPanelProvider.php`
  - `app/Providers/Filament/PortalPanelProvider.php`
  - `app/Models/Association.php`
  - the navigation statics in:
    - `app/Filament/Resources/{Games/GameResource,Associations/AssociationResource,Sports/SportResource,ClothingItems/ClothingItemResource,People/PersonResource,ActivityLogs/ActivityLogResource,StaUsers/StaUserResource,ExtraFields/ExtraFieldResource}.php`
    - `app/Filament/Pages/{Reports,Settings}.php`
    - `app/Filament/Portal/Resources/{People/PersonResource,Members/MemberResource,Invitations/InvitationResource,Sports/SportResource}.php`
    - `app/Filament/Portal/Pages/ImportPeople.php`

**Interfaces:**
- Produces:
  - `App\Support\Sta::FONTS_URL` (string)
  - `Sta::AVATAR_COLOURS` (list of hex strings)
  - `Sta::initials(?string $name): string`
  - `Sta::avatarColour(string $key): string`
  - `InitialsAvatarProvider::svg(string $text, string $fill = '#2B4C7E'): string` (an SVG data URI)
  - the CSS classes every later task uses:
    - cards and tables: `sta-card`, `sta-card-head`, `sta-card-head-text`, `sta-card-title`, `sta-card-sub`, `sta-card-actions`, `sta-card-body`, `sta-card-foot`, `sta-scroll`, `sta-table` (rows `sta-total`, cells `sta-off`)
    - pills: `sta-pill`, `sta-pill-{submitted,ok,awaiting,draft,none,open,closed,skipped,warn,overdue,male,female,mixed}`
    - phases: `sta-phase-chip`, `sta-ph-{1..4}`, `sta-phase-page`, `sta-heading`, `sta-stepper`, `sta-phase-card`, `sta-phase-card-name`
    - KPIs: `sta-kpi`, `sta-kpi-label`, `sta-kpi-value`, `sta-kpi-hint`, `sta-tone-{submitted,awaiting,draft,none,warn}`
    - bands: `sta-band`, `sta-band-{blue,indigo,violet,teal,amber,slate,rose,fuchsia,cyan,green}`, `sta-band-head`, `sta-band-icon`, `sta-band-strip`
    - people and associations: `sta-avatar`, `sta-avatar-{sm,lg,xl}`, `sta-logo`, `sta-logo-{sm,lg}`, `sta-who`, `sta-who-text`, `sta-who-name`, `sta-who-sub`
    - meters: `sta-meter`, `sta-meter-row`, `sta-meter-label`, `sta-meter-value`, `sta-meter-track`, `sta-meter-bar`
    - controls: `sta-seg`, `sta-btn`, `sta-btn-primary`, `sta-btn-outline`, `sta-btn-danger`, `sta-input`, `sta-input-num`, `sta-field`
    - banners: `sta-banner`, `sta-banner-{info,warn,ok}`
    - layout: `sta-grid-kpi`, `sta-grid-phase`, `sta-grid-cards`, `sta-split`, `sta-main`, `sta-aside`, `sta-row-between`
    - text and lists: `sta-muted`, `sta-sr-only`, `sta-tag`, `sta-chip-person`, `sta-token-field`, `sta-list`, `sta-list-item`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ui/ShellTest.php`:

```php
<?php

use App\Models\Association;
use App\Models\User;
use App\Support\InitialsAvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Support\Str;

it('registers the shared STA theme on both panels and in Vite', function () {
    foreach (['admin', 'portal'] as $id) {
        expect(Filament::getPanel($id)->getViteTheme())->toBe('resources/css/filament/sta/theme.css');
    }
    expect(file_get_contents(base_path('vite.config.js')))->toContain('resources/css/filament/sta/theme.css')
        ->and(file_exists(public_path('images/boc-logo.webp')))->toBeTrue()
        ->and(file_get_contents(resource_path('css/filament/sta/theme.css')))
        ->toContain("@source '../../../../resources/views/components/**/*'")
        ->toContain('--sta-nav: #0F2440');
});

it('shows the BOC brand, the panel name and the user role in the admin menu', function () {
    actingInAdmin(User::factory()->superAdmin()->create(['name' => 'Ali Haroona']));

    $this->get('/admin')->assertOk()
        ->assertSee('Bahrain Olympic Committee')->assertSee('Sports Entry')->assertSee('Admin panel')
        ->assertSee('images/boc-logo.webp', escape: false)->assertSee('Super Admin');
});

it('shows the association portal brand, the association and the role', function () {
    $a = Association::factory()->create(['name' => 'Bahrain Weightlifting Federation', 'code' => 'BWF']);
    actingInPortal(User::factory()->assocEditor($a)->create());

    $this->get("/portal/{$a->id}")->assertOk()
        ->assertSee('Association portal')->assertSee('Bahrain Weightlifting Federation')->assertSee('Association Editor');
});

it('orders the admin menu like the canvas', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    $this->get('/admin')->assertSeeInOrder(
        ['Games', 'Associations', 'Sports &amp; events', 'Clothing items', 'People', 'Reports', 'Audit log', 'STA users'], escape: false);
});

it('draws initials avatars without calling an outside service', function () {
    $uri = (new InitialsAvatarProvider)->get(User::factory()->make(['name' => 'Mariam Khalil']));

    expect($uri)->toStartWith('data:image/svg+xml;base64,')
        ->and(base64_decode(Str::after($uri, 'base64,')))->toContain('>MK<')->toContain('#2B4C7E');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run `php vendor/bin/pest tests/Feature/Ui/ShellTest.php`.

Expected: FAIL, because `getViteTheme()` is null, `App\Support\InitialsAvatarProvider` is missing, and "Admin panel" is not on the page.

- [ ] **Step 3: Copy the logo**

```bash
mkdir -p public/images
cp docs/design/canvas/assets/2f304cb4202092a8a167a0c221c1c36f.webp public/images/boc-logo.webp
```

- [ ] **Step 4: Create the theme**

Create `resources/css/filament/sta/theme.css`:

```css
@import '../../../../vendor/filament/filament/resources/css/theme.css';

@source '../../../../app/Filament/**/*';
@source '../../../../app/Providers/Filament/**/*';
@source '../../../../app/Support/**/*';
@source '../../../../resources/views/filament/**/*';
@source '../../../../resources/views/components/**/*';

/* Canvas tokens: docs/design/canvas/project/*.dc.html (spec 17). */
:root {
    --sta-bg: #F5F7FA;
    --sta-bg-home: #E9EEF5;
    --sta-text: #0F172A;
    --sta-text-2: #475569;
    --sta-text-3: #334155;
    --sta-border: #E2E8F0;
    --sta-border-strong: #CBD5E1;
    --sta-row: #EEF2F6;
    --sta-primary: #1F3A5F;
    --sta-primary-hover: #0F2440;
    --sta-selected: #E8EEF6;
    --sta-row-selected: #F1F5FB;
    --sta-nav: #0F2440;
    --sta-nav-active: #23406A;
    --sta-nav-hover: #1A3358;
    --sta-nav-text: #C7D2E0;
    --sta-nav-muted: #A9B8CC;
    --sta-avatar: #2B4C7E;
    --sta-warn: #9A3412;
    --sta-radius-card: 14px;
    --sta-radius-tile: 12px;
    --sta-radius-ctl: 10px;
    --sta-font-heading: 'Space Grotesk', ui-sans-serif, system-ui, sans-serif;
    --sta-target: 44px;
}

/* ---------- Filament shell ---------- */
.fi-body { background: var(--sta-bg); color: var(--sta-text); font-size: 14px; }
.fi-body a:not(.fi-btn):not(.fi-sidebar-item-btn):not(.sta-btn) { color: var(--sta-primary); }
.fi-main { padding: 32px 40px 48px; }
@media (max-width: 640px) { .fi-main { padding: 16px; } }

.fi-sidebar { background: var(--sta-nav); color: #E2E8F0; }
.fi-sidebar-header { background: transparent; box-shadow: none; border: 0; height: auto; padding: 24px 16px 8px; }
.fi-sidebar-header-controls { flex-direction: column; align-items: stretch; gap: 16px; }
.fi-sidebar-nav { padding: 16px; }
.fi-sidebar-nav-groups { gap: 4px; }
.fi-sidebar-group-label { color: var(--sta-nav-muted); }
.fi-sidebar-item-btn { min-height: var(--sta-target); padding: 0 12px; border-radius: var(--sta-radius-ctl); color: var(--sta-nav-text); font-weight: 500; }
.fi-sidebar-item-btn:hover, .fi-sidebar-item-btn:focus-visible { background: var(--sta-nav-hover); }
.fi-sidebar-item-btn:focus-visible { outline: 2px solid #FFFFFF; outline-offset: 2px; }
.fi-sidebar-item-label { color: inherit; }
.fi-sidebar-item-icon { display: none; } /* canvas menu has no icons */
.fi-sidebar-item.fi-active > .fi-sidebar-item-btn { background: var(--sta-nav-active); }
.fi-sidebar-item.fi-active .fi-sidebar-item-label { color: #FFFFFF; font-weight: 600; }
.fi-sidebar-footer { margin: 0 16px; padding: 12px 0 0; border-top: 1px solid var(--sta-nav-active); }
.fi-sidebar .fi-user-menu-trigger, .fi-sidebar .fi-user-menu-trigger-text { color: #FFFFFF; font-weight: 600; font-size: 13px; }
.fi-sidebar .fi-user-menu-trigger:hover { background: var(--sta-nav-hover); }
.fi-sidebar .fi-tenant-menu-trigger { background: var(--sta-nav-hover); border-radius: var(--sta-radius-tile); padding: 10px 12px; color: #FFFFFF; min-height: var(--sta-target); }
.fi-sidebar .fi-tenant-menu-trigger-current-tenant-label { color: var(--sta-nav-muted); font-size: 12px; }
.fi-sidebar .fi-tenant-menu-trigger-tenant-name { color: #FFFFFF; font-weight: 600; font-size: 13px; }
.sta-user-role { margin: -8px 16px 16px 62px; font-size: 12px; color: var(--sta-nav-muted); }

.fi-topbar { background: #FFFFFF; box-shadow: none; border-bottom: 1px solid var(--sta-border); }

/* Brand block (sidebar, topbar on phones, login card) */
.sta-brand { display: flex; align-items: center; gap: 12px; text-align: left; }
.sta-brand-logo { width: 40px; height: 56px; padding: 4px; box-sizing: border-box; border-radius: var(--sta-radius-ctl); background: #FFFFFF; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sta-brand-logo img { width: 100%; height: 100%; object-fit: contain; }
.sta-brand-text { display: flex; flex-direction: column; line-height: 1.3; }
.sta-brand-org, .sta-brand-panel { font-size: 12px; color: var(--sta-nav-muted); }
.sta-brand-app { font-family: var(--sta-font-heading); font-weight: 600; font-size: 16px; color: #FFFFFF; }
.fi-topbar .sta-brand-org, .fi-topbar .sta-brand-panel, .fi-simple-layout .sta-brand-org, .fi-simple-layout .sta-brand-panel { color: var(--sta-text-2); }
.fi-topbar .sta-brand-app, .fi-simple-layout .sta-brand-app { color: var(--sta-text); }
.fi-topbar .sta-brand-logo, .fi-simple-layout .sta-brand-logo { border: 1px solid var(--sta-border); }

/* Login and other simple pages */
.fi-simple-layout { background: var(--sta-bg); }
.fi-simple-main { border-radius: var(--sta-radius-card); border: 1px solid var(--sta-border); box-shadow: none; }

/* Page header */
.fi-header-heading { font-family: var(--sta-font-heading); font-size: 30px; font-weight: 700; letter-spacing: -0.01em; color: var(--sta-text); }
.fi-header-subheading { color: var(--sta-text-2); font-size: 14px; }
.fi-breadcrumbs { font-size: 13px; color: var(--sta-text-2); }
.sta-phase-page .fi-header { padding: 16px 20px; border-radius: var(--sta-radius-card); background: var(--ph-tint); border: 1px solid var(--ph-border); }
.sta-heading { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 12px; }
.sta-heading .sta-phase-chip, .sta-heading .sta-pill { height: 28px; font-family: Inter, ui-sans-serif, sans-serif; letter-spacing: 0; }

/* Filament controls */
.fi-btn { min-height: var(--sta-target); border-radius: var(--sta-radius-ctl); font-weight: 600; }
.fi-link { min-height: var(--sta-target); display: inline-flex; align-items: center; }
.fi-input-wrp { min-height: var(--sta-target); border-radius: var(--sta-radius-ctl); }
.fi-badge { border-radius: 999px; }
.fi-section:not(.fi-section-not-contained) { border-radius: var(--sta-radius-card); box-shadow: none; border: 1px solid var(--sta-border); }
.fi-section-header-heading { font-family: var(--sta-font-heading); font-size: 17px; font-weight: 600; }
.fi-ta-ctn { border-radius: var(--sta-radius-card); box-shadow: none; border: 1px solid var(--sta-border); }
.fi-ta-table thead th { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: var(--sta-text-2); }
.fi-ta-row { border-top: 1px solid var(--sta-row); }
.fi-tabs { border-bottom: 1px solid var(--sta-border); box-shadow: none; background: transparent; border-radius: 0; }
.fi-tabs-item { min-height: var(--sta-target); border-radius: 0; border-bottom: 2px solid transparent; font-weight: 600; color: var(--sta-text-2); }
.fi-tabs-item.fi-active { border-bottom-color: var(--sta-primary); color: var(--sta-text); background: transparent; }
.fi-modal-window { border-radius: var(--sta-radius-card); }

/* ---------- sta-* components ---------- */
.sta-muted { color: var(--sta-text-2); font-size: 13px; }
.sta-sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
.sta-row-between { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px; }

.sta-card { background: #FFFFFF; border: 1px solid var(--sta-border); border-radius: var(--sta-radius-card); }
.sta-card-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--sta-border); }
.sta-card-head-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.sta-card-title { margin: 0; font-family: var(--sta-font-heading); font-size: 19px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
.sta-card-sub { font-size: 13px; color: var(--sta-text-2); }
.sta-card-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.sta-card-body { padding: 16px 20px; }
.sta-card-foot { padding: 14px 20px; border-top: 1px solid var(--sta-border); font-size: 13px; color: var(--sta-text-2); }
.sta-scroll { overflow-x: auto; }

.sta-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.sta-table thead th { text-align: left; font-size: 12px; font-weight: 600; color: var(--sta-text-2); text-transform: uppercase; letter-spacing: .05em; padding: 12px; }
.sta-table td, .sta-table tbody th { padding: 12px; vertical-align: middle; text-align: left; }
.sta-table tr > :first-child { padding-left: 20px; }
.sta-table tr > :last-child { padding-right: 20px; }
.sta-table tbody tr { border-top: 1px solid var(--sta-row); }
.sta-table tr.sta-total { border-top: 2px solid var(--sta-border); background: #F8FAFC; font-weight: 700; }
.sta-table td.sta-off { background: var(--sta-border); }

.sta-pill { display: inline-flex; align-items: center; height: 26px; padding: 0 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
.sta-pill-submitted, .sta-pill-ok { background: #DCFCE7; color: #166534; }
.sta-pill-awaiting { background: #FEF3C7; color: #92400E; }
.sta-pill-draft { background: #E0E7FF; color: #1E3A8A; }
.sta-pill-none { background: #FFFFFF; color: var(--sta-text-3); box-shadow: inset 0 0 0 1px var(--sta-border-strong); }
.sta-pill-open { background: #DBEAFE; color: #1E40AF; }
.sta-pill-closed { background: #F1F5F9; color: var(--sta-text-3); }
.sta-pill-skipped { background: #F1F5F9; color: var(--sta-text-2); }
.sta-pill-warn, .sta-pill-overdue { background: #FFEDD5; color: var(--sta-warn); }
.sta-pill-male { background: #E0E7FF; color: #1E3A8A; }
.sta-pill-female { background: #FCE7F3; color: #9D174D; }
.sta-pill-mixed { background: #F1F5F9; color: var(--sta-text-3); }

.sta-ph-1 { --ph-bg: #E0F2FE; --ph-fg: #075985; --ph-dot: #0369A1; --ph-tint: #F0F9FF; --ph-border: #BAE6FD; }
.sta-ph-2 { --ph-bg: #CCFBF1; --ph-fg: #115E59; --ph-dot: #0F766E; --ph-tint: #F0FDFA; --ph-border: #99F6E4; }
.sta-ph-3 { --ph-bg: #FEF3C7; --ph-fg: #92400E; --ph-dot: #B45309; --ph-tint: #FFFBEB; --ph-border: #FDE68A; }
.sta-ph-4 { --ph-bg: #EDE9FE; --ph-fg: #5B21B6; --ph-dot: #6D28D9; --ph-tint: #F5F3FF; --ph-border: #DDD6FE; }
.sta-phase-chip { display: inline-flex; align-items: center; gap: 6px; height: 26px; padding: 0 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; background: var(--ph-bg); color: var(--ph-fg); }
.sta-phase-chip::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--ph-dot); flex-shrink: 0; }

.sta-stepper { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 8px; }
.sta-stepper li { display: inline-flex; align-items: center; gap: 8px; height: 34px; padding: 0 14px; border-radius: 999px; font-size: 13px; background: var(--ph-bg); color: var(--ph-fg); }
.sta-stepper li::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--ph-dot); }
.sta-stepper li[aria-current] { background: var(--ph-dot); color: #FFFFFF; font-weight: 600; }
.sta-stepper li[aria-current]::before { display: none; }

.sta-grid-kpi { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
.sta-grid-phase { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
.sta-grid-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr)); gap: 16px; }
.sta-kpi { background: #FFFFFF; border: 1px solid var(--sta-border); border-radius: var(--sta-radius-tile); padding: 16px 18px; display: flex; flex-direction: column; gap: 6px; }
.sta-kpi-label { font-size: 13px; color: var(--sta-text-2); }
.sta-kpi-value { font-family: var(--sta-font-heading); font-size: 30px; font-weight: 700; line-height: 1.1; }
.sta-kpi-hint { font-size: 12px; color: var(--sta-text-2); }
.sta-tone-submitted { color: #166534; }
.sta-tone-awaiting { color: #92400E; }
.sta-tone-draft { color: #1E3A8A; }
.sta-tone-none { color: var(--sta-text-3); }
.sta-tone-warn { color: var(--sta-warn); }

.sta-phase-card { background: #FFFFFF; border: 1px solid var(--sta-border); border-radius: var(--sta-radius-tile); padding: 16px; display: flex; flex-direction: column; gap: 6px; }
.sta-phase-card.is-active { border-color: var(--sta-primary); box-shadow: 0 0 0 3px #DCE6F2; }
.sta-phase-card-name { font-family: var(--sta-font-heading); font-weight: 600; font-size: 17px; }

.sta-band-blue { --band-head: #EFF6FF; --band-body: #F8FBFF; --band-border: #BFDBFE; --band-text: #1E40AF; --band-icon: #3B82F6; }
.sta-band-indigo { --band-head: #EEF2FF; --band-body: #F7F8FF; --band-border: #C7D2FE; --band-text: #3730A3; --band-icon: #4338CA; }
.sta-band-violet { --band-head: #F5F3FF; --band-body: #FAF9FF; --band-border: #DDD6FE; --band-text: #5B21B6; --band-icon: #8B5CF6; }
.sta-band-teal { --band-head: #F0FDFA; --band-body: #F7FEFC; --band-border: #99F6E4; --band-text: #115E59; --band-icon: #115E59; }
.sta-band-amber { --band-head: #FFFBEB; --band-body: #FFFDF5; --band-border: #FDE68A; --band-text: #92400E; --band-icon: #D97706; }
.sta-band-slate { --band-head: #F1F5F9; --band-body: #F8FAFC; --band-border: #CBD5E1; --band-text: #334155; --band-icon: #475569; }
.sta-band-rose { --band-head: #FFF1F2; --band-body: #FFF8F9; --band-border: #FECDD3; --band-text: #9F1239; --band-icon: #E11D48; }
.sta-band-fuchsia { --band-head: #FDF4FF; --band-body: #FEF9FF; --band-border: #F5D0FE; --band-text: #86198F; --band-icon: #86198F; }
.sta-band-cyan { --band-head: #ECFEFF; --band-body: #F6FEFF; --band-border: #A5F3FC; --band-text: #155E75; --band-icon: #0E7490; }
.sta-band-green { --band-head: #F0FDF4; --band-body: #F7FEF9; --band-border: #BBF7D0; --band-text: #166534; --band-icon: #16A34A; }
.sta-band { background: var(--band-body); border: 1px solid var(--band-border); border-radius: var(--sta-radius-card); }
.sta-band > .sta-card-head, .sta-band-head { background: var(--band-head); border-bottom: 1px solid var(--band-border); border-radius: 13px 13px 0 0; }
.sta-band .sta-card-title { color: var(--band-text); }
.sta-band-icon { width: 32px; height: 32px; border-radius: 9px; background: var(--band-icon); color: #FFFFFF; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sta-band-icon svg { width: 18px; height: 18px; }
.sta-band-strip { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: var(--sta-radius-tile); background: var(--band-head); }
.sta-band-strip h2 { margin: 0; font-family: var(--sta-font-heading); font-size: 19px; font-weight: 600; color: var(--band-text); }
/* Filament sections that carry a band (person form, game form) */
.fi-section.sta-band { background: var(--band-body); border-color: var(--band-border); }
.fi-section.sta-band > .fi-section-header { background: var(--band-head); border-bottom: 1px solid var(--band-border); border-radius: 13px 13px 0 0; padding: 18px 20px; }
.fi-section.sta-band .fi-section-header-heading { color: var(--band-text); font-size: 19px; }
.fi-section.sta-band .fi-section-header .fi-icon { width: 32px; height: 32px; padding: 7px; border-radius: 9px; background: var(--band-icon); color: #FFFFFF; }

.sta-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--sta-avatar); color: #FFFFFF; display: inline-flex; align-items: center; justify-content: center; font-family: var(--sta-font-heading); font-weight: 700; font-size: 13px; flex-shrink: 0; }
.sta-avatar-sm { width: 24px; height: 24px; font-size: 10px; }
.sta-avatar-lg { width: 56px; height: 56px; font-size: 18px; }
.sta-avatar-xl { width: 64px; height: 64px; font-size: 22px; }
.sta-logo { display: inline-flex; width: 54px; height: 34px; padding: 3px; box-sizing: border-box; border-radius: 8px; background: #FFFFFF; border: 1px solid var(--sta-border); align-items: center; justify-content: center; flex-shrink: 0; }
.sta-logo img { width: 100%; height: 100%; object-fit: contain; }
.sta-logo-sm { width: 38px; height: 24px; }
.sta-logo-lg { width: 90px; height: 56px; border-radius: var(--sta-radius-tile); }
.sta-who { display: flex; align-items: center; gap: 12px; min-width: 0; }
.sta-who-text { display: flex; flex-direction: column; min-width: 0; }
.sta-who-name { font-weight: 600; }
.sta-who-sub { font-size: 12px; color: var(--sta-text-2); }

.sta-meter { display: flex; flex-direction: column; gap: 6px; }
.sta-meter-row { display: flex; justify-content: space-between; font-size: 13px; }
.sta-meter-label { font-weight: 600; }
.sta-meter-value { font-weight: 600; color: var(--sta-text-2); }
.sta-meter-track { height: 8px; border-radius: 999px; background: var(--sta-border); overflow: hidden; }
.sta-meter-bar { height: 8px; border-radius: 999px; background: var(--meter, var(--sta-primary)); }
.sta-meter.is-over .sta-meter-bar { background: #C2410C; }
.sta-meter.is-over .sta-meter-value { color: var(--sta-warn); }

.sta-seg { display: inline-flex; flex-wrap: wrap; background: #F1F5F9; border-radius: var(--sta-radius-ctl); padding: 4px; gap: 4px; }
.sta-seg > button, .sta-seg > a { min-height: 36px; padding: 0 14px; border: 0; border-radius: 8px; background: transparent; color: var(--sta-text-3); font: 600 13px Inter, ui-sans-serif, sans-serif; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; text-decoration: none; }
.sta-seg > [aria-pressed='true'], .sta-seg > [aria-current='page'] { background: #FFFFFF; color: var(--sta-text); box-shadow: 0 1px 2px rgba(15, 23, 42, .08); }

.sta-btn { min-height: var(--sta-target); padding: 0 16px; border-radius: var(--sta-radius-ctl); border: 1px solid var(--sta-border-strong); background: #FFFFFF; color: var(--sta-text); font: 600 14px Inter, ui-sans-serif, sans-serif; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; cursor: pointer; }
.sta-btn-primary { border: 0; background: var(--sta-primary); color: #FFFFFF !important; }
.sta-btn-primary:hover { background: var(--sta-primary-hover); }
.sta-btn-outline { border-color: var(--sta-primary); color: var(--sta-primary); }
.sta-btn-danger { border-color: #FCA5A5; color: #991B1B; }
.sta-btn:focus-visible, .sta-seg > :focus-visible, .sta-input:focus-visible { outline: 2px solid var(--sta-primary); outline-offset: 2px; }

.sta-input { min-height: var(--sta-target); padding: 0 12px; border: 1px solid var(--sta-border-strong); border-radius: var(--sta-radius-ctl); font: 14px Inter, ui-sans-serif, sans-serif; background: #FFFFFF; color: var(--sta-text); box-sizing: border-box; }
.sta-input-num { width: 88px; font-weight: 600; font-size: 16px; }
textarea.sta-input { padding: 10px 12px; min-height: 96px; line-height: 1.5; width: 100%; }
.sta-field { display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600; }

.sta-banner { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 14px 18px; border-radius: var(--sta-radius-tile); border: 1px solid; }
.sta-banner-info { background: #EFF6FF; color: #1E3A8A; border-color: #BFDBFE; }
.sta-banner-warn { background: #FFF7ED; color: var(--sta-warn); border-color: #FED7AA; }
.sta-banner-ok { background: #DCFCE7; color: #166534; border-color: #BBF7D0; }

.sta-split { display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start; }
.sta-main { flex: 999 1 520px; min-width: 0; display: flex; flex-direction: column; gap: 20px; }
.sta-aside { flex: 1 1 300px; min-width: 0; display: flex; flex-direction: column; gap: 16px; }

.sta-tag { display: inline-flex; align-items: center; height: 28px; padding: 0 10px; border-radius: 8px; background: var(--sta-selected); color: var(--sta-primary); font-weight: 600; font-size: 13px; }
.sta-chip-person { display: inline-flex; align-items: center; gap: 8px; height: 36px; padding: 0 4px 0 6px; border-radius: 8px; background: var(--sta-selected); color: var(--sta-text); font-size: 13px; }
.sta-chip-person.is-bad { background: #FEF2F2; box-shadow: inset 0 0 0 1px #FCA5A5; }
.sta-chip-person button { width: 28px; height: 28px; border: 0; border-radius: 6px; background: transparent; color: var(--sta-text-2); cursor: pointer; }
.sta-token-field { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; min-height: 48px; padding: 6px 8px; border: 1px solid var(--sta-border-strong); border-radius: var(--sta-radius-ctl); background: #FFFFFF; }
.sta-token-field.is-error { border-color: #DC2626; }
.sta-token-field select { flex: 1 1 160px; min-width: 140px; min-height: 36px; border: 0; background: transparent; font: 14px Inter, ui-sans-serif, sans-serif; }

.sta-list { list-style: none; margin: 0; padding: 0; }
.sta-list-item { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 14px; padding: 14px 20px; border-top: 1px solid var(--sta-row); }
.sta-list-item:first-child { border-top: 0; }

@media (max-width: 640px) {
    .sta-card-head, .sta-card-body, .sta-card-foot { padding-left: 16px; padding-right: 16px; }
    .fi-header-heading { font-size: 24px; }
}
```

- [ ] **Step 5: Register the theme with Vite**

In `vite.config.js`, change the `input` line to:

```js
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament/sta/theme.css'],
```

- [ ] **Step 6: Create the helper and the avatar provider**

Create `app/Support/Sta.php`:

```php
<?php

namespace App\Support;

/** Canvas design tokens and small display helpers shared by Blade views and PDFs (spec 17). */
final class Sta
{
    public const FONTS_URL = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap';

    /** Association colours of the canvas (docs/design/canvas/project/Main.dc.html, the LC map). */
    public const AVATAR_COLOURS = ['#9A3412', '#3730A3', '#15803D', '#86198F', '#0F766E', '#BE185D', '#374151', '#854D0E', '#4D7C0F', '#0369A1'];

    public static function initials(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);

        return mb_strtoupper(implode('', array_map(fn (string $w) => mb_substr($w, 0, 1), array_slice($words, 0, 2)))) ?: '?';
    }

    public static function avatarColour(string $key): string
    {
        return self::AVATAR_COLOURS[crc32($key) % count(self::AVATAR_COLOURS)];
    }
}
```

Create `app/Support/InitialsAvatarProvider.php`:

```php
<?php

namespace App\Support;

use App\Models\Association;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/** Canvas-style initials circles, drawn locally (Filament's default asks ui-avatars.com). */
final class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        if ($record instanceof Association && filled($record->code)) {
            return self::svg($record->code, Sta::avatarColour($record->code));
        }

        return self::svg(Sta::initials(Filament::getNameForDefaultAvatar($record)));
    }

    public static function svg(string $text, string $fill = '#2B4C7E'): string
    {
        $size = mb_strlen($text) > 2 ? 18 : 24;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" rx="32" fill="'.$fill.'"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" font-family="Inter, Arial, sans-serif" font-size="'.$size.'" font-weight="700" fill="#FFFFFF">'.e($text).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
```

- [ ] **Step 7: Add the brand and role views**

Create `resources/views/filament/brand.blade.php`:

```blade
<span class="sta-brand">
    <span class="sta-brand-logo"><img src="{{ asset('images/boc-logo.webp') }}" alt="Bahrain Olympic Committee logo"></span>
    <span class="sta-brand-text">
        <span class="sta-brand-org">Bahrain Olympic Committee</span>
        <span class="sta-brand-app">Sports Entry</span>
        <span class="sta-brand-panel">{{ filament()->getId() === 'portal' ? 'Association portal' : 'Admin panel' }}</span>
    </span>
</span>
```

Create `resources/views/filament/sidebar-role.blade.php`:

```blade
@auth
    <p class="sta-user-role">{{ auth()->user()->role->label() }}</p>
@endauth
```

- [ ] **Step 8: Configure both panels**

In `app/Providers/Filament/AdminPanelProvider.php`, add these imports:

```php
use App\Support\InitialsAvatarProvider;
use App\Support\Sta;
use Filament\Enums\UserMenuPosition;
use Filament\FontProviders\GoogleFontProvider;
use Filament\View\PanelsRenderHook;
```

Remove the `AccountWidget` and `FilamentInfoWidget` imports. Then replace everything from `->brandName('STA Admin')` through the closing `])` of `->widgets([...])` with:

```php
            ->brandName('BOC Sports Entry')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('3.5rem')
            ->colors([
                'primary' => Color::hex('#1F3A5F'),
                'gray' => Color::Slate,
                'success' => Color::Green,   // Submitted / Complete
                'warning' => Color::Orange,  // canvas warnings #9A3412 family
                'danger' => Color::Red,
                'info' => Color::Indigo,     // Draft / Male
                'pink' => Color::Pink,       // Female (event gender badge)
            ])
            ->font('Inter', url: Sta::FONTS_URL, provider: GoogleFontProvider::class)
            ->viteTheme('resources/css/filament/sta/theme.css')
            ->darkMode(false)
            ->sidebarWidth('15.5rem')
            ->userMenu(position: UserMenuPosition::Sidebar)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn () => view('filament.sidebar-role'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
```

In `PortalPanelProvider.php`, make the same change from `->brandName('Association Portal')` through `->widgets([...])`. Use the same imports, plus these two:

```php
use App\Enums\Role;
use Filament\Navigation\NavigationItem;
```

Keep the portal's own `discoverResources` / `discoverPages` / `discoverWidgets` paths (`Filament/Portal/...`). Right after the `->renderHook(...)` line, add:

```php
            ->navigationItems([
                NavigationItem::make('Association profile')
                    ->url(fn () => EditAssociationProfile::getUrl())
                    ->isActiveWhen(fn () => request()->is('portal/*/profile'))
                    ->sort(5)
                    ->visible(fn () => auth()->user()?->role === Role::AssocAdmin),
            ])
```

`Role::AssocAdmin` is the same rule as `EditAssociationProfile::canView`. Remove the portal's `->widgets([...])` call and its two imports as well.

- [ ] **Step 9: Give associations a logo avatar**

In `app/Models/Association.php`, add these imports:

```php
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Support\Facades\Storage;
```

Change the class line to `class Association extends Model implements HasAvatar` and add this method:

```php
    /** Tenant menu logo (spec 17: the portal menu shows the association's logo); without one, InitialsAvatarProvider draws the code. */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->logo_path ? Storage::disk(config('filesystems.private'))->temporaryUrl($this->logo_path, now()->addMinutes(5)) : null;
    }
```

- [ ] **Step 10: Put the menu in canvas order**

Make these one-line edits.

**Remove the menu groups:** delete the `$navigationGroup` line in `GameResource`, `ActivityLogResource` and `StaUserResource`.

**Admin order:** add `protected static ?int $navigationSort = N;` to each of these:

| Class | Sort | Other change |
|---|---|---|
| `GameResource` | 1 | |
| `AssociationResource` | 2 | |
| `SportResource` | 3 | add `protected static ?string $navigationLabel = 'Sports & events';` |
| `ClothingItemResource` | 4 | |
| admin `People\PersonResource` | 5 | change its `$navigationLabel` to `'People'` |
| `Pages\Reports` | 6 | |
| `ActivityLogResource` | 7 | |
| `StaUserResource` | 8 | add `protected static ?string $navigationLabel = 'STA users';` |
| `Pages\Settings` | 9 | |
| `ExtraFieldResource` | 10 | |

**Portal order:** `Games` already has 1, and the Association profile item has 5. Add `$navigationSort` to these:

| Class | Sort |
|---|---|
| `Portal\Resources\People\PersonResource` | 2 |
| `ImportPeople` | 3 |
| `MemberResource` | 4 |
| `InvitationResource` | 6 |
| `Portal\Resources\Sports\SportResource` | 7 |

- [ ] **Step 11: Make tests ignore the Vite manifest**

Replace the class body in `tests/TestCase.php` with:

```php
abstract class TestCase extends BaseTestCase
{
    /** Panels now load a Vite theme; tests never need the built assets. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
```

- [ ] **Step 12: Run the tests and the build**

Run `php vendor/bin/pest tests/Feature/Ui/ShellTest.php`. Expected: 5 passed.

Run `php vendor/bin/pest`. Expected: all green.

Run `npm install && npm run build`. Expected:
- `public/build/manifest.json` contains the key `resources/css/filament/sta/theme.css`;
- `public/build/assets/theme-*.css` contains `.sta-pill-submitted` and `.fi-sidebar`.

- [ ] **Step 13: Commit**

```bash
git add resources/css/filament public/images/boc-logo.webp app/Support resources/views/filament/brand.blade.php resources/views/filament/sidebar-role.blade.php vite.config.js tests/TestCase.php tests/Feature/Ui/ShellTest.php app/Providers/Filament app/Models/Association.php app/Filament
git commit -m "feat: STA theme, navy shell with BOC brand, canvas menu order (M5)"
```

---

### Task 2: Shared Blade components and display maps

**Files:**
- Create:
  - `resources/views/components/sta/phase-chip.blade.php`
  - `resources/views/components/sta/pill.blade.php`
  - `resources/views/components/sta/kpi.blade.php`
  - `resources/views/components/sta/card.blade.php`
  - `resources/views/components/sta/phase-card.blade.php`
  - `resources/views/components/sta/assoc-avatar.blade.php`
  - `resources/views/components/sta/person-avatar.blade.php`
  - `resources/views/components/sta/meter.blade.php`
  - `resources/views/components/sta/stepper.blade.php`
  - `resources/views/components/sta/heading.blade.php`
  - `tests/Feature/Ui/ComponentsTest.php`
- Modify: `app/Support/Sta.php`

**Interfaces:**
- Consumes: the Task 1 CSS classes, `Sta::initials` and `Sta::avatarColour`.
- Produces:
  - `Sta::CELL_LABELS`: `array<string,string>` with the keys `submitted|awaiting|draft|overdue|no_response`
  - `Sta::cellPill(string $status): string` (returns a pill key)
  - `Sta::submissionPill(SubmissionStatus $s): string`
  - `Sta::phaseStatus(GamePhase $p): array{0: string, 1: string}`: `[label, pillKey]`
  - `Sta::daysLeft(?CarbonInterface $deadline): ?string`
  - `Sta::heading(string $title, ?int $phase = null, ?string $phaseLabel = null, ?string $pill = null, ?string $pillLabel = null): HtmlString`
  - these Blade components:
    - `<x-sta.phase-chip :phase :label?>`
    - `<x-sta.pill :status>` (slot = the label)
    - `<x-sta.kpi :label :value :hint? :tone?>`
    - `<x-sta.card :heading? :description? :band?>` (slots `icon`, `actions`, `footer`)
    - `<x-sta.phase-card :phase :name :status :status-label :meta? :active?>`
    - `<x-sta.assoc-avatar :association :size?>` (`sm|md|lg`)
    - `<x-sta.person-avatar :name :size?>` (`sm|md|lg|xl`)
    - `<x-sta.meter :label :value :max?>`
    - `<x-sta.stepper :game :current>`
    - `<x-sta.heading :title :phase? :phase-label? :pill? :pill-label?>`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ui/ComponentsTest.php`:

```php
<?php

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\Game;
use App\Support\Sta;
use Illuminate\Support\Facades\Blade;

it('renders the phase chip, pill, KPI tile and meter with the canvas classes', function () {
    $html = Blade::render('<x-sta.phase-chip :phase="3" /><x-sta.pill status="submitted">Submitted</x-sta.pill>'
        .'<x-sta.kpi label="Submitted to STA" value="31" hint="of 50 associations" tone="submitted" />'
        .'<x-sta.meter label="Male participants" :value="5" :max="4" />');

    expect($html)->toContain('sta-phase-chip sta-ph-3')->toContain('>Phase 3<')
        ->toContain('sta-pill sta-pill-submitted')->toContain('Submitted to STA')->toContain('sta-tone-submitted')
        ->toContain('is-over')->toContain('5 of 4')->toContain('aria-valuemax="4"');
});

it('maps phase and submission states to canvas pills', function () {
    $game = Game::factory()->create();
    $game->phase(1)->update(['enabled' => false]);
    $game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open]);
    $game->refresh();

    expect(Sta::phaseStatus($game->phase(1)))->toBe(['Skipped', 'skipped'])
        ->and(Sta::phaseStatus($game->phase(3)))->toBe(['Open', 'open'])
        ->and(Sta::submissionPill(SubmissionStatus::AwaitingAssocAdmin))->toBe('awaiting')
        ->and(Sta::cellPill('no_response'))->toBe('none')
        ->and(Sta::daysLeft(now()->addDays(6)->addHour()))->toBe('6 days left')
        ->and(Sta::daysLeft(now()->subDay()))->toBe('Overdue');
});

it('draws an association avatar from its code when there is no logo', function () {
    $a = Association::factory()->create(['name' => 'Bahrain Judo Federation', 'code' => 'BJF']);

    expect(Blade::render('<x-sta.assoc-avatar :association="$a" />', ['a' => $a]))
        ->toContain('sta-avatar')->toContain('>BJF<')->toContain(Sta::avatarColour('BJF'));
});

it('marks the current step and skipped phases in the stepper', function () {
    $game = Game::factory()->create();
    $game->phase(1)->update(['enabled' => false]);

    $html = Blade::render('<x-sta.stepper :game="$g" :current="2" />', ['g' => $game->fresh('phases')]);

    expect($html)->toContain('aria-current="step"')->toContain('1 · Entry by Sport — skipped')->toContain('4 · Short List');
});

it('builds a page heading with chip and pill', function () {
    expect((string) Sta::heading('Long List', 3, 'Phase 3', 'draft', 'Draft'))
        ->toContain('Long List')->toContain('sta-ph-3')->toContain('sta-pill-draft');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run `php vendor/bin/pest tests/Feature/Ui/ComponentsTest.php`.

Expected: FAIL with `Unable to locate a class or view for component [sta.phase-chip]`.

- [ ] **Step 3: Extend `App\Support\Sta`**

Add these imports:

```php
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\GamePhase;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
```

Add these members to the class:

```php
    /** Game dashboard cell statuses (GameProgress::status()) and their canvas labels. */
    public const CELL_LABELS = ['submitted' => 'Submitted', 'awaiting' => 'Awaiting admin', 'draft' => 'In progress',
        'overdue' => 'Overdue', 'no_response' => 'No response'];

    public static function cellPill(string $status): string
    {
        return ['submitted' => 'submitted', 'awaiting' => 'awaiting', 'draft' => 'draft', 'overdue' => 'overdue'][$status] ?? 'none';
    }

    public static function submissionPill(SubmissionStatus $s): string
    {
        return match ($s) {
            SubmissionStatus::Submitted => 'submitted',
            SubmissionStatus::AwaitingAssocAdmin => 'awaiting',
            SubmissionStatus::Draft => 'draft',
        };
    }

    /** @return array{0: string, 1: string} [label, pill key] */
    public static function phaseStatus(GamePhase $p): array
    {
        if (! $p->enabled) {
            return ['Skipped', 'skipped'];
        }

        return match ($p->status) {
            PhaseStatus::Open => ['Open', 'open'],
            PhaseStatus::Closed => ['Closed', 'closed'],
            PhaseStatus::Draft => ['Not opened', 'none'],
        };
    }

    public static function daysLeft(?CarbonInterface $deadline): ?string
    {
        if ($deadline === null) {
            return null;
        }
        if ($deadline->isPast()) {
            return 'Overdue';
        }
        $days = (int) floor(now()->diffInDays($deadline));

        return match ($days) {
            0 => 'Due today',
            1 => '1 day left',
            default => $days.' days left',
        };
    }

    /** Page heading with optional phase chip and status pill (rendered inside Filament's <h1>). */
    public static function heading(string $title, ?int $phase = null, ?string $phaseLabel = null, ?string $pill = null, ?string $pillLabel = null): HtmlString
    {
        return new HtmlString(Blade::render(
            '<x-sta.heading :title="$title" :phase="$phase" :phase-label="$phaseLabel" :pill="$pill" :pill-label="$pillLabel" />',
            compact('title', 'phase', 'phaseLabel', 'pill', 'pillLabel')));
    }
```

- [ ] **Step 4: Create the components**

Create `resources/views/components/sta/phase-chip.blade.php`:

```blade
@props(['phase', 'label' => null])
<span {{ $attributes->class(['sta-phase-chip', 'sta-ph-'.$phase]) }}>{{ $label ?? 'Phase '.$phase }}</span>
```

Create `resources/views/components/sta/pill.blade.php`:

```blade
@props(['status'])
<span {{ $attributes->class(['sta-pill', 'sta-pill-'.$status]) }}>{{ $slot }}</span>
```

Create `resources/views/components/sta/kpi.blade.php`:

```blade
@props(['label', 'value', 'hint' => null, 'tone' => null])
<div {{ $attributes->class('sta-kpi') }}>
    <span class="sta-kpi-label">{{ $label }}</span>
    <span @class(['sta-kpi-value', 'sta-tone-'.$tone => $tone])>{{ $value }}</span>
    @if (filled($hint))<span class="sta-kpi-hint">{{ $hint }}</span>@endif
</div>
```

Create `resources/views/components/sta/card.blade.php`:

```blade
@props(['heading' => null, 'description' => null, 'band' => null, 'headingTag' => 'h2'])
<section {{ $attributes->class(['sta-card', 'sta-band sta-band-'.$band => $band]) }}>
    @if (filled($heading) || isset($actions))
        <div class="sta-card-head">
            <div class="sta-card-head-text">
                @if (filled($heading))
                    <{{ $headingTag }} class="sta-card-title">@isset($icon)<span class="sta-band-icon" aria-hidden="true">{{ $icon }}</span>@endisset{{ $heading }}</{{ $headingTag }}>
                @endif
                @if (filled($description))<span class="sta-card-sub">{{ $description }}</span>@endif
            </div>
            @isset($actions)<div class="sta-card-actions">{{ $actions }}</div>@endisset
        </div>
    @endif
    {{ $slot }}
    @isset($footer)<div class="sta-card-foot">{{ $footer }}</div>@endisset
</section>
```

Create `resources/views/components/sta/phase-card.blade.php`:

```blade
@props(['phase', 'name', 'status', 'statusLabel', 'meta' => null, 'active' => false])
<div {{ $attributes->class(['sta-phase-card', 'is-active' => $active]) }}>
    <div class="sta-row-between">
        <x-sta.phase-chip :phase="$phase" />
        <x-sta.pill :status="$status">{{ $statusLabel }}</x-sta.pill>
    </div>
    <span class="sta-phase-card-name">{{ $name }}</span>
    @if (filled($meta))<span class="sta-muted">{{ $meta }}</span>@endif
    {{ $slot }}
</div>
```

Create `resources/views/components/sta/assoc-avatar.blade.php`:

```blade
@props(['association', 'size' => 'md'])
@if ($association->logo_path)
    <span {{ $attributes->class(['sta-logo', 'sta-logo-'.$size => $size !== 'md']) }}>
        <img src="{{ \App\Filament\Shared\AttachmentActions::url($association->logo_path) }}" alt="{{ $association->name }} logo" loading="lazy">
    </span>
@else
    <span {{ $attributes->class(['sta-avatar', 'sta-avatar-'.$size => $size !== 'md']) }}
          style="background: {{ \App\Support\Sta::avatarColour($association->code ?? $association->name) }}" aria-hidden="true">{{ $association->code ?? \App\Support\Sta::initials($association->name) }}</span>
@endif
```

Create `resources/views/components/sta/person-avatar.blade.php`:

```blade
@props(['name', 'size' => 'md'])
<span {{ $attributes->class(['sta-avatar', 'sta-avatar-'.$size => $size !== 'md']) }} role="img" aria-label="Photo of {{ $name }}">{{ \App\Support\Sta::initials($name) }}</span>
```

Create `resources/views/components/sta/meter.blade.php`:

```blade
@props(['label', 'value', 'max' => null])
@php
    $over = $max !== null && $value > $max;
    $pct = $max !== null ? min(100, (int) round($value / max((int) $max, 1) * 100)) : 0;
@endphp
<div {{ $attributes->class(['sta-meter', 'is-over' => $over]) }}>
    <div class="sta-meter-row">
        <span class="sta-meter-label">{{ $label }}</span>
        <span class="sta-meter-value">{{ $value }}{{ $max !== null ? ' of '.$max : '' }}</span>
    </div>
    @if ($max !== null)
        <div class="sta-meter-track" role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="{{ $max }}" aria-valuenow="{{ $value }}">
            <div class="sta-meter-bar" style="width: {{ $pct }}%"></div>
        </div>
    @endif
</div>
```

Create `resources/views/components/sta/stepper.blade.php`:

```blade
@props(['game', 'current'])
<ol {{ $attributes->class('sta-stepper') }} aria-label="Phases">
    @foreach ($game->phases->sortBy('phase') as $p)
        <li class="sta-ph-{{ $p->phase }}" @if ($p->phase === (int) $current) aria-current="step" @endif>{{ $p->phase }} · {{ \App\Models\GamePhase::NAMES[$p->phase] }}@unless ($p->enabled) — skipped @endunless</li>
    @endforeach
</ol>
```

Create `resources/views/components/sta/heading.blade.php`:

```blade
@props(['title', 'phase' => null, 'phaseLabel' => null, 'pill' => null, 'pillLabel' => null])
<span class="sta-heading">{{ $title }}
    @if ($phase)<x-sta.phase-chip :phase="$phase" :label="$phaseLabel" />@endif
    @if ($pill)<x-sta.pill :status="$pill">{{ $pillLabel }}</x-sta.pill>@endif
</span>
```

- [ ] **Step 5: Run the tests**

Run `php vendor/bin/pest tests/Feature/Ui/ComponentsTest.php`. Expected: 5 passed.

- [ ] **Step 6: Commit**

```bash
git add resources/views/components/sta app/Support/Sta.php tests/Feature/Ui/ComponentsTest.php
git commit -m "feat: shared sta Blade components and canvas status maps (M5)"
```

---

### Task 3: Phase page chrome (header band, stepper) and the portal Games / Home cards

**Files:**
- Create:
  - `resources/views/filament/portal/partials/phase-top.blade.php`
  - `app/Filament/Portal/Pages/Home.php`
  - `tests/Feature/Ui/PhasePagesTest.php`
- Modify:
  - `app/Filament/Portal/Concerns/HandlesSubmission.php`
  - `app/Filament/Resources/Games/Pages/Concerns/StaEditsSubmission.php`
  - `app/Filament/Portal/Pages/Games.php`
  - `resources/views/filament/portal/pages/games.blade.php`
  - `app/Providers/Filament/PortalPanelProvider.php`
  - the first lines of the four phase views (`resources/views/filament/portal/pages/phase{1,2,3,4}-entry.blade.php`)

**Interfaces:**
- Consumes: `Sta::heading`, `Sta::phaseStatus`, `Sta::submissionPill`, `Sta::daysLeft`, `<x-sta.stepper>`, `<x-sta.card>`, `<x-sta.phase-card>`.
- Produces:
  - On every page that uses `HandlesSubmission`:
    - `getPageClasses(): array` (`['sta-phase-page', 'sta-ph-N']`)
    - `getHeading(): string|Htmlable`
    - `getSubheading(): ?string`
    - `getBreadcrumbs(): array`
    - `protected phaseHeadingTitle(): string`
  - `Games::forTenant(Association $tenant): Collection`. Each item is `{game: Game, rows: Collection}`, and each row is `array{phase:int, name:string, status:string, pill:string, deadline:?Carbon, submission:string, url:?string}`.
  - The view partial `filament.portal.partials.phase-top`, which takes `$pdfKey` (?string) and `$pdfLabel` (?string).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ui/PhasePagesTest.php`:

```php
<?php

use App\Enums\PhaseStatus;
use App\Filament\Portal\Pages\Games;
use App\Filament\Portal\Pages\Home;
use App\Filament\Portal\Pages\Phase1Entry;
use App\Filament\Resources\Games\Pages\StaPhase1Edit;
use App\Models\Association;
use App\Models\Game;
use App\Models\GameSport;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    $this->assoc = Association::factory()->create(['name' => 'Bahrain Weightlifting Federation', 'code' => 'BWF']);
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addDays(6)->addHour()]);
    GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Weightlifting'])->id]);
});

it('tints a phase page and shows chip, status, deadline and stepper', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());

    livewire(Phase1Entry::class, ['game' => $this->game->id])
        ->assertSeeHtml('sta-phase-page')->assertSeeHtml('sta-ph-1')
        ->assertSee('Entry by Sport')->assertSee('Phase 1 of 4 · Open')->assertSee('Draft · saved')
        ->assertSee('6 days left')->assertSeeHtml('aria-current="step"');
});

it('shows game phases as cards on the Games page and on the portal home', function () {
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());

    livewire(Games::class)->assertSeeHtml('sta-phase-card')->assertSee('Entry by Sport')->assertSee('No response')
        ->assertSeeHtml(Phase1Entry::getUrl(['game' => $this->game->id]));
    livewire(Home::class)->assertSee('Asian Indoor Games')->assertSeeHtml('sta-phase-card');
});

it('names the association in the STA edit heading', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    $s = Submission::for($this->game, $this->assoc, 1);

    livewire(StaPhase1Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $s->id])
        ->assertSee('Edit Bahrain Weightlifting Federation · Entry by Sport')->assertSeeHtml('sta-ph-1');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run `php vendor/bin/pest tests/Feature/Ui/PhasePagesTest.php`.

Expected: FAIL. `sta-phase-page` is not in the HTML, and `App\Filament\Portal\Pages\Home` does not exist.

- [ ] **Step 3: Add the phase chrome to `HandlesSubmission`**

Add these imports:

```php
use App\Filament\Portal\Pages\Games;
use App\Models\GamePhase;
use App\Support\Sta;
use Illuminate\Contracts\Support\Htmlable;
```

Add these methods to the trait:

```php
    /** Spec 17: phase pages wear their phase colour (theme: .sta-phase-page.sta-ph-N .fi-header). */
    public function getPageClasses(): array
    {
        return ['sta-phase-page', 'sta-ph-'.$this->submission->phase];
    }

    public function getHeading(): string|Htmlable
    {
        $p = $this->submission->phase;
        [$phaseState] = Sta::phaseStatus($this->gameModel->phase($p));
        $status = $this->submission->status;
        $saved = $status === SubmissionStatus::Draft && $this->submission->updated_at ? ' · saved '.$this->submission->updated_at->format('H:i') : '';

        return Sta::heading($this->phaseHeadingTitle(), $p, "Phase {$p} of 4 · {$phaseState}", Sta::submissionPill($status), $status->label().$saved);
    }

    public function getSubheading(): ?string
    {
        $deadline = $this->submission->gamePhase()->effectiveDeadline($this->submission);

        return $deadline ? 'Deadline '.$deadline->format('j M Y H:i').' · '.Sta::daysLeft($deadline) : 'No deadline set';
    }

    protected function phaseHeadingTitle(): string
    {
        return GamePhase::NAMES[$this->submission->phase];
    }

    public function getBreadcrumbs(): array
    {
        return [Games::getUrl() => 'Games', $this->gameModel->name];
    }
```

- [ ] **Step 4: Override the title and breadcrumbs for STA edits**

In `StaEditsSubmission`, add these methods. A trait's own methods win over the methods of the traits it uses, so these replace the ones from `HandlesSubmission`:

```php
    protected function phaseHeadingTitle(): string
    {
        return 'Edit '.$this->submission->association->name.' · '.GamePhase::NAMES[static::PHASE];
    }

    public function getBreadcrumbs(): array
    {
        return [
            GameResource::getUrl('dashboard', ['record' => $this->record]) => $this->record->name,
            GameResource::getUrl('submission', ['record' => $this->record, 'submission' => $this->submission]) => $this->submission->association->name,
            'Edit',
        ];
    }
```

- [ ] **Step 5: Create the shared top partial**

Create `resources/views/filament/portal/partials/phase-top.blade.php`:

```blade
{{-- Phase pages: stepper, read-only reason and the signed PDF link. Pass $pdfKey (pdf_paths key or null) and $pdfLabel. --}}
<x-sta.stepper :game="$this->gameModel" :current="$this->submission->phase" />

@if ($readOnlyReason)
    <div class="sta-banner sta-banner-warn" role="status">{{ $readOnlyReason }}</div>
@endif

@php $paths = $this->submission->pdf_paths ?? []; @endphp
@if ($pdfKey && $this->submission->status === \App\Enums\SubmissionStatus::Submitted && isset($paths[$pdfKey]))
    <div><a class="sta-btn" target="_blank" href="{{ \App\Filament\Shared\AttachmentActions::url($paths[$pdfKey]) }}">{{ $pdfLabel }}</a></div>
@endif
```

In each of the four phase views, replace the opening block with one `@include`. The block runs from the `Deadline:` paragraph through the `$readOnlyReason` banner, and also covers the PDF link block where the view has one.
- `phase1-entry`: `@include('filament.portal.partials.phase-top', ['pdfKey' => 'entry_by_sport', 'pdfLabel' => 'View Entry by Sport PDF'])`
- `phase2-entry`: `@include('filament.portal.partials.phase-top', ['pdfKey' => null, 'pdfLabel' => null])`. Phase 2 keeps its per-sport links.
- `phase3-entry`: `@include('filament.portal.partials.phase-top', ['pdfKey' => 'long_list', 'pdfLabel' => 'View Long List PDF'])`
- `phase4-entry`: `@include('filament.portal.partials.phase-top', ['pdfKey' => 'short_list', 'pdfLabel' => 'View Short List PDF'])`

Tasks 4–6 rewrite the rest of these views. The PDF link labels stay exactly as they are, because three tests assert them.

- [ ] **Step 6: Make the Games data reusable and add pills**

In `app/Filament/Portal/Pages/Games.php`, add `use App\Models\Association;` and `use App\Support\Sta;`. Then replace `getGamesProperty()` with:

```php
    /** @return Collection<int, array{game: Game, rows: Collection}> */
    public function getGamesProperty(): Collection
    {
        return static::forTenant(Filament::getTenant());
    }

    /** @return Collection<int, array{game: Game, rows: Collection}> published games the association is invited to, with a row per enabled phase */
    public static function forTenant(Association $tenant): Collection
    {
        return Game::whereNotNull('published_at')->whereHas('associations', fn ($q) => $q->whereKey($tenant->getKey()))
            ->with('phases')->orderBy('start_date')->get()
            ->map(fn (Game $g) => ['game' => $g, 'rows' => $g->phases->where('enabled', true)->sortBy('phase')->map(function (GamePhase $p) use ($g, $tenant) {
                $sub = Submission::withoutGlobalScope('association')
                    ->where(['game_id' => $g->id, 'association_id' => $tenant->id, 'phase' => $p->phase])->first();
                $page = match ($p->phase) {
                    1 => Phase1Entry::class,
                    2 => Phase2Entry::class,
                    3 => Phase3Entry::class,
                    4 => Phase4Entry::class,
                    default => null,
                };

                return [
                    'phase' => $p->phase,
                    'name' => GamePhase::NAMES[$p->phase],
                    'status' => ucfirst($p->status->value),
                    'pill' => $sub ? Sta::submissionPill($sub->status) : 'none',
                    'deadline' => $p->effectiveDeadline($sub),
                    'submission' => $sub?->status->label() ?? 'No response',
                    'url' => $page && $p->status !== PhaseStatus::Draft ? $page::getUrl(['game' => $g->id]) : null,
                ];
            })]);
    }
```

- [ ] **Step 7: Rewrite the Games view**

Replace `resources/views/filament/portal/pages/games.blade.php` with:

```blade
<x-filament-panels::page>
    @forelse ($this->games as $item)
        @php $g = $item['game']; @endphp
        <x-sta.card :heading="$g->name" :description="$g->start_date->format('j M Y').' – '.$g->end_date->format('j M Y')" wire:key="game-{{ $g->id }}">
            <div class="sta-card-body sta-grid-phase">
                @foreach ($item['rows'] as $row)
                    <x-sta.phase-card :phase="$row['phase']" :name="$row['name']" :status="$row['pill']" :status-label="$row['submission']"
                        :meta="$row['status'].($row['deadline'] ? ' · Deadline '.$row['deadline']->format('j M Y H:i') : '')"
                        :active="$row['status'] === 'Open'">
                        @if ($row['url'])
                            <a href="{{ $row['url'] }}" class="sta-btn sta-btn-outline" style="align-self: flex-start"
                               aria-label="Open Phase {{ $row['phase'] }} {{ $row['name'] }} for {{ $g->name }}">Open</a>
                        @endif
                    </x-sta.phase-card>
                @endforeach
            </div>
        </x-sta.card>
    @empty
        <p class="sta-muted">No games yet.</p>
    @endforelse
</x-filament-panels::page>
```

- [ ] **Step 8: Add the portal home**

Create `app/Filament/Portal/Pages/Home.php`:

```php
<?php

namespace App\Filament\Portal\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Support\Collection;

/** Portal home (canvas menu "Home"): the same game cards as the Games page. */
class Home extends Dashboard
{
    protected string $view = 'filament.portal.pages.games';

    protected static ?string $title = 'Home';

    protected static ?string $navigationLabel = 'Home';

    public function getGamesProperty(): Collection
    {
        return Games::forTenant(Filament::getTenant());
    }
}
```

In `PortalPanelProvider`, delete `->pages([Dashboard::class])` and the `Filament\Pages\Dashboard` import. `discoverPages` registers `Home`, and its route is `/` because it is a Dashboard subclass.

- [ ] **Step 9: Run the tests**

Run `php vendor/bin/pest tests/Feature/Ui/PhasePagesTest.php`. Expected: 3 passed.

Run `php vendor/bin/pest tests/Feature/Phase1EntryTest.php tests/Feature/Phase2EntryTest.php tests/Feature/Phase3EntryTest.php tests/Feature/Phase4EntryTest.php tests/Feature/StaSubmissionEditTest.php tests/Feature/PanelAccessTest.php tests/Feature/EntryBySportPdfTest.php tests/Feature/LongListPdfTest.php tests/Feature/ShortListPdfTest.php`. Expected: all pass. "This phase is closed." and the "View … PDF" labels still render through the partial.

- [ ] **Step 10: Commit**

```bash
git add app/Filament/Portal app/Filament/Resources/Games/Pages/Concerns/StaEditsSubmission.php resources/views/filament/portal app/Providers/Filament/PortalPanelProvider.php tests/Feature/Ui/PhasePagesTest.php
git commit -m "feat: phase-coloured page headers, phase stepper, portal game cards and Home (M5)"
```

---

### Task 4: Phase 1 note per answer, the Phase 1 layout and the Phase 2 Entry by Number layout

**Files:**
- Create:
  - `database/migrations/2026_11_16_000100_add_note_to_interest_answers.php`
  - `tests/Feature/Ui/Phase1NoteAndPhase2Test.php`
- Modify:
  - `app/Models/InterestAnswer.php`
  - `app/Filament/Shared/Phases/EditsPhase1.php`
  - `resources/views/filament/portal/pages/phase1-entry.blade.php`
  - `resources/views/filament/portal/pages/phase2-entry.blade.php`
  - `app/Filament/Resources/Games/Pages/SubmissionView.php` (`phase1Rows`)
  - `resources/views/filament/resources/games/pages/submission-view.blade.php` (the Phase 1 table)
  - `resources/css/filament/sta/theme.css`

**Interfaces:**
- Consumes: `<x-sta.card>`, `<x-sta.meter>`, `<x-sta.pill>`, and the partial from Task 3.
- Produces:
  - the column `interest_answers.note` (`varchar(255)`, nullable)
  - `InterestAnswer::$fillable` now includes `note`
  - `EditsPhase1::$notes` (`array<int|string, ?string>`)
  - `SubmissionView::phase1Rows()` rows now also carry `note`
  - Task 13 reads `InterestAnswer->note` for the PDF

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ui/Phase1NoteAndPhase2Test.php`:

```php
<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Filament\Portal\Pages\Phase1Entry;
use App\Filament\Portal\Pages\Phase2Entry;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\InterestAnswer;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    $this->assoc = Association::factory()->create(['code' => 'BWF']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id, 'p2_max_men' => 2,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    actingInPortal(User::factory()->assocEditor($this->assoc)->create());
});

it('saves an optional note with each Phase 1 answer', function () {
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);

    livewire(Phase1Entry::class, ['game' => $this->game->id])
        ->set("answers.{$this->judo->id}", 'maybe')->set("notes.{$this->judo->id}", 'Depends on the national championship')
        ->call('save')->assertHasNoErrors();

    expect(InterestAnswer::sole()->note)->toBe('Depends on the national championship');
    livewire(Phase1Entry::class, ['game' => $this->game->id])
        ->assertSet("notes.{$this->judo->id}", 'Depends on the national championship')
        ->assertSeeHtml('role="radiogroup"')->assertSeeHtml('name="answer-'.$this->judo->id.'"');
});

it('refuses a Phase 1 note longer than 255 characters', function () {
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);

    livewire(Phase1Entry::class, ['game' => $this->game->id])
        ->set("answers.{$this->judo->id}", 'yes')->set("notes.{$this->judo->id}", str_repeat('a', 256))
        ->call('save')->assertHasErrors(["notes.{$this->judo->id}"]);

    expect(InterestAnswer::count())->toBe(0);
});

it('lays out Phase 2 with participant meters, event entry totals and the policy card', function () {
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->judo->update(['entry_policy' => 'One athlete per weight.']);
    GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id, 'p2_max_athletes' => 2,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => '-60kg', 'gender' => EventGender::Men])->id]);

    livewire(Phase2Entry::class, ['game' => $this->game->id])
        ->set("totals.{$this->judo->id}.male", '3')
        ->assertSee('Judo limits')->assertSee('Male participants')->assertSee('3 of 2')->assertSeeHtml('is-over')
        ->assertSee('Event entries')->assertSee('Entry policy')->assertSee('One athlete per weight.')
        ->assertSee('Up to 2 athletes')->assertSeeHtml('>Male</th>')->assertSeeHtml('>Female</th>');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run `php vendor/bin/pest tests/Feature/Ui/Phase1NoteAndPhase2Test.php`.

Expected: FAIL. `notes` is not a property, and "Judo limits" is not shown.

- [ ] **Step 3: Add the migration and the model field**

Create `database/migrations/2026_11_16_000100_add_note_to_interest_answers.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Spec 17: an optional note per Phase 1 answer, printed on the Entry by Sport PDF. */
    public function up(): void
    {
        Schema::table('interest_answers', function (Blueprint $t) {
            $t->string('note', 255)->nullable()->after('answer');
        });
    }

    public function down(): void
    {
        Schema::table('interest_answers', function (Blueprint $t) {
            $t->dropColumn('note');
        });
    }
};
```

In `app/Models/InterestAnswer.php`, change the fillable list to `protected $fillable = ['submission_id', 'game_sport_id', 'answer', 'note'];`.

- [ ] **Step 4: Load, validate and save the notes in `EditsPhase1`**

Add the property:

```php
    /** @var array<int|string, ?string> game_sport_id => optional note (spec 17, at most 255 characters) */
    public array $notes = [];
```

In `loadPhase()`, add after the `answers` line:

```php
        $this->notes = $this->submission->answers()->pluck('note', 'game_sport_id')->filter()->all();
```

In `save()`, insert this as the first statement inside the `try` block, before `$this->guardEditable();`:

```php
            $this->validate(['notes.*' => ['nullable', 'string', 'max:255']], ['notes.*.max' => 'A note can be at most 255 characters.']);
```

In the loop, replace the `InterestAnswer::updateOrCreate(...)` call with:

```php
                    $note = trim((string) ($this->notes[$gameSportId] ?? ''));
                    InterestAnswer::updateOrCreate(['submission_id' => $this->submission->id, 'game_sport_id' => $gameSportId],
                        ['answer' => $answer, 'note' => $note === '' ? null : $note]);
```

- [ ] **Step 5: Rewrite the Phase 1 view**

Replace `resources/views/filament/portal/pages/phase1-entry.blade.php` with:

```blade
<x-filament-panels::page>
    @include('filament.portal.partials.phase-top', ['pdfKey' => 'entry_by_sport', 'pdfLabel' => 'View Entry by Sport PDF'])

    <x-sta.card heading="Sports you were invited to" description="Tell STA whether you plan to take part. Numbers per event come in Phase 2 (Entry by Number).">
        <ul class="sta-list">
            @forelse ($this->sports as $gs)
                <li class="sta-list-item" wire:key="sport-{{ $gs->id }}">
                    <span class="sta-who-name" style="flex: 1 1 160px">{{ $gs->sport->name }}</span>
                    <div class="sta-seg" role="radiogroup" aria-label="{{ $gs->sport->name }}">
                        @foreach (['yes' => 'Yes', 'no' => 'No', 'maybe' => 'Maybe'] as $value => $label)
                            <label>
                                <input type="radio" name="answer-{{ $gs->id }}" wire:model="answers.{{ $gs->id }}" value="{{ $value }}" @disabled($readOnlyReason)>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @unless (isset($answers[$gs->id]))
                        <x-sta.pill status="none">No response</x-sta.pill>
                    @endunless
                    <label class="sta-field" style="flex: 2 1 240px">
                        <span class="sta-sr-only">Note for {{ $gs->sport->name }}</span>
                        <input type="text" maxlength="255" class="sta-input" placeholder="Note (optional)" wire:model="notes.{{ $gs->id }}" @disabled($readOnlyReason)>
                    </label>
                    @error('notes.'.$gs->id)<p class="sta-error" role="alert">{{ $message }}</p>@enderror
                </li>
            @empty
                <li class="sta-list-item sta-muted">No sports for your association in this game.</li>
            @endforelse
        </ul>
    </x-sta.card>

    @unless ($readOnlyReason)
        <div><x-filament::button color="gray" wire:click="save">Save as draft</x-filament::button></div>
    @endunless
    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
```

- [ ] **Step 6: Rewrite the Phase 2 view**

Replace `resources/views/filament/portal/pages/phase2-entry.blade.php` with the code below. Keep every `wire:model`, every message lookup and every text the tests assert. The page must not contain the words "Men" or "Women".

```blade
<x-filament-panels::page>
    @include('filament.portal.partials.phase-top', ['pdfKey' => null, 'pdfLabel' => null])

    @php
        $paths = $this->submission->pdf_paths ?? [];
        $submitted = $this->submission->status === \App\Enums\SubmissionStatus::Submitted;
        $maleEv = \App\Enums\EventGender::Men;
        $femaleEv = \App\Enums\EventGender::Women;
        $mixedEv = \App\Enums\EventGender::Mixed;
        $msg = fn (string $key) => $errors->first($key) ?: ($liveErrors[$key] ?? null);
        $int = fn ($v) => is_numeric($v) ? (int) $v : 0;
    @endphp

    @forelse ($this->visibleSports() as $gs)
        @php
            $pm = $int($totals[$gs->id]['male'] ?? null);
            $pf = $int($totals[$gs->id]['female'] ?? null);
            $entries = [
                'male' => $gs->gameEvents->filter(fn ($ge) => $ge->event->gender === $maleEv)->sum(fn ($ge) => $int($counts[$ge->id] ?? null)),
                'female' => $gs->gameEvents->filter(fn ($ge) => $ge->event->gender === $femaleEv)->sum(fn ($ge) => $int($counts[$ge->id] ?? null)),
            ];
        @endphp
        <div class="sta-split" wire:key="sport-{{ $gs->id }}">
            <x-sta.card class="sta-main" :heading="$gs->sport->name" description="Enter how many athletes you plan to send to each event">
                <fieldset class="sta-fieldset is-tinted" wire:key="participants-{{ $gs->id }}">
                    <legend>
                        <span class="sta-legend-title">Total number of participants</span>
                        <span class="sta-muted">Count each athlete once, even if they enter several events.</span>
                    </legend>
                    @foreach (['male' => ['Male', $gs->p2_max_men], 'female' => ['Female', $gs->p2_max_women]] as $field => [$label, $max])
                        <label class="sta-inline-field" for="totals-{{ $gs->id }}-{{ $field }}">{{ $label }}@if ($max !== null) <span class="sta-muted">(up to {{ $max }})</span>@endif
                            <input id="totals-{{ $gs->id }}-{{ $field }}" type="number" min="0" max="999" class="sta-input sta-input-num"
                                wire:model.live.debounce.400ms="totals.{{ $gs->id }}.{{ $field }}" @disabled($readOnlyReason)>
                        </label>
                        @if ($m = $msg("totals.{$gs->id}.{$field}"))<p class="sta-error">{{ $m }}</p>@endif
                    @endforeach
                    <span class="sta-muted">Total {{ $pm + $pf }}@if ($gs->p2_max_total !== null) · male + female up to {{ $gs->p2_max_total }}@endif</span>
                    @foreach (['male', 'female', 'total'] as $k)
                        @if ($m = $msg("sport.{$gs->id}.{$k}"))<p class="sta-error" role="alert">{{ $m }}</p>@endif
                    @endforeach
                </fieldset>

                <div class="sta-scroll">
                    <table class="sta-table">
                        <thead>
                            <tr><th scope="col">Event</th><th scope="col" class="sta-c">Male</th><th scope="col" class="sta-c">Female</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($gs->gameEvents as $ge)
                                @php
                                    $g = $ge->event->gender;
                                    $eMsg = $msg('counts.'.$ge->id);
                                @endphp
                                <tr>
                                    <th scope="row">
                                        <span class="sta-who-text">
                                            <span>{{ $ge->event->name }}</span>
                                            @if ($ge->p2_max_athletes !== null)
                                                <span class="sta-allow">Up to {{ $ge->p2_max_athletes }} {{ \Illuminate\Support\Str::plural('athlete', $ge->p2_max_athletes) }}</span>
                                            @endif
                                        </span>
                                    </th>
                                    @if ($g === $mixedEv)
                                        <td colspan="2" class="sta-c">
                                            <input type="number" min="0" aria-label="{{ $ge->event->name }} (Mixed)" class="sta-input sta-input-num"
                                                wire:model.live.debounce.400ms="counts.{{ $ge->id }}" @disabled($readOnlyReason)>
                                            <span class="sta-muted">Mixed</span>
                                            @if ($eMsg)<p class="sta-error">{{ $eMsg }}</p>@endif
                                        </td>
                                    @else
                                        @foreach ([$maleEv, $femaleEv] as $col)
                                            @if ($g === $col)
                                                <td class="sta-c">
                                                    <input type="number" min="0" aria-label="{{ $ge->event->name }}" class="sta-input sta-input-num"
                                                        wire:model.live.debounce.400ms="counts.{{ $ge->id }}" @disabled($readOnlyReason)>
                                                    @if ($eMsg)<p class="sta-error">{{ $eMsg }}</p>@endif
                                                </td>
                                            @else
                                                <td class="sta-off"><span class="sta-sr-only">Not open to this gender</span></td>
                                            @endif
                                        @endforeach
                                    @endif
                                </tr>
                            @endforeach
                            <tr class="sta-total">
                                <th scope="row">Event entries</th>
                                <td class="sta-c">{{ $entries['male'] }}</td>
                                <td class="sta-c">{{ $entries['female'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <fieldset class="sta-fieldset" wire:key="medals-{{ $gs->id }}">
                    <legend>
                        <span class="sta-legend-title">Expected medals</span>
                        <span class="sta-muted">Optional · not linked to an athlete or event</span>
                    </legend>
                    @foreach (['gold' => ['Gold', '#CA8A04'], 'silver' => ['Silver', '#94A3B8'], 'bronze' => ['Bronze', '#B45309']] as $field => [$label, $dot])
                        <label class="sta-inline-field" for="totals-{{ $gs->id }}-{{ $field }}"><span class="sta-dot" style="background: {{ $dot }}" aria-hidden="true"></span>{{ $label }}
                            <input id="totals-{{ $gs->id }}-{{ $field }}" type="number" min="0" max="99" class="sta-input sta-input-num"
                                wire:model.live.debounce.400ms="totals.{{ $gs->id }}.{{ $field }}" @disabled($readOnlyReason)>
                        </label>
                        @if ($m = $msg("totals.{$gs->id}.{$field}"))<p class="sta-error">{{ $m }}</p>@endif
                    @endforeach
                </fieldset>

                @if ($submitted && isset($paths[$gs->id]))
                    <div class="sta-card-foot"><a target="_blank" href="{{ \App\Filament\Shared\AttachmentActions::url($paths[$gs->id]) }}">View Entry by Event PDF</a></div>
                @endif
            </x-sta.card>

            <aside class="sta-aside" aria-label="{{ $gs->sport->name }} limits and entry policy">
                <x-sta.card :heading="$gs->sport->name.' limits'" description="Checked against the total number of participants" heading-tag="h3">
                    <div class="sta-card-body sta-stack" aria-live="polite">
                        <x-sta.meter label="Male participants" :value="$pm" :max="$gs->p2_max_men" />
                        <x-sta.meter label="Female participants" :value="$pf" :max="$gs->p2_max_women" />
                        <x-sta.meter label="Total participants" :value="$pm + $pf" :max="$gs->p2_max_total" />
                    </div>
                </x-sta.card>
                <x-sta.card heading="Entry policy" heading-tag="h3">
                    <div class="sta-card-body sta-stack">
                        <p>Each event shows how many athletes it allows.</p>
                        @if ($gs->entry_policy)<p>{{ $gs->entry_policy }}</p>@endif
                        @if ($gs->dob_from || $gs->dob_to)
                            <div class="sta-note"><strong>Age eligibility:</strong> born {{ $gs->dob_from ? 'from '.$gs->dob_from->format('j M Y') : '' }} {{ $gs->dob_to ? 'to '.$gs->dob_to->format('j M Y') : '' }}</div>
                        @endif
                    </div>
                </x-sta.card>
            </aside>
        </div>
    @empty
        <p class="sta-muted">No sports to enter for your association in this game.</p>
    @endforelse

    <p class="sta-muted">A saved draft is never submitted. After you submit, your association admin approves it before it goes to STA, and a signed Entry by Event PDF is generated.</p>
    @unless ($readOnlyReason)
        <div><x-filament::button color="gray" wire:click="save">Save as draft</x-filament::button></div>
    @endunless
    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
```

- [ ] **Step 7: Show the note in the submission view**

In `SubmissionView::phase1Rows()`, change the `map` closure so it returns the note as well:

```php
            ->map(fn ($gs) => ['sport' => $gs->sport->name, 'answer' => $answers->get($gs->id)?->answer->label() ?? 'No response',
                'note' => $answers->get($gs->id)?->note]);
```

In `submission-view.blade.php`, replace the Phase 1 table head and rows. The head becomes `<tr class="text-left text-gray-500"><th class="py-1">Sport</th><th class="py-1">Answer</th><th class="py-1">Note</th></tr>`. Each row becomes `<tr class="border-t border-gray-200 dark:border-white/10"><td class="py-1">{{ $r['sport'] }}</td><td class="py-1">{{ $r['answer'] }}</td><td class="py-1">{{ $r['note'] ?? '' }}</td></tr>`.

- [ ] **Step 8: Add the CSS these views use**

Append to `resources/css/filament/sta/theme.css`:

```css
/* Task 4: phase entry helpers */
.sta-fieldset { margin: 0; border: 0; border-top: 1px solid var(--sta-border); padding: 14px 20px; display: flex; flex-wrap: wrap; align-items: center; gap: 12px 24px; }
.sta-card-head + .sta-fieldset { border-top: 0; }
.sta-fieldset.is-tinted { background: var(--ph-tint, #F8FAFC); }
.sta-fieldset legend { float: left; display: flex; flex-direction: column; gap: 2px; margin-right: 8px; padding: 0; }
.sta-legend-title { font-family: var(--sta-font-heading); font-size: 16px; font-weight: 600; }
.sta-inline-field { display: inline-flex; align-items: center; gap: 10px; font-weight: 600; }
.sta-error { color: #B91C1C; font-size: 13px; font-weight: 600; margin: 0; flex-basis: 100%; }
.sta-c { text-align: center !important; }
.sta-allow { font-size: 12px; font-weight: 600; color: #115E59; }
.sta-dot { display: inline-block; width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; }
.sta-stack { display: flex; flex-direction: column; gap: 14px; }
.sta-stack > p { margin: 0; line-height: 1.55; color: var(--sta-text-3); }
.sta-note { padding: 10px 12px; border-radius: var(--sta-radius-ctl); background: #F1F5F9; font-size: 13px; color: var(--sta-text-3); }
.sta-seg > label { min-height: 36px; padding: 0 14px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; font: 600 13px Inter, ui-sans-serif, sans-serif; color: var(--sta-text-3); cursor: pointer; }
.sta-seg > label:has(input:checked) { background: #FFFFFF; color: var(--sta-text); box-shadow: 0 1px 2px rgba(15, 23, 42, .08); }
.sta-seg > label:has(input:focus-visible) { outline: 2px solid var(--sta-primary); outline-offset: 2px; }
```

- [ ] **Step 9: Run the tests**

Run `php artisan migrate` on the local database. Tests use `RefreshDatabase`.

Run `php vendor/bin/pest tests/Feature/Ui/Phase1NoteAndPhase2Test.php tests/Feature/Phase1EntryTest.php tests/Feature/Phase2EntryTest.php tests/Feature/Phase2LimitsTest.php tests/Feature/GenderLabelsTest.php tests/Feature/SubmissionViewTest.php tests/Feature/StaSubmissionEditTest.php`. Expected: all pass.

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_11_16_000100_add_note_to_interest_answers.php app/Models/InterestAnswer.php app/Filament/Shared/Phases/EditsPhase1.php app/Filament/Resources/Games/Pages/SubmissionView.php resources/views/filament resources/css/filament/sta/theme.css tests/Feature/Ui/Phase1NoteAndPhase2Test.php
git commit -m "feat: Phase 1 note per answer; Phase 1 and Phase 2 pages in the canvas layout (M5)"
```

---

### Task 5: Long List (Phase 3): event chips, Phase 2 comparison, list and grid

**Files:**
- Create: `tests/Feature/Ui/LongListUiTest.php`
- Modify:
  - `app/Filament/Shared/Phases/EditsPhase3.php`
  - `resources/views/filament/portal/pages/phase3-entry.blade.php`
  - `resources/views/filament/shared/long-list-people.blade.php`
  - `resources/css/filament/sta/theme.css`

**Interfaces:**
- Consumes: `<x-sta.card>`, `<x-sta.person-avatar>`, `<x-sta.pill>`, and the partial from Task 3.
- Produces these methods on `EditsPhase3`. They only change the in-memory selection; `save()` validates as before.
  - `addAthlete(int $gameEventId, int|string $personId): void`
  - `removeAthlete(int $gameEventId, int|string $personId): void`
  - `addOfficial(int $gameSportId, int|string $personId): void`
  - `removeOfficial(int $gameSportId, int|string $personId): void`

**Decision:** the shared people table (`long-list-people`) has no per-item size columns. Missing sizes already show as warnings ("T-Shirt size missing."), and the same partial serves the admin submission view, which has no game clothing context of its own.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ui/LongListUiTest.php`:

```php
<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Pages\Phase3Entry;
use App\Models\Association;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\NumberEntry;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    Bus::fake();
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->ali = Person::factory()->complete()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan']);
    $this->omar = Person::factory()->complete()->create(['association_id' => $this->assoc->id, 'first_name' => 'Omar', 'last_name' => 'Saleh']);
    $this->coach = Person::factory()->official()->complete()->create(['association_id' => $this->assoc->id, 'first_name' => 'Coach', 'last_name' => 'Karim']);
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());
});

it('adds and removes people with chips and pickers, then saves through the usual checks', function () {
    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->call('addAthlete', $this->m60->id, (string) $this->ali->id)
        ->call('addAthlete', $this->m60->id, (string) $this->ali->id)
        ->assertSet("athletes.{$this->m60->id}", [(string) $this->ali->id])
        ->assertSeeHtml('sta-chip-person')->assertSeeHtml('aria-label="Remove Ali Hasan from Men -60kg"')
        ->call('addOfficial', $this->judo->id, (string) $this->coach->id)
        ->assertSet("officials.{$this->judo->id}", [(string) $this->coach->id])
        ->call('addAthlete', $this->m60->id, (string) $this->omar->id)
        ->call('removeAthlete', $this->m60->id, $this->ali->id)
        ->assertSet("athletes.{$this->m60->id}", [(string) $this->omar->id])
        ->call('save')->assertHasNoErrors();

    expect(Entry::count())->toBe(2);
});

it('compares with Phase 2 and summarises the people on the list', function () {
    $p2 = Submission::for($this->game, $this->assoc, 2);
    NumberEntry::create(['submission_id' => $p2->id, 'game_event_id' => $this->m60->id, 'count' => 1]);
    $p2->update(['status' => SubmissionStatus::Submitted]);

    livewire(Phase3Entry::class, ['game' => $this->game->id])
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id, (string) $this->omar->id])
        ->assertSee('Compared with your Phase 2 numbers')->assertSee('Men -60kg · 2 of 1')->assertSee('1 event differs')
        ->assertSee('People on your Long List')->assertSee('2 male · 0 female · 0 team officials')
        ->assertSee('Long List 2 / Phase 2: 1')->assertSee('Over the Phase 2 number');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run `php vendor/bin/pest tests/Feature/Ui/LongListUiTest.php`.

Expected: FAIL with `Unable to call component method. Public method [addAthlete] not found`.

- [ ] **Step 3: Add the picker methods to `EditsPhase3`**

Add these methods to the trait:

```php
    /** Chip picker (spec 17 canvas LongListEvents): edits the selection only; save() runs LongListChecks as before. */
    public function addAthlete(int $gameEventId, int|string $personId): void
    {
        $this->athletes[$gameEventId] = $this->withPerson($this->athletes[$gameEventId] ?? [], $personId);
    }

    public function removeAthlete(int $gameEventId, int|string $personId): void
    {
        $this->athletes[$gameEventId] = $this->withoutPerson($this->athletes[$gameEventId] ?? [], $personId);
    }

    public function addOfficial(int $gameSportId, int|string $personId): void
    {
        $this->officials[$gameSportId] = $this->withPerson($this->officials[$gameSportId] ?? [], $personId);
    }

    public function removeOfficial(int $gameSportId, int|string $personId): void
    {
        $this->officials[$gameSportId] = $this->withoutPerson($this->officials[$gameSportId] ?? [], $personId);
    }

    /** @return list<string> */
    private function withPerson(array|string $ids, int|string $personId): array
    {
        $ids = array_map('strval', (array) $ids);
        if ($this->readOnlyReason === null && (string) $personId !== '' && ! in_array((string) $personId, $ids, true)) {
            $ids[] = (string) $personId;
        }

        return $ids;
    }

    /** @return list<string> */
    private function withoutPerson(array|string $ids, int|string $personId): array
    {
        $ids = array_map('strval', (array) $ids);

        return $this->readOnlyReason === null ? array_values(array_diff($ids, [(string) $personId])) : $ids;
    }
```

- [ ] **Step 4: Rewrite the Phase 3 view**

Replace `resources/views/filament/portal/pages/phase3-entry.blade.php` with the code below. The `x-data`, `x-on:click` and `x-if` strings, "Open registry", "Long List N / Phase 2: M", "Over the Phase 2 number" and the option labels `Name – Function (code)` must stay exactly as written, because tests assert them.

```blade
<x-filament-panels::page>
    @include('filament.portal.partials.phase-top', ['pdfKey' => 'long_list', 'pdfLabel' => 'View Long List PDF'])

    @php
        $p2 = $this->phase2Numbers();
        $athleteOptions = $this->candidates(\App\Enums\PersonType::Athlete);
        $officialOptions = $this->candidates(\App\Enums\PersonType::Official);
        $people = $athleteOptions->concat($officialOptions)->keyBy('id');
        $sports = $this->visibleSports();
    @endphp

    @if ($p2 !== null)
        @php
            $compare = $sports->flatMap->gameEvents->map(fn ($ge) => ['name' => $ge->event->name, 'n' => count((array) ($athletes[$ge->id] ?? [])), 'p2' => $p2[$ge->id] ?? 0]);
            $differ = $compare->filter(fn ($c) => $c['n'] > $c['p2'])->count();
        @endphp
        <x-sta.card heading="Compared with your Phase 2 numbers">
            <x-slot:actions>
                @if ($differ)<span class="sta-pill sta-pill-warn">{{ $differ }} {{ $differ === 1 ? 'event differs' : 'events differ' }} · allowed, STA will see it</span>@endif
            </x-slot:actions>
            <div class="sta-card-body sta-chips">
                @foreach ($compare as $c)
                    <span @class(['sta-compare', 'is-over' => $c['n'] > $c['p2']])>{{ $c['name'] }} · {{ $c['n'] }} of {{ $c['p2'] }}</span>
                @endforeach
            </div>
        </x-sta.card>
    @endif

    @if ($url = $this->registryUrl())
        <p class="sta-muted"><a href="{{ $url }}" target="_blank">Open registry</a> to edit people; people you add with Add athlete or Add team official appear in these lists straight away.</p>
    @endif

    @forelse ($sports as $gs)
        @php
            $sportAthletes = $gs->gameEvents->flatMap(fn ($ge) => (array) ($athletes[$ge->id] ?? []))->unique()->count();
            $sportOfficials = count((array) ($officials[$gs->id] ?? []));
        @endphp
        <x-sta.card :heading="$gs->sport->name" :description="$gs->gameEvents->count().' events · '.$sportAthletes.' athletes · '.$sportOfficials.' team officials'" wire:key="sport-{{ $gs->id }}">
            <p class="sta-card-body sta-muted" style="padding-bottom: 0">Pick athletes from your registry for each event. Each list offers only active athletes whose gender matches the event (Mixed events accept Male and Female). One athlete can be in several events.</p>
            <div class="sta-card-body sta-event-grid">
                @foreach ($gs->gameEvents as $ge)
                    @php
                        $chosen = array_map('intval', (array) ($athletes[$ge->id] ?? []));
                        $n = count($chosen);
                        $max = $p2 === null ? null : ($p2[$ge->id] ?? 0);
                        $over = $max !== null && $n > $max;
                        $err = $errors->first('athletes.'.$ge->id);
                    @endphp
                    <div class="sta-event-box" wire:key="event-{{ $ge->id }}">
                        <div class="sta-row-between">
                            <label class="sta-event-name" for="event-{{ $ge->id }}">{{ $ge->event->name }} ({{ $ge->event->gender->label() }})</label>
                            <span class="sta-muted">Long List {{ $n }}{{ $max !== null ? " / Phase 2: $max" : '' }}</span>
                            @if ($over)<span class="sta-pill sta-pill-warn">Over the Phase 2 number</span>@endif
                        </div>
                        <div @class(['sta-token-field', 'is-error' => $err])>
                            @foreach ($chosen as $id)
                                @php $p = $people->get($id) ?? \App\Models\Person::withoutGlobalScopes()->find($id); @endphp
                                <span @class(['sta-chip-person', 'is-bad' => $err]) wire:key="chip-{{ $ge->id }}-{{ $id }}">
                                    <x-sta.person-avatar :name="$p?->fullName() ?? '?'" size="sm" />
                                    <span class="sta-who-name">{{ $p?->fullName() }}</span>
                                    <span class="sta-who-sub">{{ $p?->code }}</span>
                                    @unless ($readOnlyReason)
                                        <button type="button" wire:click="removeAthlete({{ $ge->id }}, {{ $id }})" aria-label="Remove {{ $p?->fullName() }} from {{ $ge->event->name }}">×</button>
                                    @endunless
                                </span>
                            @endforeach
                            @unless ($readOnlyReason)
                                <select id="event-{{ $ge->id }}" aria-label="Add an athlete to {{ $ge->event->name }}" wire:change="addAthlete({{ $ge->id }}, $event.target.value)">
                                    <option value="">Search athletes…</option>
                                    @foreach ($athleteOptions->filter(fn ($p) => \App\Services\Phases\LongListChecks::genderMatches($p->gender, $ge->event->gender))->reject(fn ($p) => in_array($p->id, $chosen, true)) as $p)
                                        <option value="{{ $p->id }}">{{ $p->fullName() }} ({{ $p->code }})</option>
                                    @endforeach
                                </select>
                            @endunless
                        </div>
                        @if ($err)<p class="sta-error" role="alert">{{ $err }}</p>@endif
                    </div>
                @endforeach

                @php
                    $chosenOfficials = array_map('intval', (array) ($officials[$gs->id] ?? []));
                    $oErr = $errors->first('officials.'.$gs->id);
                @endphp
                <div class="sta-event-box sta-event-box-officials" wire:key="officials-{{ $gs->id }}">
                    <div class="sta-row-between">
                        <label class="sta-event-name" for="officials-{{ $gs->id }}">Team officials</label>
                        <span class="sta-muted">For the whole sport, not one event · active officials only</span>
                    </div>
                    <div @class(['sta-token-field', 'is-error' => $oErr])>
                        @foreach ($chosenOfficials as $id)
                            @php $p = $people->get($id) ?? \App\Models\Person::withoutGlobalScopes()->find($id); @endphp
                            <span class="sta-chip-person" wire:key="ochip-{{ $gs->id }}-{{ $id }}">
                                <x-sta.person-avatar :name="$p?->fullName() ?? '?'" size="sm" />
                                <span class="sta-who-name">{{ $p?->fullName() }}</span>
                                <span class="sta-who-sub">{{ $p?->function }}</span>
                                @unless ($readOnlyReason)
                                    <button type="button" wire:click="removeOfficial({{ $gs->id }}, {{ $id }})" aria-label="Remove {{ $p?->fullName() }} from {{ $gs->sport->name }} team officials">×</button>
                                @endunless
                            </span>
                        @endforeach
                        @unless ($readOnlyReason)
                            <select id="officials-{{ $gs->id }}" aria-label="Add a team official to {{ $gs->sport->name }}" wire:change="addOfficial({{ $gs->id }}, $event.target.value)">
                                <option value="">Search officials…</option>
                                @foreach ($officialOptions->reject(fn ($p) => in_array($p->id, $chosenOfficials, true)) as $p)
                                    <option value="{{ $p->id }}">{{ $p->fullName() }} – {{ $p->function }} ({{ $p->code }})</option>
                                @endforeach
                            </select>
                        @endunless
                    </div>
                    @if ($oErr)<p class="sta-error" role="alert">{{ $oErr }}</p>@endif
                </div>
            </div>
        </x-sta.card>
    @empty
        <p class="sta-muted">No sports to enter for your association in this game.</p>
    @endforelse

    @php
        $rows = $this->listRows();
        $isAthlete = fn ($r) => $r['person']->type === \App\Enums\PersonType::Athlete;
        $male = $rows->filter(fn ($r) => $isAthlete($r) && $r['person']->gender === \App\Enums\PersonGender::Male)->count();
        $female = $rows->filter(fn ($r) => $isAthlete($r) && $r['person']->gender === \App\Enums\PersonGender::Female)->count();
        $offs = $rows->reject($isAthlete)->count();
    @endphp
    <section aria-labelledby="ll-people" class="sta-stack">
        {{-- View choice is per user, browser-only (localStorage), never sent to the server. --}}
        <div class="sta-stack" x-data="{ view: (() => { try { return localStorage.getItem('longListView.{{ auth()->id() }}') || 'list' } catch (e) { return 'list' } })() }"
             x-init="$watch('view', v => { try { localStorage.setItem('longListView.{{ auth()->id() }}', v) } catch (e) {} })">
            <div class="sta-row-between">
                <h2 id="ll-people" class="sta-card-title">People on your Long List</h2>
                <div class="sta-row-between">
                    <span class="sta-muted">{{ $male }} male · {{ $female }} female · {{ $offs }} team officials</span>
                    <div class="sta-seg" role="group" aria-label="Long List view">
                        <button type="button" x-on:click="view = 'list'" x-bind:aria-pressed="view === 'list'">List</button>
                        <button type="button" x-on:click="view = 'grid'" x-bind:aria-pressed="view === 'grid'">Grid</button>
                    </div>
                </div>
            </div>
            <div x-show="view === 'list'" class="sta-card">
                @include('filament.shared.long-list-people', ['rows' => $rows, 'showTravel' => ! $readOnlyReason])
            </div>
            {{-- Rendered only in Grid view so List view does not fetch every photo on each Livewire render. --}}
            <template x-if="view === 'grid'"><div class="sta-grid-cards">
                @forelse ($rows as $row)
                    @php $p = $row['person']; @endphp
                    <article wire:key="card-{{ $p->id }}" class="sta-card sta-person-card">
                        <div class="sta-who">
                            @if ($p->photo_path)
                                <img loading="lazy" src="{{ \App\Filament\Shared\AttachmentActions::url($p->photo_path) }}" alt="Photo of {{ $p->fullName() }}" class="sta-avatar sta-avatar-lg" style="object-fit: cover">
                            @else
                                <x-sta.person-avatar :name="$p->fullName()" size="lg" />
                            @endif
                            <div class="sta-who-text" style="flex: 1">
                                <span class="sta-who-name">{{ $p->fullName() }}</span>
                                <span class="sta-who-sub">{{ $p->code }} · {{ $p->dob?->format('j M Y') }} · {{ $row['age'] }}</span>
                            </div>
                            <span class="sta-tag">{{ $p->gender->label() }}</span>
                        </div>
                        <div class="sta-chips">@foreach ($row['roles'] as $role)<span class="sta-tag">{{ $role }}</span>@endforeach</div>
                        <div><x-sta.pill :status="$row['missing'] ? 'warn' : 'ok'">{{ $row['missing'] ? 'Incomplete' : 'Complete' }}</x-sta.pill></div>
                        @if ($row['warnings'])
                            <ul class="sta-banner sta-banner-warn sta-warn-list">@foreach ($row['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul>
                        @endif
                    </article>
                @empty
                    <p class="sta-muted">Nobody on the list yet.</p>
                @endforelse
            </div></template>
        </div>
    </section>

    @unless ($readOnlyReason)
        <div><x-filament::button color="gray" wire:click="save">Save as draft</x-filament::button></div>
    @endunless
    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
```

- [ ] **Step 5: Restyle the shared people table**

Replace `resources/views/filament/shared/long-list-people.blade.php` with the code below. It is the same logic and text as before, in canvas markup.

```blade
{{-- Long List people table. $rows = LongListChecks::rows(); optional $showTravel adds the travel action (host page must define the `travel` action). --}}
@if ($rows->isEmpty())
    <p class="sta-card-body sta-muted">Nobody on the list yet.</p>
@else
    <div class="sta-scroll">
        <table class="sta-table">
            <thead>
                <tr>
                    <th scope="col">Person</th><th scope="col">Entered in</th><th scope="col">Age</th><th scope="col">Registry</th><th scope="col">Warnings</th>
                    <th scope="col">@if ($showDetails ?? false) Travel & insurance @else <span class="sta-sr-only">Travel and insurance</span> @endif</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php $p = $row['person']; @endphp
                    <tr wire:key="person-{{ $p->id }}">
                        <td>
                            <div class="sta-who">
                                <x-sta.person-avatar :name="$p->fullName()" />
                                <div class="sta-who-text"><span class="sta-who-name">{{ $p->fullName() }}</span><span class="sta-who-sub">{{ $p->code }}</span></div>
                            </div>
                        </td>
                        <td class="sta-entered">{{ implode(', ', $row['roles']) }}</td>
                        <td>{{ $row['age'] }}</td>
                        <td>
                            @if ($row['missing'])
                                <x-sta.pill status="warn">Incomplete</x-sta.pill>
                                <div class="sta-who-sub">Missing: {{ implode(', ', $row['missing']) }}</div>
                            @else
                                <x-sta.pill status="ok">Complete</x-sta.pill>
                            @endif
                        </td>
                        <td><ul class="sta-warn-list">@foreach ($row['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul></td>
                        <td class="sta-who-sub">
                            @if (($showDetails ?? false) && ($d = $row['detail']))
                                <div>{{ $d->insurance_company ?? '–' }}@if ($showMedical ?? false) · Policy {{ $d->insurance_policy_no ?? '–' }}@endif</div>
                                <div>Valid until {{ $d->insurance_valid_until?->format('j M Y') ?? '–' }}</div>
                                @if ($d->insurance_certificate_path)
                                    <a href="{{ \App\Filament\Shared\AttachmentActions::url($d->insurance_certificate_path) }}" target="_blank">View certificate</a>
                                @endif
                                @if ($d->vaccinations && ($showMedical ?? false))<div>Vaccinations: {{ $d->vaccinations }}</div>@endif
                            @elseif ($row['detail'])
                                <div>Insurance: {{ $row['detail']->insurance_company ?? '–' }}</div>
                                @if ($row['detail']->insurance_certificate_path)
                                    <a href="{{ \App\Filament\Shared\AttachmentActions::url($row['detail']->insurance_certificate_path) }}" target="_blank">View certificate</a>
                                @endif
                            @endif
                            @if (($showTravel ?? false) && $row['on_list'])
                                <x-filament::link tag="button" size="sm" wire:click="mountAction('travel', { person: {{ $p->id }} })">Travel & insurance</x-filament::link>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
```

- [ ] **Step 6: Add the CSS**

Append to `theme.css`:

```css
/* Task 5: Long List */
.sta-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.sta-compare { display: inline-flex; align-items: center; height: 32px; padding: 0 12px; border-radius: 8px; font-size: 13px; background: #F1F5F9; color: var(--sta-text-3); }
.sta-compare.is-over { background: #FFEDD5; color: var(--sta-warn); font-weight: 600; }
.sta-event-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(440px, 100%), 1fr)); gap: 16px; }
.sta-event-box { display: flex; flex-direction: column; gap: 8px; padding: 14px 16px; border-radius: var(--sta-radius-tile); border: 1px solid var(--sta-border); background: #FFFFFF; }
.sta-event-box-officials { background: #F8FAFC; }
.sta-event-name { font-weight: 700; font-size: 15px; }
.sta-entered { font-weight: 600; color: var(--sta-primary); }
.sta-warn-list { margin: 0; padding: 0; list-style: none; font-size: 13px; color: var(--sta-warn); line-height: 1.4; }
.sta-banner.sta-warn-list { display: block; padding: 10px 12px; }
.sta-person-card { padding: 18px; display: flex; flex-direction: column; gap: 14px; }
```

- [ ] **Step 7: Run the tests**

Run `php vendor/bin/pest tests/Feature/Ui/LongListUiTest.php tests/Feature/Phase3EntryTest.php tests/Feature/LongListChecksTest.php tests/Feature/LongListPdfTest.php tests/Feature/SubmissionViewTest.php tests/Feature/StaSubmissionEditTest.php`. Expected: all pass.

`Phase3EntryTest` still sets `athletes.*` arrays directly, and the option text, the error text and the List / Grid strings are unchanged.

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Shared/Phases/EditsPhase3.php resources/views/filament resources/css/filament/sta/theme.css tests/Feature/Ui/LongListUiTest.php
git commit -m "feat: Long List in the canvas layout: event chips, Phase 2 comparison, list and grid (M5)"
```

---

### Task 6: Short List (Phase 4): tick tables, meters, justification

**Files:**
- Create: `tests/Feature/Ui/ShortListUiTest.php`
- Modify:
  - `app/Filament/Shared/Phases/EditsPhase4.php`
  - `resources/views/filament/portal/pages/phase4-entry.blade.php`
  - `resources/css/filament/sta/theme.css`

**Interfaces:**
- Consumes: `longListSlots()`, `longListPeople()` (keyed by id), `overLimits()`, `ShortListChecks::describe()`, `<x-sta.meter>`, `<x-sta.card band="violet">`.
- Produces: `EditsPhase4::tickedCounts(GameSport $gs): array{male: int, female: int, officials: int}`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ui/ShortListUiTest.php`:

```php
<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Portal\Pages\Phase4Entry;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    Bus::fake();
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create();
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(4)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->judo = GameSport::factory()->create(['game_id' => $this->game->id, 'p4_max_men' => 1, 'p4_max_officials' => 2,
        'sport_id' => Sport::factory()->create(['association_id' => $this->assoc->id, 'name' => 'Judo'])->id]);
    $this->m60 = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $this->judo->id,
        'event_id' => Event::factory()->create(['sport_id' => $this->judo->sport_id, 'name' => 'Men -60kg', 'gender' => EventGender::Men])->id]);
    $this->ali = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Ali', 'last_name' => 'Hasan']);
    $this->omar = Person::factory()->create(['association_id' => $this->assoc->id, 'first_name' => 'Omar', 'last_name' => 'Saleh']);
    $this->coach = Person::factory()->official()->create(['association_id' => $this->assoc->id, 'first_name' => 'Coach', 'last_name' => 'Karim']);
    $p3 = Submission::for($this->game, $this->assoc, 3);
    foreach ([$this->ali, $this->omar] as $p) {
        $p3->entries()->create(['person_id' => $p->id, 'game_event_id' => $this->m60->id, 'game_sport_id' => $this->judo->id]);
    }
    $p3->entries()->create(['person_id' => $this->coach->id, 'game_event_id' => null, 'game_sport_id' => $this->judo->id]);
    $p3->update(['status' => SubmissionStatus::Submitted]);
    actingInPortal(User::factory()->assocAdmin($this->assoc)->create());
});

it('counts ticked people per sport', function () {
    $page = livewire(Phase4Entry::class, ['game' => $this->game->id]);

    expect($page->instance()->tickedCounts($this->judo->fresh('gameEvents')))->toBe(['male' => 2, 'female' => 0, 'officials' => 1]);
});

it('shows meters, the violet justification card when over, and the all-clear when within', function () {
    livewire(Phase4Entry::class, ['game' => $this->game->id])
        ->assertSee('Ticked vs maximum')->assertSee('2 of 1')->assertSeeHtml('is-over')
        ->assertSee('Justification needed')->assertSee('Over a maximum')->assertSee('Judo: 2 male athletes (limit 1)')
        ->assertSeeHtml('sta-band-violet')
        ->set("athletes.{$this->m60->id}", [(string) $this->ali->id])
        ->assertSee('Within all maximums — no justification needed.')->assertSee('Within maximums');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run `php vendor/bin/pest tests/Feature/Ui/ShortListUiTest.php`.

Expected: FAIL with `Method tickedCounts does not exist`.

- [ ] **Step 3: Add `tickedCounts` to `EditsPhase4`**

Add `use App\Enums\PersonGender;` and `use App\Models\GameSport;` if they are missing. Then add:

```php
    /** @return array{male: int, female: int, officials: int} people ticked in one sport (spec 17: Short List meters) */
    public function tickedCounts(GameSport $gs): array
    {
        $people = $this->longListPeople();
        $ids = $gs->gameEvents->flatMap(fn ($ge) => (array) ($this->athletes[$ge->id] ?? []))->map(fn ($id) => (int) $id)->unique();

        return [
            'male' => $ids->filter(fn ($id) => $people->get($id)?->gender === PersonGender::Male)->count(),
            'female' => $ids->filter(fn ($id) => $people->get($id)?->gender === PersonGender::Female)->count(),
            'officials' => collect((array) ($this->officials[$gs->id] ?? []))->unique()->count(),
        ];
    }
```

- [ ] **Step 4: Rewrite the Phase 4 view**

Replace `resources/views/filament/portal/pages/phase4-entry.blade.php` with:

```blade
<x-filament-panels::page>
    @include('filament.portal.partials.phase-top', ['pdfKey' => 'short_list', 'pdfLabel' => 'View Short List PDF'])

    @if (! $this->longListSubmitted())
        <div class="sta-banner sta-banner-warn" role="status">Submit the Long List first.</div>
    @else
        @php
            [$slotAthletes, $slotOfficials] = $this->longListSlots();
            $people = $this->longListPeople();
            $over = $this->overLimits();
            $sports = $this->visibleSports();
        @endphp

        <div class="sta-banner sta-banner-info" role="note">Everyone on your Long List is shown per sport. Tick the people for the Short List — the counts update as you tick. Going over a maximum is allowed only with a justification.</div>

        <div class="sta-split">
            <div class="sta-main">
                @foreach ($sports as $gs)
                    @php
                        $events = $gs->gameEvents->filter(fn ($ge) => ! empty($slotAthletes[$ge->id]));
                        $ticked = $this->tickedCounts($gs);
                        $sportOver = collect($over)->contains('sport', $gs->sport->name);
                    @endphp
                    @continue($events->isEmpty() && empty($slotOfficials[$gs->id]))
                    <x-sta.card :heading="$gs->sport->name"
                        :description="'Ticked: '.$ticked['male'].' male, '.$ticked['female'].' female, '.$ticked['officials'].' team officials'"
                        wire:key="sport-{{ $gs->id }}">
                        <x-slot:actions>
                            <x-sta.pill :status="$sportOver ? 'warn' : 'ok'">{{ $sportOver ? 'Over a maximum' : 'Within maximums' }}</x-sta.pill>
                        </x-slot:actions>

                        @if ($events->isNotEmpty())
                            <h3 class="sta-subhead">Athletes</h3>
                            <div class="sta-scroll">
                                <table class="sta-table">
                                    <thead><tr><th scope="col">On Short List</th><th scope="col">Gender</th><th scope="col">Event</th><th scope="col">Passport</th></tr></thead>
                                    <tbody>
                                        @foreach ($events as $ge)
                                            @foreach ($slotAthletes[$ge->id] as $id)
                                                @php $p = $people->get((int) $id); @endphp
                                                @continue(! $p)
                                                <tr wire:key="a-{{ $ge->id }}-{{ $id }}">
                                                    <td>
                                                        <label class="sta-tick">
                                                            <input type="checkbox" value="{{ $id }}" wire:model.live="athletes.{{ $ge->id }}" @disabled($readOnlyReason)>
                                                            <x-sta.person-avatar :name="$p->fullName()" />
                                                            <span class="sta-who-text"><span class="sta-who-name">{{ $p->fullName() }}</span><span class="sta-who-sub">{{ $p->code }}</span></span>
                                                        </label>
                                                    </td>
                                                    <td>{{ $p->gender->label() }}</td>
                                                    <td class="sta-entered">{{ $ge->event->name }} ({{ $ge->event->gender->label() }})</td>
                                                    <td>
                                                        @if ($p->passport_scan_path)
                                                            <a href="{{ \App\Filament\Shared\AttachmentActions::url($p->passport_scan_path) }}" target="_blank" aria-label="View passport scan of {{ $p->fullName() }}">View</a>
                                                        @else
                                                            <span class="sta-muted">Missing</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            @error('athletes.'.$ge->id)<tr><td colspan="4"><p class="sta-error" role="alert">{{ $message }}</p></td></tr>@enderror
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if (! empty($slotOfficials[$gs->id]))
                            <h3 class="sta-subhead sta-subhead-rule">Team officials</h3>
                            <div class="sta-scroll">
                                <table class="sta-table">
                                    <thead><tr><th scope="col">On Short List</th><th scope="col">Function</th><th scope="col">Passport</th></tr></thead>
                                    <tbody>
                                        @foreach ($slotOfficials[$gs->id] as $id)
                                            @php $p = $people->get((int) $id); @endphp
                                            @continue(! $p)
                                            <tr wire:key="o-{{ $gs->id }}-{{ $id }}">
                                                <td>
                                                    <label class="sta-tick">
                                                        <input type="checkbox" value="{{ $id }}" wire:model.live="officials.{{ $gs->id }}" @disabled($readOnlyReason)>
                                                        <span class="sta-who-text"><span class="sta-who-name">{{ $p->fullName() }}</span><span class="sta-who-sub">{{ $p->code }}</span></span>
                                                    </label>
                                                </td>
                                                <td>{{ $p->function }}</td>
                                                <td>
                                                    @if ($p->passport_scan_path)
                                                        <a href="{{ \App\Filament\Shared\AttachmentActions::url($p->passport_scan_path) }}" target="_blank" aria-label="View passport scan of {{ $p->fullName() }}">View</a>
                                                    @else
                                                        <span class="sta-muted">Missing</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        @error('officials.'.$gs->id)<tr><td colspan="3"><p class="sta-error" role="alert">{{ $message }}</p></td></tr>@enderror
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-sta.card>
                @endforeach

                <x-sta.card heading="People on the Short List">
                    @include('filament.shared.long-list-people', ['rows' => $this->listRows()])
                </x-sta.card>
            </div>

            <aside class="sta-aside" aria-label="Short List maximums">
                <x-sta.card heading="Ticked vs maximum" description="Updates as you tick">
                    <div class="sta-card-body sta-stack" aria-live="polite">
                        @foreach ($sports as $gs)
                            @php $ticked = $this->tickedCounts($gs); @endphp
                            <p class="sta-meter-label">{{ $gs->sport->name }}</p>
                            <x-sta.meter label="Male" :value="$ticked['male']" :max="$gs->p4_max_men" style="--meter: #6D28D9" />
                            <x-sta.meter label="Female" :value="$ticked['female']" :max="$gs->p4_max_women" style="--meter: #6D28D9" />
                            <x-sta.meter label="Team officials" :value="$ticked['officials']" :max="$gs->p4_max_officials" style="--meter: #6D28D9" />
                        @endforeach
                    </div>
                </x-sta.card>

                @if ($over)
                    <x-sta.card heading="Justification needed" band="violet" class="sta-justify">
                        <div class="sta-card-body sta-stack">
                            <ul class="sta-warn-list">
                                @foreach ($over as $o)<li>{{ \App\Services\Phases\ShortListChecks::describe($o) }}</li>@endforeach
                            </ul>
                            <label class="sta-field" for="justification">Why do you need more than the maximum? (required to submit, at most 2000 characters)
                                <textarea id="justification" wire:model="justification" rows="6" maxlength="2000" class="sta-input" @disabled($readOnlyReason)></textarea>
                            </label>
                            @error('justification')<p class="sta-error">{{ $message }}</p>@enderror
                            <span class="sta-muted">STA sees this on the Short List PDF and decides.</span>
                        </div>
                    </x-sta.card>
                @else
                    <div class="sta-banner sta-banner-ok" role="status">Within all maximums — no justification needed.</div>
                @endif
            </aside>
        </div>

        @unless ($readOnlyReason)
            <div><x-filament::button color="gray" wire:click="save">Save as draft</x-filament::button></div>
        @endunless
    @endif

    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
```

- [ ] **Step 5: Add the CSS**

Append to `theme.css`:

```css
/* Task 6: Short List */
.sta-subhead { margin: 0; padding: 14px 20px 6px; font-size: 13px; font-weight: 700; color: var(--sta-text-2); text-transform: uppercase; letter-spacing: .05em; }
.sta-subhead-rule { border-top: 1px solid var(--sta-border); padding-top: 18px; }
.sta-tick { display: flex; align-items: center; gap: 12px; min-height: 52px; cursor: pointer; }
.sta-tick input[type='checkbox'] { width: 22px; height: 22px; accent-color: #6D28D9; flex-shrink: 0; }
.sta-justify { border-width: 2px; border-color: #6D28D9; }
.sta-justify .sta-warn-list { color: #5B21B6; font-weight: 600; }
```

- [ ] **Step 6: Run the tests**

Run `php vendor/bin/pest tests/Feature/Ui/ShortListUiTest.php tests/Feature/Phase4EntryTest.php tests/Feature/ShortListChecksTest.php tests/Feature/ShortListAdminTest.php tests/Feature/ShortListPdfTest.php tests/Feature/StaSubmissionEditTest.php`. Expected: all pass. "Submit the Long List first.", the describe lines, `NOT_ON_LONG_LIST`, "This phase is closed." and "View Short List PDF" are all still rendered.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Shared/Phases/EditsPhase4.php resources/views/filament/portal/pages/phase4-entry.blade.php resources/css/filament/sta/theme.css tests/Feature/Ui/ShortListUiTest.php
git commit -m "feat: Short List in the canvas layout: tick tables, ticked-vs-maximum meters, violet justification (M5)"
```

---

### Task 7: Registry, admin People and the athlete / official forms

**Files:**
- Create:
  - `app/Filament/Shared/PersonPageChrome.php`
  - `resources/views/filament/resources/people/person-subheading.blade.php`
  - `resources/views/filament/resources/people/kpis.blade.php`
  - `tests/Feature/Ui/PeopleUiTest.php`
- Modify:
  - `app/Filament/Shared/PersonTable.php`
  - `app/Filament/Shared/PersonForm.php`
  - `app/Filament/Portal/Resources/People/Pages/{ListPeople,CreatePerson,EditPerson}.php`
  - `app/Filament/Resources/People/Pages/{ListPeople,CreatePerson,EditPerson}.php`
  - `resources/css/filament/sta/theme.css`

**Interfaces:**
- Consumes: `InitialsAvatarProvider::svg`, `Sta::initials`, `<x-sta.kpi>`, `<x-sta.pill>`, and the `sta-band-*` classes.
- Produces:
  - `PersonPageChrome` (a trait): `getPageClasses()` returns `['sta-bg-alt']`, and `getSubheading()` returns the code chip, completeness and "Missing: …" on edit.
  - `PersonTable::missingSizes(Person $p): int` (public static)
  - admin `ListPeople::kpis(): array{athletes:int, officials:int, expiring:int, missingScan:int}`
  - the CSS class `sta-bg-alt`, which sets the page background to `#E9EEF5`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ui/PeopleUiTest.php`:

```php
<?php

use App\Enums\AppliesTo;
use App\Filament\Portal\Resources\People\Pages\EditPerson as PortalEditPerson;
use App\Filament\Portal\Resources\People\Pages\ListPeople as PortalListPeople;
use App\Filament\Resources\People\Pages\ListPeople as AdminListPeople;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Person;
use App\Models\User;

use function Pest\Livewire\livewire;

it('lists the portal registry with type tabs, size state and the canvas subtitle', function () {
    $a = Association::factory()->create();
    actingInPortal(User::factory()->assocAdmin($a)->create());
    ClothingItem::factory()->create(['name' => 'Suit', 'applies_to' => AppliesTo::Both, 'is_active' => true]);
    $p = Person::factory()->create(['association_id' => $a->id, 'first_name' => 'Hamad', 'last_name' => 'Buali']);

    livewire(PortalListPeople::class)
        ->assertSee('Your permanent registry · reused for every game · 1 athletes, 0 team officials')
        ->assertSee('1 missing')->assertSee('Athletes')->assertSee('Officials')->assertSee('Import Excel')->assertSee('Add person')
        ->assertCanSeeTableRecords([$p]);
});

it('shows the admin People KPI tiles', function () {
    actingInAdmin(User::factory()->superAdmin()->create());
    Person::factory()->count(2)->create();
    Person::factory()->official()->create(['passport_expiry' => now()->addMonths(2)->toDateString()]);

    $page = livewire(AdminListPeople::class)
        ->assertSee('Team officials')->assertSee('Passport expires within 6 months')->assertSee('Missing passport scan');

    expect($page->instance()->kpis())->toMatchArray(['athletes' => 2, 'officials' => 1, 'expiring' => 1, 'missingScan' => 3]);
});

it('colours the person form sections and shows completeness in the header', function () {
    $a = Association::factory()->create();
    actingInPortal(User::factory()->assocAdmin($a)->create());
    $p = Person::factory()->create(['association_id' => $a->id]);

    livewire(PortalEditPerson::class, ['record' => $p->getRouteKey()])
        ->assertSeeHtml('sta-band-blue')->assertSeeHtml('sta-band-indigo')->assertSeeHtml('sta-band-rose')
        ->assertSee($p->code)->assertSee('Incomplete')->assertSee('Missing:')->assertSee('Save as draft');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run `php vendor/bin/pest tests/Feature/Ui/PeopleUiTest.php`.

Expected: FAIL, because "Your permanent registry" is not shown and `kpis()` does not exist.

- [ ] **Step 3: Restyle the shared person table**

In `app/Filament/Shared/PersonTable.php`, add these imports:

```php
use App\Models\ClothingItem;
use App\Support\InitialsAvatarProvider;
use App\Support\Sta;
use Filament\Support\Enums\FontWeight;
```

Replace the `->modifyQueryUsing(...)` and `->columns([...])` calls with the code below. The filters and record actions stay as they are.

```php
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['fieldValues', 'sizes'])) // missingFields() and sizes per row
            ->description('Passport dates in orange expire within 6 months.')
            ->columns([
                ImageColumn::make('photo_path')->label('')->circular()->disk(config('filesystems.private'))->visibility('private')
                    ->defaultImageUrl(fn (Person $record) => InitialsAvatarProvider::svg(Sta::initials($record->fullName()))),
                TextColumn::make('last_name')->label('Name')->formatStateUsing(fn (Person $record) => $record->fullName())
                    ->description(fn (Person $record) => $record->code)->weight(FontWeight::SemiBold)
                    ->searchable(['first_name', 'last_name'])->sortable(),
                TextColumn::make('code')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                ...($withAssociation ? [TextColumn::make('association.name')->searchable()] : []),
                TextColumn::make('type')->label('Type')
                    ->formatStateUsing(fn (PersonType $state, Person $record) => $state === PersonType::Official && filled($record->function) ? $record->function : $state->label()),
                TextColumn::make('dob')->date('d M Y')->label('Date of birth'),
                TextColumn::make('passport_expiry')->date('d M Y')->weight(FontWeight::SemiBold)
                    ->color(fn (Person $record) => $record->passport_expiry?->lt(now()->addMonths(6)) ? 'warning' : null),
                TextColumn::make('sizes_state')->label('Sizes')->badge()
                    ->state(fn (Person $record) => ($n = self::missingSizes($record)) ? "{$n} missing" : 'Complete')
                    ->color(fn (string $state) => $state === 'Complete' ? 'success' : 'warning'),
                TextColumn::make('completeness')->badge()
                    ->state(fn (Person $record) => $record->isComplete() ? 'Complete' : 'Incomplete')
                    ->color(fn (string $state) => $state === 'Complete' ? 'success' : 'warning')
                    ->tooltip(fn (Person $record) => $record->isComplete() ? null : 'Missing: '.implode(', ', $record->missingFields())),
                IconColumn::make('is_active')->boolean(),
            ])
```

Add this method to the class:

```php
    /** Active clothing items for the person's type that have no size yet (canvas Registry "Sizes" column). */
    public static function missingSizes(Person $record): int
    {
        $items = once(fn () => ClothingItem::where('is_active', true)->get());
        $have = $record->sizes->pluck('size', 'clothing_item_id');

        return $items->filter(fn (ClothingItem $i) => $i->applies_to->includes($record->type) && blank($have->get($i->id)))->count();
    }
```

The separate `function` column goes away, because officials now show their function in the Type column, as the canvas does.

- [ ] **Step 4: Add type tabs, subtitle and actions to both People lists**

In `app/Filament/Portal/Resources/People/Pages/ListPeople.php`, replace the class body with the code below. Keep the `CreateAction` import, and add these imports:

```php
use App\Enums\PersonType;
use App\Filament\Portal\Pages\ImportPeople;
use App\Models\Person;
use Filament\Actions\Action;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
```

```php
    protected function getHeaderActions(): array
    {
        return [
            Action::make('importExcel')->label('Import Excel')->color('gray')->url(fn () => ImportPeople::getUrl())
                ->visible(fn () => ImportPeople::canAccess()),
            CreateAction::make()->label('Add person'),
        ];
    }

    public function getSubheading(): ?string
    {
        $count = fn (PersonType $t) => Person::query()->where('type', $t)->count();

        return 'Your permanent registry · reused for every game · '.$count(PersonType::Athlete).' athletes, '.$count(PersonType::Official).' team officials';
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'athletes' => Tab::make('Athletes')->modifyQueryUsing(fn (Builder $q) => $q->where('type', PersonType::Athlete)),
            'officials' => Tab::make('Officials')->modifyQueryUsing(fn (Builder $q) => $q->where('type', PersonType::Official)),
        ];
    }
```

In `app/Filament/Resources/People/Pages/ListPeople.php`, keep `getHeaderActions()` and add the same `getTabs()` (with the same `Tab`, `Builder` and `PersonType` imports). Also add the following, with these imports: `App\Models\Person`, `Filament\Schemas\Schema`, `Filament\Schemas\Components\EmbeddedTable`, `Filament\Schemas\Components\RenderHook`, `Filament\Schemas\Components\View` and `Filament\View\PanelsRenderHook`.

```php
    public function getSubheading(): ?string
    {
        return 'Athletes and team officials from every association · passport and CPR numbers are encrypted';
    }

    /** @return array{athletes: int, officials: int, expiring: int, missingScan: int} canvas AdminPeople tiles */
    public function kpis(): array
    {
        return [
            'athletes' => Person::query()->where('type', PersonType::Athlete)->count(),
            'officials' => Person::query()->where('type', PersonType::Official)->count(),
            'expiring' => Person::query()->whereNotNull('passport_expiry')->where('passport_expiry', '<', now()->addMonths(6))->count(),
            'missingScan' => Person::query()->whereNull('passport_scan_path')->count(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.resources.people.kpis')->viewData(['kpis' => $this->kpis()]),
            $this->getTabsContentComponent(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
            EmbeddedTable::make(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
        ]);
    }
```

Create `resources/views/filament/resources/people/kpis.blade.php`:

```blade
<section aria-label="Summary" class="sta-grid-kpi">
    <x-sta.kpi label="Athletes" :value="number_format($kpis['athletes'])" />
    <x-sta.kpi label="Team officials" :value="number_format($kpis['officials'])" />
    <x-sta.kpi label="Passport expires within 6 months" :value="number_format($kpis['expiring'])" tone="warn" />
    <x-sta.kpi label="Missing passport scan" :value="number_format($kpis['missingScan'])" tone="warn" />
</section>
```

- [ ] **Step 5: Colour the form sections**

In `app/Filament/Shared/PersonForm.php`, add `use Filament\Support\Icons\Heroicon;`. Then append `->extraAttributes(['class' => 'sta-band sta-band-X'])->icon(Heroicon::Y)` to each `Section::make(...)` call. Keep everything else the same.

| Section | Band | Icon |
|---|---|---|
| `'Person'` | `blue` | `Heroicon::OutlinedUser` |
| `'Passport'` | `indigo` | `Heroicon::OutlinedIdentification` |
| `'CPR'` | `violet` | `Heroicon::OutlinedCreditCard` |
| `'Contact'` | `teal` | `Heroicon::OutlinedPhone` |
| `'Guardian'` | `amber` | `Heroicon::OutlinedUserGroup` |
| `'Team official details'` | `cyan` | `Heroicon::OutlinedBriefcase` |
| `'Clothing'` | `slate` | `Heroicon::OutlinedShoppingBag` |
| `'Additional information'` | `slate` | `Heroicon::OutlinedAdjustmentsHorizontal` |
| `'Medical'` / `'Health'` (the closure heading) | `rose` | `Heroicon::OutlinedHeart` |
| `'Consents'` | `fuchsia` | `Heroicon::OutlinedShieldCheck` |
| `'Declarations'` | `fuchsia` | `Heroicon::OutlinedShieldCheck` |

For example, the Person section becomes `Section::make('Person')->extraAttributes(['class' => 'sta-band sta-band-blue'])->icon(Heroicon::OutlinedUser)->columns(2)->schema([...])`.

- [ ] **Step 6: Add the person page chrome**

Create `app/Filament/Shared/PersonPageChrome.php`:

```php
<?php

namespace App\Filament\Shared;

use App\Models\Person;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/** Athlete / official create and edit pages (canvas PlayerForm, OfficialForm): soft blue page, code and completeness in the header. */
trait PersonPageChrome
{
    public function getPageClasses(): array
    {
        return ['sta-bg-alt'];
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (! ($this->record ?? null) instanceof Person || ! $this->record->exists) {
            return 'Fields marked * are required to save. Fields needed for the Long List can wait, but the person stays Incomplete until they are filled.';
        }

        return new HtmlString(view('filament.resources.people.person-subheading', ['person' => $this->record])->render());
    }
}
```

Create `resources/views/filament/resources/people/person-subheading.blade.php`:

```blade
@php $missing = $person->missingFields(); @endphp
<span class="sta-heading">
    <span class="sta-tag">{{ $person->code }}</span>
    <x-sta.pill :status="$missing ? 'warn' : 'ok'">{{ $missing ? 'Incomplete' : 'Complete' }}</x-sta.pill>
    @if ($missing)<span class="sta-tone-warn" style="font-size: 13px">Missing: {{ implode(', ', $missing) }}</span>@endif
</span>
```

Add `use \App\Filament\Shared\PersonPageChrome;` inside the class body of all four pages: portal `CreatePerson` and `EditPerson`, and admin `CreatePerson` and `EditPerson`.

- [ ] **Step 7: Add the CSS**

Append to `theme.css`:

```css
/* Task 7: person pages and admin home use the canvas soft background */
.fi-body:has(.sta-bg-alt) { background: var(--sta-bg-home); }
```

- [ ] **Step 8: Run the tests**

Run `php vendor/bin/pest tests/Feature/Ui/PeopleUiTest.php tests/Feature/PeopleScreensTest.php tests/Feature/PersonFormV12Test.php tests/Feature/PersonCompletenessTest.php tests/Feature/PersonExtraFieldsTest.php tests/Feature/MedicalAccessTest.php tests/Feature/PeopleImportTest.php tests/Feature/SecurityTest.php tests/Feature/Phase3EntryTest.php`. Expected: all pass.

- [ ] **Step 9: Commit**

```bash
git add app/Filament/Shared app/Filament/Portal/Resources/People app/Filament/Resources/People resources/views/filament/resources/people resources/css/filament/sta/theme.css tests/Feature/Ui/PeopleUiTest.php
git commit -m "feat: registry and admin People in the canvas look; coloured person form sections (M5)"
```

---

### Task 8: Game setup (Details & associations, Sports & limits, Phases)

**Files:**
- Create:
  - `resources/views/filament/resources/games/phase-card.blade.php`
  - `tests/Feature/Ui/GameSetupUiTest.php`
- Modify:
  - `app/Filament/Resources/Games/Pages/EditGame.php`
  - `app/Filament/Resources/Games/GameResource.php` (`getRelations` order)
  - `app/Filament/Resources/Games/Schemas/GameForm.php`
  - `app/Filament/Resources/Games/RelationManagers/PhasesRelationManager.php`
  - `app/Filament/Resources/Games/RelationManagers/GameSportsRelationManager.php`
  - `resources/css/filament/sta/theme.css`

**Interfaces:**
- Consumes: `Sta::phaseStatus`, `<x-sta.phase-chip>`, `<x-sta.pill>`, and the `sta-ph-N` variables.
- Produces: `EditGame` with combined tabs, which are labelled "1 · Details & associations", "2 · Sports & limits" and "3 · Phases".

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ui/GameSetupUiTest.php`:

```php
<?php

use App\Enums\PhaseStatus;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\RelationManagers\GameSportsRelationManager;
use App\Filament\Resources\Games\RelationManagers\PhasesRelationManager;
use App\Models\Game;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(fn () => actingInAdmin(User::factory()->superAdmin()->create()));

it('shows game setup as numbered tabs with the draft pill and the clothing band', function () {
    $game = Game::factory()->draft()->create();

    livewire(EditGame::class, ['record' => $game->getRouteKey()])
        ->assertSee('Game setup')->assertSee('1 · Details & associations')->assertSee('2 · Sports & limits')->assertSee('3 · Phases')
        ->assertSee('Draft · not visible to associations')->assertSee('Publish needs 1+ invited association and 1+ sport')
        ->assertSee('Clothing sizes to collect')->assertSeeHtml('sta-band-amber');
});

it('draws each phase as a tinted card with its description and dates', function () {
    $game = Game::factory()->create();
    $game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);

    livewire(PhasesRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class])
        ->assertSeeHtml('sta-phase-num')->assertSee('Long List')->assertSee('Open')
        ->assertSee('Associations pick athletes for each event')->assertSee('Turned off for this game');
});

it('shows Phase 2 limits and Short List maximums per sport', function () {
    $game = Game::factory()->create();

    livewire(GameSportsRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class])
        ->assertSee('P2 male')->assertSee('P4 male')->assertSee('P4 officials');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run `php vendor/bin/pest tests/Feature/Ui/GameSetupUiTest.php`.

Expected: FAIL, because "Game setup" is not shown.

- [ ] **Step 3: Set up the edit page header and tabs**

In `EditGame.php`, add these imports:

```php
use App\Filament\Resources\Games\GameResource;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
```

Replace `getSubheading()` and add the other methods:

```php
    public function getTitle(): string|Htmlable
    {
        return 'Game setup';
    }

    public function getBreadcrumbs(): array
    {
        return [GameResource::getUrl() => 'Games', $this->record->name, 'Setup'];
    }

    public function getSubheading(): string|Htmlable
    {
        return new HtmlString(Blade::render($this->record->isPublished()
            ? '<x-sta.pill status="submitted">Published</x-sta.pill>'
            : '<span class="sta-heading"><x-sta.pill status="draft">Draft · not visible to associations</x-sta.pill><span class="sta-muted">Publish needs 1+ invited association and 1+ sport</span></span>'));
    }

    /** Canvas setup tabs: the form is tab 1, then the relation managers. */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return '1 · Details & associations';
    }
```

In the publish action, change `->color('success')` to `->color('primary')`.

In `GameResource::getRelations()`, return `[GameSportsRelationManager::class, PhasesRelationManager::class]`. In `GameSportsRelationManager`, set `protected static ?string $title = '2 · Sports & limits';`. In `PhasesRelationManager`, set `protected static ?string $title = '3 · Phases';`.

- [ ] **Step 4: Style the form sections**

In `GameForm::configure()`:
- Change `Section::make('Details')` to `Section::make('Game details')`.
- Change `Section::make('Associations')` to `Section::make('Invited associations')->description('Each invited association sees this game once it is published.')`.
- Change the clothing section to the code below:

```php
            Section::make('Clothing sizes to collect')->description('Phase 3 · associations enter a size for each person on the Long List; missing sizes are flagged.')
                ->extraAttributes(['class' => 'sta-band sta-band-amber'])->icon(Heroicon::OutlinedShoppingBag)->schema([
                    CheckboxList::make('clothingItems')->relationship('clothingItems', 'name',
                        fn ($query) => $query->where('is_active', true)->orderBy('name'))->columns(3),
                ]),
```

Add `use Filament\Support\Icons\Heroicon;`. The fields do not change.

- [ ] **Step 5: Show the phases as cards**

In `PhasesRelationManager::table()`, add `use Filament\Tables\Columns\ViewColumn;`. Replace the `->columns([...])` call with the code below, and add `->contentGrid(...)` and `->recordClasses(...)`:

```php
            ->contentGrid(['default' => 1])
            ->recordClasses(fn (GamePhase $r) => 'sta-phase-record sta-ph-'.$r->phase.($r->enabled && $r->status === PhaseStatus::Open ? ' is-open' : '').($r->enabled ? '' : ' is-off'))
            ->columns([
                ViewColumn::make('phase')->view('filament.resources.games.phase-card'),
            ])
```

Make these label changes only. The action names, visibility and behaviour stay the same.
- `EditAction::make()` becomes `EditAction::make()->label('Edit dates')`.
- The `open` action label becomes `'Open phase'`.
- The `close` action label becomes `'Close phase'`.

Create `resources/views/filament/resources/games/phase-card.blade.php`:

```blade
@php
    $p = $getRecord();
    [$label, $pill] = \App\Support\Sta::phaseStatus($p);
    $what = [
        1 => 'Associations answer Yes / No / Maybe for each invited sport. Sports answered No are hidden from them later.',
        2 => 'Associations enter how many athletes per event, within the event, male, female and total limits.',
        3 => 'Associations pick athletes for each event and officials for each sport, with clothing sizes. PDF on approval.',
        4 => 'Associations reduce the Long List to the maximums. Going over needs one justification.',
    ][$p->phase];
@endphp
<div class="sta-phase-row">
    <span class="sta-phase-num" aria-hidden="true">{{ $p->phase }}</span>
    <div class="sta-who-text" style="gap: 4px">
        <div class="sta-heading">
            <x-sta.phase-chip :phase="$p->phase" />
            <span class="sta-phase-card-name">{{ \App\Models\GamePhase::NAMES[$p->phase] }}</span>
            <x-sta.pill :status="$pill">{{ $label }}</x-sta.pill>
        </div>
        <span class="sta-muted">{{ $p->enabled ? $what : 'Turned off for this game — associations skip it.' }}</span>
        @if ($p->enabled)
            <span class="sta-muted">Opens {{ $p->opens_at?->timezone('Asia/Bahrain')->format('d M Y H:i') ?? '–' }} · Deadline {{ $p->deadline?->timezone('Asia/Bahrain')->format('d M Y H:i') ?? '–' }}</span>
        @endif
    </div>
</div>
```

- [ ] **Step 6: Add the Short List maximums to the sports table**

In `GameSportsRelationManager::table()`, replace `->columns([...])` with the code below. The `P2 …` labels stay, because `GenderLabelsTest` asserts them.

```php
            ->description('Phase 2 limits apply to each association\'s total number of participants; Phase 4 maximums apply to its Short List.')
            ->columns([
                TextColumn::make('sport.name')->label('Sport')->weight(FontWeight::SemiBold)
                    ->description(fn (GameSport $record) => $record->sport->association?->name),
                TextColumn::make('p2_max_men')->label('P2 male'),
                TextColumn::make('p2_max_women')->label('P2 female'),
                TextColumn::make('p2_max_total')->label('P2 total'),
                TextColumn::make('p4_max_men')->label('P4 male'),
                TextColumn::make('p4_max_women')->label('P4 female'),
                TextColumn::make('p4_max_officials')->label('P4 officials'),
                TextColumn::make('game_events_count')->label('Events'),
            ])
```

Add `use Filament\Support\Enums\FontWeight;`.

- [ ] **Step 7: Add the CSS**

Append to `theme.css`:

```css
/* Task 8: game setup phase cards */
.fi-ta-record.sta-phase-record { background: var(--ph-tint); border: 1px solid var(--ph-border); border-radius: var(--sta-radius-card); }
.fi-ta-record.sta-phase-record.is-open { border-color: var(--ph-dot); box-shadow: 0 0 0 3px var(--ph-bg); }
.fi-ta-record.sta-phase-record.is-off { background: #FAFBFC; }
.sta-phase-row { display: flex; gap: 16px; align-items: flex-start; }
.sta-phase-num { width: 44px; height: 44px; border-radius: var(--sta-radius-tile); background: var(--ph-bg); color: var(--ph-fg); display: inline-flex; align-items: center; justify-content: center; font-family: var(--sta-font-heading); font-weight: 700; font-size: 18px; flex-shrink: 0; }
.is-open .sta-phase-num { background: var(--ph-dot); color: #FFFFFF; }
```

- [ ] **Step 8: Run the tests**

Run `php vendor/bin/pest tests/Feature/Ui/GameSetupUiTest.php tests/Feature/GameSetupTest.php tests/Feature/GamePublishTest.php tests/Feature/GenderLabelsTest.php tests/Feature/PhaseGateTest.php tests/Feature/PhaseRemindersTest.php`. Expected: all pass. `GamePublishTest` still sees "Draft" and "Published", and the `open` / `close` / `publish` actions keep their names.

- [ ] **Step 9: Commit**

```bash
git add app/Filament/Resources/Games resources/views/filament/resources/games/phase-card.blade.php resources/css/filament/sta/theme.css tests/Feature/Ui/GameSetupUiTest.php
git commit -m "feat: game setup as canvas tabs with phase cards and Short List maximums (M5)"
```

---

### Task 9: Admin lists and the pages without a design

This task covers Associations, Sports & events, the Audit log, the submission view, comments, the public invitation pages, and the theme-only pages.

**Files:**
- Create: `tests/Feature/Ui/AdminListsUiTest.php`
- Modify:
  - `app/Filament/Resources/Associations/Tables/AssociationsTable.php`
  - `app/Filament/Resources/Associations/Pages/ListAssociations.php`
  - `app/Filament/Resources/Sports/Tables/SportsTable.php`
  - `app/Filament/Resources/Sports/RelationManagers/EventsRelationManager.php`
  - `app/Filament/Resources/ActivityLogs/ActivityLogResource.php`
  - `resources/views/filament/resources/games/pages/submission-view.blade.php`
  - `resources/views/filament/partials/comments.blade.php`
  - `resources/views/invitations/accept.blade.php`
  - `resources/views/invitations/invalid.blade.php`

**Interfaces:**
- Consumes: `InitialsAvatarProvider::svg`, `Sta::avatarColour`, `Sta::submissionPill`, `<x-sta.card>`, `<x-sta.pill>`, `<x-sta.phase-chip>`, and the panel colours `pink`, `info`, `warning` and `success` from Task 1.
- Produces: no new interfaces.

The following pages get the look from the theme alone, with no code change: Clothing items, STA users, Extra fields, Settings, export layouts (`GameExportFiles`), Members, Invitations, ImportPeople, EditAssociationProfile, the Filament login, password reset and profile pages, and every modal. Check them in Task 14.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ui/AdminListsUiTest.php`:

```php
<?php

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\Associations\Pages\ListAssociations;
use App\Filament\Resources\Sports\Pages\ListSports;
use App\Models\Association;
use App\Models\Sport;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(fn () => actingInAdmin(User::factory()->superAdmin()->create()));

it('lists associations with the admin state, editors and sports', function () {
    $judo = Association::factory()->create(['name' => 'Bahrain Judo Federation', 'code' => 'BJF']);
    User::factory()->assocAdmin($judo)->create(['name' => 'Yusuf Kamal']);
    Sport::factory()->create(['association_id' => $judo->id, 'name' => 'Judo']);
    Association::factory()->create(['name' => 'Bahrain eSports Federation', 'code' => 'BEF']);

    livewire(ListAssociations::class)
        ->assertSee('Yusuf Kamal')->assertSee('No admin')->assertSee('0 / 2')->assertSee('Judo')
        ->assertSee('2 associations · 1 have an active admin · 0 invitations pending');
});

it('shows each sport with its association', function () {
    $a = Association::factory()->create(['name' => 'Bahrain Karate Federation']);
    Sport::factory()->create(['association_id' => $a->id, 'name' => 'Karate']);

    livewire(ListSports::class)->assertSee('Karate')->assertSee('Bahrain Karate Federation');
});

it('marks medical and document changes in the audit log', function () {
    activity()->withProperties(['medical_changed' => true, 'documents_changed' => ['passport_no']])->event('updated')->log('updated');

    livewire(ListActivityLogs::class)->assertSee('Medical data changed')->assertSee('Documents changed');
});

it('brands the public invitation pages', function () {
    $this->get('/invitations/not-a-real-token')->assertStatus(410)
        ->assertSee('Bahrain Olympic Committee')->assertSee('invalid or has expired');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run `php vendor/bin/pest tests/Feature/Ui/AdminListsUiTest.php`.

Expected: FAIL, because "No admin" and "Medical data changed" are not shown.

- [ ] **Step 3: Restyle the associations table and its subtitle**

In `AssociationsTable.php`, add these imports:

```php
use App\Models\Invitation;
use App\Support\InitialsAvatarProvider;
use App\Support\Sta;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
```

Replace `->columns([...])` with the code below. The record actions stay the same.

```php
            ->modifyQueryUsing(fn (Builder $q) => $q->with(['users', 'sports'])
                ->withCount(['invitations as admin_invites_count' => fn ($i) => $i->pending()->where('role', Role::AssocAdmin)]))
            ->columns([
                ImageColumn::make('logo_path')->label('')->disk(config('filesystems.private'))->visibility('private')
                    ->defaultImageUrl(fn (Association $record) => InitialsAvatarProvider::svg($record->code ?? Sta::initials($record->name), Sta::avatarColour($record->code ?? $record->name))),
                TextColumn::make('name')->label('Association')->weight(FontWeight::SemiBold)
                    ->description(fn (Association $record) => $record->email)->searchable(['name', 'email'])->sortable(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('admin')->label('Admin')->badge()
                    ->state(fn (Association $record) => $record->users->first(fn ($u) => $u->role === Role::AssocAdmin && $u->is_active)?->name
                        ?? ($record->admin_invites_count ? 'Invite pending' : 'No admin'))
                    ->color(fn (string $state) => match ($state) { 'Invite pending' => 'warning', 'No admin' => 'danger', default => 'success' }),
                TextColumn::make('editors')->label('Editors')
                    ->state(fn (Association $record) => $record->users->filter(fn ($u) => $u->role === Role::AssocEditor && $u->is_active)->count().' / 2'),
                TextColumn::make('sports_list')->label('Sports')->wrap()
                    ->state(fn (Association $record) => $record->sports->pluck('name')->implode(' · ') ?: '–'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
```

In `ListAssociations.php`, add `use App\Enums\Role;`, `use App\Models\Association;` and `use App\Models\Invitation;`, and this method:

```php
    public function getSubheading(): ?string
    {
        $total = Association::count();
        $withAdmin = Association::whereHas('users', fn ($q) => $q->where('role', Role::AssocAdmin)->where('is_active', true))->count();

        return "{$total} associations · {$withAdmin} have an active admin · ".Invitation::pending()->count().' invitations pending';
    }
```

- [ ] **Step 4: Restyle sports and events**

In `SportsTable.php`, add `use App\Models\Sport;` and `use Filament\Support\Enums\FontWeight;`. Replace the columns with:

```php
                TextColumn::make('name')->weight(FontWeight::SemiBold)->description(fn (Sport $record) => $record->association?->name)->searchable()->sortable(),
                TextColumn::make('association.name')->label('Association')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('events_count')->counts('events')->label('Events')->formatStateUsing(fn ($state) => $state.' events'),
                IconColumn::make('is_active')->label('Active')->boolean(),
```

In `EventsRelationManager.php`, change the gender column to:

```php
                TextColumn::make('gender')->formatStateUsing(fn (EventGender $state) => $state->label())->badge()
                    ->color(fn (EventGender $state) => match ($state) { EventGender::Men => 'info', EventGender::Women => 'pink', EventGender::Mixed => 'gray' }),
```

- [ ] **Step 5: Mark sensitive changes in the audit log**

In `ActivityLogResource::table()`, insert this column after `subject_id`:

```php
                TextColumn::make('marks')->label('Marked')->badge()
                    ->state(fn (Activity $record) => array_values(array_filter([
                        $record->properties->get('medical_changed') ? 'Medical data changed' : null,
                        $record->properties->get('documents_changed') ? 'Documents changed' : null,
                    ])))
                    ->color(fn (string $state) => $state === 'Medical data changed' ? 'gray' : 'info'),
```

- [ ] **Step 6: Restyle the submission view and the comments**

In `submission-view.blade.php`:

1. Replace the first `<x-filament::section>…</x-filament::section>` (the `<dl>` summary) with the code below.
2. Add `sta-table` to the start of the `class` attribute of each of the five `<table class="w-full text-sm">` tags, so they become `class="sta-table w-full text-sm"`. The cells keep `class="py-1"`, because `SubmissionViewTest` asserts `<td class="py-1">3</td>`.

```blade
    <x-sta.card>
        <dl class="sta-card-body grid gap-3 text-sm sm:grid-cols-3">
            <div><dt class="sta-muted">Association</dt><dd><span class="sta-who"><x-sta.assoc-avatar :association="$s->association" size="sm" /> {{ $s->association->name }} ({{ $s->association->code }})</span></dd></div>
            <div><dt class="sta-muted">Phase</dt><dd><x-sta.phase-chip :phase="$s->phase" :label="'Phase '.$s->phase.' · '.\App\Models\GamePhase::NAMES[$s->phase]" /></dd></div>
            <div><dt class="sta-muted">Status</dt><dd><x-sta.pill :status="\App\Support\Sta::submissionPill($s->status)">{{ $s->status->label() }}</x-sta.pill></dd></div>
            <div><dt class="sta-muted">Reference</dt><dd>{{ $s->reference_no ?? '–' }}</dd></div>
            <div><dt class="sta-muted">Deadline</dt><dd>{{ $deadline?->format('j M Y H:i') ?? 'none' }}</dd></div>
            <div><dt class="sta-muted">PDFs</dt><dd>
                @forelse ($links as $link)
                    <a href="{{ $link['url'] }}" target="_blank" class="block">{{ $link['name'] }}</a>
                @empty
                    –
                @endforelse
            </dd></div>
        </dl>
    </x-sta.card>
```

Replace `resources/views/filament/partials/comments.blade.php` with:

```blade
<x-sta.card heading="Comments from STA">
    <ul class="sta-list">
        @forelse ($comments as $c)
            <li class="sta-list-item" style="flex-direction: column; align-items: flex-start; gap: 4px">
                <span class="sta-who-sub">{{ $c->user?->name ?? 'STA' }} · {{ $c->created_at->format('j M Y H:i') }}</span>
                <p class="whitespace-pre-line" style="margin: 0">{{ $c->body }}</p>
            </li>
        @empty
            <li class="sta-list-item sta-muted">No comments.</li>
        @endforelse
    </ul>
</x-sta.card>
```

- [ ] **Step 7: Brand the public invitation pages**

In `resources/views/invitations/accept.blade.php`, replace the `<style>…</style>` block with:

```html
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="{{ \App\Support\Sta::FONTS_URL }}" rel="stylesheet">
    <style>
        body{font-family:Inter,system-ui,Arial,sans-serif;background:#F5F7FA;color:#0F172A;margin:0;padding:24px;font-size:14px}
        main{max-width:440px;margin:40px auto;background:#fff;padding:28px;border-radius:14px;border:1px solid #E2E8F0}
        .brand{display:flex;align-items:center;gap:12px;margin-bottom:20px}
        .brand img{width:40px;height:56px;object-fit:contain;padding:4px;border:1px solid #E2E8F0;border-radius:10px;box-sizing:border-box}
        .brand small{display:block;font-size:12px;color:#475569}
        .brand strong{font-family:'Space Grotesk',sans-serif;font-size:16px}
        h1{font-family:'Space Grotesk',sans-serif;font-size:24px;margin:0 0 8px}
        label{display:block;margin-top:14px;font-weight:600;font-size:13px}
        input{width:100%;min-height:44px;padding:0 12px;margin-top:6px;border:1px solid #CBD5E1;border-radius:10px;box-sizing:border-box;font:14px Inter,sans-serif}
        input:focus-visible,button:focus-visible{outline:2px solid #1F3A5F;outline-offset:2px}
        button{margin-top:20px;width:100%;min-height:44px;background:#1F3A5F;color:#fff;border:0;border-radius:10px;font:600 14px Inter,sans-serif}
        .error{color:#B91C1C;font-size:13px}
    </style>
```

Then add this as the first child of `<main>`:

```html
    <div class="brand"><img src="{{ asset('images/boc-logo.webp') }}" alt="Bahrain Olympic Committee logo"><div><small>Bahrain Olympic Committee</small><strong>Sports Entry</strong><small>Association portal</small></div></div>
```

Replace `resources/views/invitations/invalid.blade.php` with:

```blade
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invitation unavailable</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="{{ \App\Support\Sta::FONTS_URL }}" rel="stylesheet">
    <style>
        body{font-family:Inter,system-ui,Arial,sans-serif;background:#F5F7FA;color:#0F172A;margin:0;padding:24px;font-size:14px}
        main{max-width:440px;margin:40px auto;background:#fff;padding:28px;border-radius:14px;border:1px solid #E2E8F0}
        .brand{display:flex;align-items:center;gap:12px;margin-bottom:20px}
        .brand img{width:40px;height:56px;object-fit:contain;padding:4px;border:1px solid #E2E8F0;border-radius:10px;box-sizing:border-box}
        .brand small{display:block;font-size:12px;color:#475569}
        .brand strong{font-family:'Space Grotesk',sans-serif;font-size:16px}
        h1{font-family:'Space Grotesk',sans-serif;font-size:24px;margin:0 0 8px}
    </style>
</head>
<body>
<main>
    <div class="brand"><img src="{{ asset('images/boc-logo.webp') }}" alt="Bahrain Olympic Committee logo"><div><small>Bahrain Olympic Committee</small><strong>Sports Entry</strong><small>Association portal</small></div></div>
    <h1>Invitation unavailable</h1>
    <p>This invitation is invalid or has expired. Please ask your administrator to send a new one.</p>
</main>
</body>
</html>
```

- [ ] **Step 8: Run the tests**

Run `php vendor/bin/pest tests/Feature/Ui/AdminListsUiTest.php tests/Feature/AssociationResourceTest.php tests/Feature/SportsTest.php tests/Feature/AuditLogTest.php tests/Feature/SubmissionViewTest.php tests/Feature/SubmissionCommentsTest.php tests/Feature/InvitationAcceptTest.php tests/Feature/InvitationUiTest.php tests/Feature/ClothingAndSettingsTest.php tests/Feature/StaUserResourceTest.php tests/Feature/ExtraFieldTest.php tests/Feature/GameFilesLayoutTest.php`. Expected: all pass.

- [ ] **Step 9: Commit**

```bash
git add app/Filament/Resources/Associations app/Filament/Resources/Sports app/Filament/Resources/ActivityLogs resources/views tests/Feature/Ui/AdminListsUiTest.php
git commit -m "feat: associations, sports, audit log, submission view, comments and invitation pages in the canvas look (M5)"
```

---

### Task 10: Game dashboard (phase cards, KPIs, who-has-submitted)

**Files:**
- Create:
  - `app/Support/GameProgress.php`
  - `tests/Feature/Ui/GameDashboardUiTest.php`
- Modify:
  - `app/Support/Sta.php` (add `when()`)
  - `app/Filament/Resources/Games/Pages/GameDashboard.php`
  - `resources/views/filament/resources/games/pages/game-dashboard.blade.php`
  - `resources/css/filament/sta/theme.css`
  - `tests/Feature/GameDashboardTest.php` (one listed line)

**Interfaces:**
- Consumes: `LongListChecks::phase2Numbers()` and `selection()`, `ShortListChecks::overLimits()`, `Sta::*`, `<x-sta.*>`.
- Produces:
  - `App\Support\GameProgress`:
    - `__construct(Game $game)`
    - `submission(Association $a, int $phase): ?Submission`
    - `status(Association $a, int $phase): string` (returns `submitted|awaiting|overdue|draft|no_response`)
    - `counts(int $phase): array{submitted:int, awaiting:int, draft:int, overdue:int, no_response:int, total:int}`
    - `openPhase(): ?GamePhase`
    - `lastActivity(Association $a): ?CarbonInterface`
    - `flags(Association $a, int $phase): ?string`
  - `Sta::when(CarbonInterface $t): string`
  - `GameDashboard`:
    - `public string $show = 'all'` and `public string $search = ''`
    - `progress(): GameProgress`
    - `focusPhase(): ?GamePhase`
    - `tally(): array{all:int, pending:int, submitted:int}`
    - `phaseMeta(GamePhase $p): string`
    - `flagCount(): int`
    - `sportsOf(Association $a): string`
  - `cell()` and `pdfLinks()` keep their signatures. `submissions()` is removed, and nothing else calls it.

**Listed test change:** in `tests/Feature/GameDashboardTest.php` line 39, change `->set('pendingOnly', true)` to `->set('show', 'pending')`. The canvas replaces the "Pending only" checkbox with the All / Pending / Submitted switch.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ui/GameDashboardUiTest.php`:

```php
<?php

use App\Enums\EventGender;
use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Games\Pages\GameDashboard;
use App\Models\Association;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\NumberEntry;
use App\Models\Person;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use App\Support\GameProgress;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
    $this->game = Game::factory()->create();
    $this->game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addDays(3)]);
    $this->done = Association::factory()->create(['name' => 'Done Fed', 'code' => 'DON']);
    $this->late = Association::factory()->create(['name' => 'Late Fed', 'code' => 'LAT']);
    $this->game->associations()->attach([$this->done->id, $this->late->id]);
    actingInAdmin(User::factory()->superAdmin()->create());
    Submission::for($this->game, $this->done, 2)->update(['status' => SubmissionStatus::Submitted, 'reference_no' => 'DON-G001-P2-R1']);
});

it('shows phase cards, KPI tiles and the who-has-submitted table', function () {
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->assertSee('Entry by Number')->assertSeeHtml('sta-phase-card is-active')
        ->assertSee('Submitted to STA')->assertSee('Awaiting association admin')->assertSee('In draft')->assertSee('Not started')->assertSee('Flags to review')
        ->assertSee('Who has submitted')->assertSee('All 2')->assertSee('Pending 1')->assertSee('Submitted 1')->assertSee('Last activity')
        ->assertSee('Remind pending (1)');
});

it('filters with the switch and the search box', function () {
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->set('show', 'submitted')->assertSee('Done Fed')->assertDontSee('Late Fed')
        ->set('show', 'all')->set('search', 'late')->assertSee('Late Fed')->assertDontSee('Done Fed');
});

it('flags Long List events above the Phase 2 number', function () {
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $gs = GameSport::factory()->create(['game_id' => $this->game->id, 'sport_id' => Sport::factory()->create(['association_id' => $this->done->id])->id]);
    $ev = GameEvent::factory()->create(['game_id' => $this->game->id, 'game_sport_id' => $gs->id,
        'event_id' => Event::factory()->create(['sport_id' => $gs->sport_id, 'name' => '-60kg', 'gender' => EventGender::Men])->id]);
    NumberEntry::create(['submission_id' => Submission::for($this->game, $this->done, 2)->id, 'game_event_id' => $ev->id, 'count' => 1]);
    $p3 = Submission::for($this->game, $this->done, 3);
    foreach (Person::factory()->count(2)->create(['association_id' => $this->done->id]) as $p) {
        $p3->entries()->create(['person_id' => $p->id, 'game_event_id' => $ev->id, 'game_sport_id' => $gs->id]);
    }

    $progress = new GameProgress($this->game->fresh());

    expect($progress->flags($this->done, 3))->toBe('1 differs from Phase 2')
        ->and($progress->flags($this->late, 3))->toBeNull()
        ->and($progress->counts(2))->toMatchArray(['submitted' => 1, 'no_response' => 1, 'total' => 2])
        ->and($progress->lastActivity($this->done))->not->toBeNull();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run `php vendor/bin/pest tests/Feature/Ui/GameDashboardUiTest.php`.

Expected: FAIL, because `App\Support\GameProgress` is not found.

- [ ] **Step 3: Create `GameProgress`**

Create `app/Support/GameProgress.php`:

```php
<?php

namespace App\Support;

use App\Enums\PhaseStatus;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\Game;
use App\Models\GamePhase;
use App\Models\Submission;
use App\Services\Phases\LongListChecks;
use App\Services\Phases\ShortListChecks;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Read-only status of one game's submissions: the game dashboard and the admin home share it (spec 17). Never creates submissions. */
final class GameProgress
{
    /** @var Collection<string, Submission> keyed "associationId-phase" */
    private Collection $subs;

    public function __construct(public readonly Game $game)
    {
        $game->loadMissing(['phases', 'associations']);
        $this->subs = Submission::withoutGlobalScopes()->where('game_id', $game->id)
            ->withExists(['answers', 'numbers', 'entries'])->get()->keyBy(fn (Submission $s) => "{$s->association_id}-{$s->phase}");
    }

    public function submission(Association $a, int $phase): ?Submission
    {
        return $this->subs->get("{$a->id}-{$phase}");
    }

    /** submitted | awaiting | overdue | draft | no_response: the rule the game dashboard has always used. */
    public function status(Association $a, int $phase): string
    {
        $s = $this->submission($a, $phase);
        $deadline = $this->game->phase($phase)->effectiveDeadline($s);

        return match (true) {
            $s?->status === SubmissionStatus::Submitted => 'submitted',
            $s?->status === SubmissionStatus::AwaitingAssocAdmin => 'awaiting',
            $deadline !== null && now()->greaterThan($deadline) => 'overdue',
            $s !== null && ($s->answers_exists || $s->numbers_exists || $s->entries_exists) => 'draft',
            default => 'no_response',
        };
    }

    /** @return array{submitted: int, awaiting: int, draft: int, overdue: int, no_response: int, total: int} */
    public function counts(int $phase): array
    {
        $c = array_fill_keys(['submitted', 'awaiting', 'draft', 'overdue', 'no_response'], 0);
        foreach ($this->game->associations as $a) {
            $c[$this->status($a, $phase)]++;
        }

        return $c + ['total' => $this->game->associations->count()];
    }

    public function openPhase(): ?GamePhase
    {
        return $this->game->phases->where('enabled', true)->sortBy('phase')->first(fn (GamePhase $p) => $p->status === PhaseStatus::Open);
    }

    public function lastActivity(Association $a): ?CarbonInterface
    {
        return $this->subs->where('association_id', $a->id)->max('updated_at');
    }

    /**
     * What STA should look at for one phase (spec 17 "Flags"): Long List events above the submitted Phase 2 number, or Short List maximums exceeded.
     * ponytail: a few queries per association; fine for the ~50 rows of a game, cache per request if dashboards slow down.
     */
    public function flags(Association $a, int $phase): ?string
    {
        $s = $this->submission($a, $phase);
        if ($s === null || ! in_array($phase, [3, 4], true)) {
            return null;
        }
        if ($phase === 3) {
            $long = app(LongListChecks::class);
            $p2 = $long->phase2Numbers($s);
            if ($p2 === null) {
                return null;
            }
            [$athletes] = $long->selection($s);
            $n = collect($athletes)->filter(fn ($ids, $ge) => count($ids) > ($p2[$ge] ?? 0))->count();

            return $n ? $n.' '.($n === 1 ? 'differs' : 'differ').' from Phase 2' : null;
        }
        $n = count(app(ShortListChecks::class)->overLimits($s));

        return $n ? $n.' over a maximum' : null;
    }
}
```

In `app/Support/Sta.php`, add:

```php
    /** "Today, 10:42", "Yesterday" or "7 Oct" (canvas Last activity). */
    public static function when(CarbonInterface $t): string
    {
        $t = $t->copy()->timezone('Asia/Bahrain');

        return match (true) {
            $t->isToday() => 'Today, '.$t->format('H:i'),
            $t->isYesterday() => 'Yesterday',
            default => $t->format('j M'),
        };
    }
```

- [ ] **Step 4: Rework `GameDashboard`**

In `GameDashboard.php`, add `use App\Support\GameProgress;` and `use App\Support\Sta;`. Then make these changes:
- Delete the property `public bool $pendingOnly = false;`, the property `protected ?Collection $loaded`, and the method `submissions()`.
- Change `loadSubmissions()` to `$this->progress = null;`.
- Replace `getTitle()`, `rows()` and `cell()`, and add the rest of the methods below.
- In `remindPending`, change the label to `->label(fn () => 'Remind pending ('.$this->tally()['pending'].')')`.

```php
    /** All | pending | submitted (canvas switch); pending = not submitted in an open phase. */
    public string $show = 'all';

    public string $search = '';

    protected ?GameProgress $progress = null;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        return $this->record->start_date?->format('j M').' – '.$this->record->end_date?->format('j M Y')
            .' · '.$this->record->associations()->count().' associations invited · '.$this->record->gameSports()->count().' sports';
    }

    public function progress(): GameProgress
    {
        return $this->progress ??= new GameProgress($this->record);
    }

    /** @return Collection<int, Association> */
    public function rows(): Collection
    {
        $needle = mb_strtolower(trim($this->search));

        return $this->record->associations()->orderBy('name')->get()
            ->when($this->show === 'pending', fn ($c) => $c->filter(fn ($a) => $this->isPending($a)))
            ->when($this->show === 'submitted', fn ($c) => $c->reject(fn ($a) => $this->isPending($a)))
            ->when($needle !== '', fn ($c) => $c->filter(fn ($a) => str_contains(mb_strtolower($a->name.' '.$a->code), $needle)))
            ->values();
    }

    private function isPending(Association $a): bool
    {
        return $this->phases()->where('status', PhaseStatus::Open)->contains(fn ($p) => $this->cell($a, $p->phase)['status'] !== 'submitted');
    }

    /** @return array{all: int, pending: int, submitted: int} counts for the switch, ignoring the current filter */
    public function tally(): array
    {
        $all = $this->record->associations()->get();
        $pending = $all->filter(fn ($a) => $this->isPending($a))->count();

        return ['all' => $all->count(), 'pending' => $pending, 'submitted' => $all->count() - $pending];
    }

    /** @return array{status: string, label: string, submission: ?Submission} */
    public function cell(Association $a, int $phase): array
    {
        $status = $this->progress()->status($a, $phase);

        return ['status' => $status, 'label' => Sta::CELL_LABELS[$status], 'submission' => $this->progress()->submission($a, $phase)];
    }

    public function focusPhase(): ?GamePhase
    {
        return $this->progress()->openPhase() ?? $this->phases()->last();
    }

    public function phaseMeta(GamePhase $p): string
    {
        if (! $p->enabled) {
            return 'Not used for this game';
        }

        return match ($p->status) {
            PhaseStatus::Draft => 'Not opened yet',
            PhaseStatus::Open => $p->deadline ? 'Deadline '.$p->deadline->format('j M Y').' · '.Sta::daysLeft($p->deadline) : 'Open, no deadline',
            PhaseStatus::Closed => (function () use ($p) {
                $c = $this->progress()->counts($p->phase);

                return "Closed · {$c['submitted']} of {$c['total']} submitted";
            })(),
        };
    }

    public function flagCount(): int
    {
        $focus = $this->focusPhase();

        return $focus ? $this->record->associations->filter(fn ($a) => $this->progress()->flags($a, $focus->phase) !== null)->count() : 0;
    }

    public function sportsOf(Association $a): string
    {
        return $this->record->sportsFor($a)->map(fn ($gs) => $gs->sport->name)->implode(' · ');
    }
```

- [ ] **Step 5: Rewrite the dashboard view**

Replace `resources/views/filament/resources/games/pages/game-dashboard.blade.php` with:

```blade
<x-filament-panels::page>
    @php
        $phases = $this->phases();
        $progress = $this->progress();
        $focus = $this->focusPhase();
        $counts = $focus ? $progress->counts($focus->phase) : null;
        $rows = $this->rows();
        $tally = $this->tally();
    @endphp

    <section aria-label="Phases" class="sta-grid-phase">
        @foreach ($record->phases->sortBy('phase') as $p)
            @php [$label, $pill] = \App\Support\Sta::phaseStatus($p); @endphp
            <x-sta.phase-card :phase="$p->phase" :name="\App\Models\GamePhase::NAMES[$p->phase]" :status="$pill" :status-label="$label"
                :meta="$this->phaseMeta($p)" :active="$p->enabled && $p->status === \App\Enums\PhaseStatus::Open" />
        @endforeach
    </section>

    @if ($counts)
        <section aria-label="Phase {{ $focus->phase }} progress" class="sta-grid-kpi">
            <x-sta.kpi label="Submitted to STA" :value="$counts['submitted']" :hint="'of '.$counts['total'].' associations'" tone="submitted" />
            <x-sta.kpi label="Awaiting association admin" :value="$counts['awaiting']" hint="editor submitted, not approved" tone="awaiting" />
            <x-sta.kpi label="In draft" :value="$counts['draft']" hint="started, not submitted" tone="draft" />
            <x-sta.kpi label="Not started" :value="$counts['no_response']" :hint="'no entries yet'.($counts['overdue'] ? ' · '.$counts['overdue'].' overdue' : '')" tone="none" />
            <x-sta.kpi label="Flags to review" :value="$this->flagCount()" hint="differences and warnings" tone="warn" />
        </section>
    @endif

    <x-sta.card heading="Who has submitted"
        :description="$focus ? 'Phase '.$focus->phase.' is '.strtolower(\App\Support\Sta::phaseStatus($focus)[0]).' · reminders go out automatically '.implode(', ', $record->reminder_days ?? []).' days before the deadline' : null">
        <x-slot:actions>
            <div class="sta-seg" role="group" aria-label="Filter">
                @foreach (['all' => 'All', 'pending' => 'Pending', 'submitted' => 'Submitted'] as $key => $label)
                    <button type="button" wire:click="$set('show', '{{ $key }}')" aria-pressed="{{ $show === $key ? 'true' : 'false' }}">{{ $label }} {{ $tally[$key] }}</button>
                @endforeach
            </div>
            <label class="sta-search">
                <span class="sta-sr-only">Search associations</span>
                <input type="search" class="sta-input" placeholder="Search associations" wire:model.live.debounce.300ms="search">
            </label>
        </x-slot:actions>

        <div class="sta-scroll">
            <table class="sta-table" style="min-width: 760px">
                <thead>
                    <tr>
                        <th scope="col">Association</th>
                        @foreach ($phases as $p)<th scope="col"><x-sta.phase-chip :phase="$p->phase" /></th>@endforeach
                        <th scope="col">Flags</th>
                        <th scope="col">Last activity</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $a)
                        @php
                            $flags = $focus ? $progress->flags($a, $focus->phase) : null;
                            $last = $progress->lastActivity($a);
                        @endphp
                        <tr wire:key="row-{{ $a->id }}">
                            <td>
                                <div class="sta-who">
                                    <x-sta.assoc-avatar :association="$a" />
                                    <div class="sta-who-text"><span class="sta-who-name">{{ $a->name }}</span><span class="sta-who-sub">{{ $a->code }} · {{ $this->sportsOf($a) }}</span></div>
                                </div>
                            </td>
                            @foreach ($phases as $p)
                                @php
                                    $c = $this->cell($a, $p->phase);
                                    $s = $c['submission'];
                                @endphp
                                <td wire:key="cell-{{ $a->id }}-{{ $p->phase }}">
                                    <div class="sta-cell">
                                        <x-sta.pill :status="\App\Support\Sta::cellPill($c['status'])">{{ $c['label'] }}</x-sta.pill>
                                        @if ($s)
                                            <a href="{{ \App\Filament\Resources\Games\GameResource::getUrl('submission', ['record' => $record, 'submission' => $s]) }}" aria-label="View {{ $a->name }} Phase {{ $p->phase }}">View</a>
                                        @endif
                                        @if ($s?->deadline_override)
                                            <span class="sta-who-sub">Deadline: {{ $s->deadline_override->format('d M Y H:i') }}</span>
                                        @endif
                                        @if ($c['status'] === 'submitted')
                                            <span class="sta-who-sub">{{ $s->reference_no }}</span>
                                            @foreach ($this->pdfLinks($s) as $link)
                                                <a href="{{ $link['url'] }}" target="_blank" class="sta-who-sub">{{ $link['name'] }}</a>
                                            @endforeach
                                        @endif
                                        @if ($this->canManage())
                                            @if ($c['status'] !== 'submitted')
                                                <x-filament::link tag="button" size="sm" wire:click="mountAction('extend', { association: {{ $a->id }}, phase: {{ $p->phase }} })">Extend</x-filament::link>
                                            @endif
                                            @if (in_array($c['status'], ['submitted', 'awaiting']))
                                                <x-filament::link tag="button" size="sm" color="danger" wire:click="mountAction('reopen', { association: {{ $a->id }}, phase: {{ $p->phase }} })">Re-open</x-filament::link>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                            <td @class(['sta-flag' => $flags, 'sta-muted' => ! $flags])>{{ $flags ?? 'None' }}</td>
                            <td class="sta-muted">{{ $last ? \App\Support\Sta::when($last) : '–' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $phases->count() + 3 }}" class="sta-muted">No associations match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-slot:footer>Showing {{ $rows->count() }} of {{ $tally['all'] }} associations</x-slot:footer>
    </x-sta.card>
</x-filament-panels::page>
```

- [ ] **Step 6: Add the CSS and update the listed test line**

Append to `theme.css`:

```css
/* Task 10: game dashboard */
.sta-cell { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 8px; }
.sta-flag { color: var(--sta-warn); font-size: 13px; }
.sta-search .sta-input { width: 200px; }
```

In `tests/Feature/GameDashboardTest.php` line 39, change `->set('pendingOnly', true)` to `->set('show', 'pending')`.

- [ ] **Step 7: Run the tests**

Run `php vendor/bin/pest tests/Feature/Ui/GameDashboardUiTest.php tests/Feature/GameDashboardTest.php tests/Feature/SubmissionViewTest.php tests/Feature/PhaseRemindersTest.php tests/Feature/SubmissionWorkflowTest.php`. Expected: all pass. "Submitted", "No response" and "In progress" are still the cell labels, and the submission URL is still linked.

- [ ] **Step 8: Commit**

```bash
git add app/Support app/Filament/Resources/Games/Pages/GameDashboard.php resources/views/filament/resources/games/pages/game-dashboard.blade.php resources/css/filament/sta/theme.css tests/Feature/Ui/GameDashboardUiTest.php tests/Feature/GameDashboardTest.php
git commit -m "feat: game dashboard with phase cards, KPIs, All/Pending/Submitted switch, flags and last activity (M5)"
```

---

### Task 11: Admin home dashboard

**Files:**
- Create:
  - `app/Filament/Pages/Dashboard.php`
  - `resources/views/filament/pages/dashboard.blade.php`
  - `tests/Feature/Ui/AdminHomeTest.php`
- Modify:
  - `app/Providers/Filament/AdminPanelProvider.php`
  - `resources/css/filament/sta/theme.css`

**Interfaces:**
- Consumes: `GameProgress` (Task 10), `Sta::when`, `Sta::daysLeft`, `<x-sta.*>`, `Invitation::pending()`, and the activity properties `medical_changed` and `documents_changed`.
- Produces: `App\Filament\Pages\Dashboard` with these methods:
  - `activeGames(): Collection<array{game, progress, phase, counts}>`
  - `attention(Collection $games): list<array{tone, title, text, url, link}>`
  - `registry(): ?array`
  - `associations(): Collection`
  - `recentActivity(): Collection`

  Each section is gated by the permission that already guards its data: `view_submissions`, `manage_associations`, `manage_people` and `view_audit_log`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ui/AdminHomeTest.php`:

```php
<?php

use App\Enums\PhaseStatus;
use App\Filament\Pages\Dashboard;
use App\Models\Association;
use App\Models\Game;
use App\Models\Person;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    $this->game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addDays(6)->addHour()]);
    $this->assoc = Association::factory()->create(['name' => 'Bahrain Weightlifting Federation', 'code' => 'BWF']);
    $this->game->associations()->attach($this->assoc);
    Person::factory()->create(['association_id' => $this->assoc->id]);
});

it('shows the canvas home sections to a super admin', function () {
    actingInAdmin(User::factory()->superAdmin()->create());

    livewire(Dashboard::class)
        ->assertSee('Active games')->assertSee('Asian Indoor Games')->assertSee('Phase 3 · Long List')
        ->assertSee('Associations submitted')->assertSee('0 of 1')->assertSee('6 days')
        ->assertSee('Needs your attention')->assertSee('1 phase deadline this week')
        ->assertSee('Registry health')->assertSee('Bahrain Weightlifting Federation')->assertSee('No admin')
        ->assertSee('Recent activity')->assertSee('New game');
});

it('hides the sections a user may not see', function () {
    actingInAdmin(User::factory()->sta()->create());

    livewire(Dashboard::class)->assertOk()
        ->assertDontSee('Asian Indoor Games')->assertDontSee('Registry health')->assertDontSee('Recent activity');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run `php vendor/bin/pest tests/Feature/Ui/AdminHomeTest.php`.

Expected: FAIL with `Class "App\Filament\Pages\Dashboard" not found`.

- [ ] **Step 3: Create the page class**

Create `app/Filament/Pages/Dashboard.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Enums\PersonType;
use App\Enums\Role;
use App\Enums\StaPermission;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Associations\AssociationResource;
use App\Filament\Resources\Games\GameResource;
use App\Models\Association;
use App\Models\Game;
use App\Models\GamePhase;
use App\Models\Invitation;
use App\Models\Person;
use App\Support\GameProgress;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/** Admin home (canvas AdminDashboard): active games, what needs attention, registry health, associations, recent activity. Read-only. */
class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    public function getPageClasses(): array
    {
        return ['sta-bg-alt'];
    }

    public function getSubheading(): ?string
    {
        return now('Asia/Bahrain')->format('l, j F Y').' · all games and associations';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newGame')->label('New game')->icon(Heroicon::OutlinedPlus)
                ->url(fn () => GameResource::getUrl('create'))
                ->visible(fn () => $this->allowed(StaPermission::ManageGames)),
        ];
    }

    public function allowed(StaPermission $p): bool
    {
        return (bool) auth()->user()?->can($p->value);
    }

    /** @return Collection<int, array{game: Game, progress: GameProgress, phase: ?GamePhase, counts: ?array}> published games that have not ended */
    public function activeGames(): Collection
    {
        if (! $this->allowed(StaPermission::ViewSubmissions)) {
            return collect();
        }

        return once(fn () => Game::whereNotNull('published_at')->whereDate('end_date', '>=', today())->withCount('gameSports')->orderBy('start_date')->get()
            ->map(function (Game $g) {
                $progress = new GameProgress($g);
                $phase = $progress->openPhase();

                return ['game' => $g, 'progress' => $progress, 'phase' => $phase, 'counts' => $phase ? $progress->counts($phase->phase) : null];
            }));
    }

    /** @return list<array{tone: string, title: string, text: string, url: ?string, link: string}> */
    public function attention(Collection $games): array
    {
        $items = [];
        $soon = $games->filter(fn ($g) => $g['phase']?->deadline?->between(now(), now()->addDays(7)));
        if ($soon->isNotEmpty()) {
            $items[] = ['tone' => 'warn', 'title' => $soon->count().' phase '.str('deadline')->plural($soon->count()).' this week',
                'text' => $soon->map(fn ($g) => $g['game']->name.' · Phase '.$g['phase']->phase.' '.GamePhase::NAMES[$g['phase']->phase].' closes '.$g['phase']->deadline->format('D j M'))->implode(' · '),
                'url' => GameResource::getUrl('dashboard', ['record' => $soon->first()['game']]), 'link' => 'Open'];
        }
        foreach ($games as $g) {
            $pending = $g['counts'] ? $g['counts']['total'] - $g['counts']['submitted'] : 0;
            if ($pending > 0) {
                $items[] = ['tone' => 'none', 'title' => $pending.' '.str('association')->plural($pending).' haven’t submitted the '.GamePhase::NAMES[$g['phase']->phase],
                    'text' => $g['game']->name.' · '.$g['counts']['no_response'].' not started',
                    'url' => GameResource::getUrl('dashboard', ['record' => $g['game']]), 'link' => 'Open dashboard'];
            }
        }
        if ($this->allowed(StaPermission::ManageAssociations) && ($n = Invitation::pending()->count())) {
            $items[] = ['tone' => 'none', 'title' => $n.' '.str('invitation')->plural($n).' pending', 'text' => 'Waiting for the invited person to accept',
                'url' => AssociationResource::getUrl(), 'link' => 'Manage'];
        }

        return $items;
    }

    /** @return array{athletes: int, officials: int, complete: int, incomplete: int, expiring: int, perAssociation: Collection, byAssociation: Collection}|null */
    public function registry(): ?array
    {
        if (! $this->allowed(StaPermission::ManagePeople)) {
            return null;
        }

        return once(function () {
            // ponytail: completeness needs each person's fields; fine for a few thousand people, store a flag if the registry grows past that.
            $people = Person::withoutGlobalScopes()->with(['fieldValues', 'association'])->get()->map(fn (Person $p) => [
                'type' => $p->type, 'complete' => $p->isComplete(), 'code' => $p->association?->code,
                'expiring' => (bool) $p->passport_expiry?->lt(now()->addMonths(6)),
            ]);
            $per = $people->groupBy('code')->map(fn ($g, $code) => ['code' => $code, 'complete' => $g->where('complete', true)->count(), 'total' => $g->count()]);

            return [
                'athletes' => $people->where('type', PersonType::Athlete)->count(),
                'officials' => $people->where('type', PersonType::Official)->count(),
                'complete' => $people->where('complete', true)->count(),
                'incomplete' => $people->where('complete', false)->count(),
                'expiring' => $people->where('expiring', true)->count(),
                'perAssociation' => $per,
                'byAssociation' => $per->sortByDesc('total')->take(6)->values(),
            ];
        });
    }

    /** @return Collection<int, array{association: Association, admin: string, adminPill: string, editors: int, people: int, pct: ?int, last: ?Carbon}> */
    public function associations(): Collection
    {
        if (! $this->allowed(StaPermission::ManageAssociations)) {
            return collect();
        }
        $per = $this->registry()['perAssociation'] ?? collect();

        return Association::with('users')->withCount('people')->withMax('people', 'updated_at')
            ->withCount(['invitations as admin_invites_count' => fn ($q) => $q->pending()->where('role', Role::AssocAdmin)])
            ->orderByDesc('people_max_updated_at')->orderBy('name')->limit(8)->get()
            ->map(function (Association $a) use ($per) {
                $admin = $a->users->first(fn ($u) => $u->role === Role::AssocAdmin && $u->is_active);
                $row = $per->get($a->code);

                return [
                    'association' => $a,
                    'admin' => $admin?->name ?? ($a->admin_invites_count ? 'Invite pending' : 'No admin'),
                    'adminPill' => $admin ? 'ok' : ($a->admin_invites_count ? 'awaiting' : 'warn'),
                    'editors' => $a->users->filter(fn ($u) => $u->role === Role::AssocEditor && $u->is_active)->count(),
                    'people' => $a->people_count,
                    'pct' => $row && $row['total'] ? (int) round($row['complete'] / $row['total'] * 100) : null,
                    'last' => $a->people_max_updated_at ? Carbon::parse($a->people_max_updated_at) : null,
                ];
            });
    }

    /** @return Collection<int, Activity> */
    public function recentActivity(): Collection
    {
        return $this->allowed(StaPermission::ViewAuditLog) ? Activity::with('causer')->latest('id')->limit(6)->get() : collect();
    }

    public function auditLogUrl(): string
    {
        return ActivityLogResource::getUrl();
    }
}
```

In `AdminPanelProvider`, delete `->pages([Dashboard::class])` and the `use Filament\Pages\Dashboard;` import. `discoverPages` registers `App\Filament\Pages\Dashboard`, so registering Filament's own Dashboard as well would give two `/` routes.

- [ ] **Step 4: Create the view**

Create `resources/views/filament/pages/dashboard.blade.php`:

```blade
<x-filament-panels::page>
    @php
        $games = $this->activeGames();
        $attention = $this->attention($games);
        $registry = $this->registry();
        $associations = $this->associations();
        $activity = $this->recentActivity();
    @endphp

    @if ($this->allowed(\App\Enums\StaPermission::ViewSubmissions))
        <section aria-labelledby="games-title" class="sta-stack sta-band-blue">
            <div class="sta-band-strip"><span class="sta-band-icon" aria-hidden="true"><x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedFlag" /></span><h2 id="games-title">Active games</h2></div>
            <div class="sta-grid-cards" style="grid-template-columns: repeat(auto-fit, minmax(min(380px, 100%), 1fr))">
                @forelse ($games as $g)
                    @php
                        $game = $g['game'];
                        $phase = $g['phase'];
                        $c = $g['counts'];
                        $pct = $c && $c['total'] ? (int) round($c['submitted'] / $c['total'] * 100) : 0;
                    @endphp
                    <a href="{{ \App\Filament\Resources\Games\GameResource::getUrl('dashboard', ['record' => $game]) }}" @class(['sta-card sta-game-card', 'is-active' => $loop->first])>
                        <div class="sta-row-between" style="align-items: flex-start">
                            <div class="sta-who-text">
                                <span class="sta-phase-card-name" style="font-size: 18px">{{ $game->name }}</span>
                                <span class="sta-muted">{{ $game->start_date->format('j M') }} – {{ $game->end_date->format('j M Y') }} · {{ $game->associations->count() }} associations · {{ $game->game_sports_count }} sports</span>
                            </div>
                            @if ($phase)<x-sta.phase-chip :phase="$phase->phase" :label="'Phase '.$phase->phase.' · '.\App\Models\GamePhase::NAMES[$phase->phase]" />@endif
                        </div>
                        @if ($phase && $phase->deadline)
                            <div class="sta-facts">
                                <div><span class="sta-who-sub">Deadline</span><strong>{{ $phase->deadline->format('D j M Y') }}</strong></div>
                                <div><span class="sta-who-sub">Time left</span><strong class="sta-time-left">{{ str_replace(' left', '', \App\Support\Sta::daysLeft($phase->deadline)) }}</strong></div>
                            </div>
                        @endif
                        @if ($c)
                            <div class="sta-meter">
                                <div class="sta-meter-row"><span class="sta-meter-label">Associations submitted</span><span class="sta-meter-value">{{ $c['submitted'] }} of {{ $c['total'] }}</span></div>
                                <div class="sta-meter-track"><div class="sta-meter-bar" style="width: {{ $pct }}%"></div></div>
                                <span class="sta-who-sub">{{ $c['awaiting'] }} awaiting association admin · {{ $c['draft'] }} in draft · {{ $c['no_response'] + $c['overdue'] }} not started</span>
                            </div>
                        @else
                            <span class="sta-muted">No phase is open.</span>
                        @endif
                        <span class="sta-link-strong">Open game dashboard →</span>
                    </a>
                @empty
                    <p class="sta-muted">No active games.</p>
                @endforelse
            </div>
        </section>
    @endif

    <div class="sta-split">
        <x-sta.card class="sta-main" band="amber" heading="Needs your attention" description="Across the active games · next 7 days">
            <x-slot:icon><x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedBellAlert" /></x-slot:icon>
            <ul class="sta-list">
                @forelse ($attention as $item)
                    <li class="sta-list-item">
                        <span @class(['sta-attn-icon', 'is-warn' => $item['tone'] === 'warn']) aria-hidden="true"><x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedClock" /></span>
                        <div class="sta-who-text" style="flex: 1 1 260px"><span class="sta-who-name">{{ $item['title'] }}</span><span class="sta-muted">{{ $item['text'] }}</span></div>
                        @if ($item['url'])<a href="{{ $item['url'] }}" class="sta-btn">{{ $item['link'] }}</a>@endif
                    </li>
                @empty
                    <li class="sta-list-item sta-muted">Nothing needs your attention.</li>
                @endforelse
            </ul>
        </x-sta.card>

        @if ($registry)
            <x-sta.card class="sta-aside" band="teal" heading="Registry health" description="Permanent registry of all associations">
                <x-slot:icon><x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedUsers" /></x-slot:icon>
                <div class="sta-card-body sta-grid-kpi" style="grid-template-columns: repeat(auto-fit, minmax(min(120px, 100%), 1fr))">
                    <div class="sta-who-text"><span class="sta-who-sub">Athletes</span><span class="sta-num">{{ number_format($registry['athletes']) }}</span></div>
                    <div class="sta-who-text"><span class="sta-who-sub">Team officials</span><span class="sta-num">{{ number_format($registry['officials']) }}</span></div>
                    <div class="sta-who-text"><span class="sta-who-sub">Complete</span><span class="sta-num" style="color: var(--sta-primary)">{{ number_format($registry['complete']) }}</span></div>
                    <div class="sta-who-text"><span class="sta-who-sub">Incomplete</span><span class="sta-num sta-tone-warn">{{ number_format($registry['incomplete']) }}</span></div>
                </div>
                @if ($registry['expiring'])
                    <div class="sta-card-body" style="padding-top: 0"><a class="sta-banner sta-banner-warn" href="{{ \App\Filament\Resources\People\PersonResource::getUrl() }}">{{ $registry['expiring'] }} passports expire within 6 months</a></div>
                @endif
                <div class="sta-card-body sta-stack" style="padding-top: 0">
                    <h3 class="sta-meter-label" style="margin: 0">By association</h3>
                    @foreach ($registry['byAssociation'] as $row)
                        <x-sta.meter :label="$row['code'] ?? '—'" :value="$row['complete']" :max="$row['total']" />
                    @endforeach
                </div>
                <x-slot:footer>Incomplete = missing any field needed for the Long List (full name, photo, passport scan, issuing country or issue date, CPR).</x-slot:footer>
            </x-sta.card>
        @endif
    </div>

    @if ($this->allowed(\App\Enums\StaPermission::ManageAssociations))
        <x-sta.card band="indigo" heading="Associations" description="Each has 1 admin and up to 2 editors">
            <x-slot:icon><x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedRectangleStack" /></x-slot:icon>
            <x-slot:actions><a class="sta-btn" href="{{ \App\Filament\Resources\Associations\AssociationResource::getUrl() }}">All associations</a></x-slot:actions>
            <div class="sta-scroll">
                <table class="sta-table" style="min-width: 760px">
                    <thead><tr><th scope="col">Association</th><th scope="col">Code</th><th scope="col">Admin</th><th scope="col">Editors</th><th scope="col">People</th><th scope="col">Complete</th><th scope="col">Last activity</th><th scope="col">Active</th></tr></thead>
                    <tbody>
                        @foreach ($associations as $r)
                            <tr>
                                <td><div class="sta-who"><x-sta.assoc-avatar :association="$r['association']" /><span class="sta-who-name">{{ $r['association']->name }}</span></div></td>
                                <td class="sta-who-name">{{ $r['association']->code }}</td>
                                <td><x-sta.pill :status="$r['adminPill']">{{ $r['admin'] }}</x-sta.pill></td>
                                <td>{{ $r['editors'] }} / 2</td>
                                <td>{{ $r['people'] }}</td>
                                <td>{{ $r['pct'] === null ? '—' : $r['pct'].'%' }}</td>
                                <td class="sta-muted">{{ $r['last'] ? \App\Support\Sta::when($r['last']) : '–' }}</td>
                                <td><x-sta.pill :status="$r['association']->is_active ? 'ok' : 'closed'">{{ $r['association']->is_active ? 'Active' : 'Inactive' }}</x-sta.pill></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-slot:footer>Showing {{ $associations->count() }} · sorted by last activity</x-slot:footer>
        </x-sta.card>
    @endif

    @if ($this->allowed(\App\Enums\StaPermission::ViewAuditLog))
        <x-sta.card band="slate" heading="Recent activity" description="From the audit log · medical changes are marked, values are never shown">
            <x-slot:icon><x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedClipboardDocumentList" /></x-slot:icon>
            <x-slot:actions><a href="{{ $this->auditLogUrl() }}">Open audit log</a></x-slot:actions>
            <ol class="sta-list">
                @forelse ($activity as $e)
                    <li class="sta-list-item">
                        <span class="sta-muted" style="flex: 0 0 104px">{{ \App\Support\Sta::when($e->created_at) }}</span>
                        <span style="flex: 1 1 320px">
                            <strong>{{ $e->causer?->name ?? 'System' }}</strong>
                            @if ($e->causer?->role)<span class="sta-muted">({{ $e->causer->role->label() }})</span>@endif
                            {{ $e->event ?? $e->description }} {{ $e->subject_type ? class_basename($e->subject_type) : '' }}
                            @if ($e->properties->get('medical_changed'))<x-sta.pill status="closed">Medical data changed</x-sta.pill>@endif
                            @if ($e->properties->get('documents_changed'))<x-sta.pill status="draft">Documents changed</x-sta.pill>@endif
                        </span>
                    </li>
                @empty
                    <li class="sta-list-item sta-muted">No activity yet.</li>
                @endforelse
            </ol>
        </x-sta.card>
    @endif
</x-filament-panels::page>
```

- [ ] **Step 5: Add the CSS**

Append to `theme.css`:

```css
/* Task 11: admin home */
.sta-game-card { padding: 20px; display: flex; flex-direction: column; gap: 14px; text-decoration: none; color: var(--sta-text) !important; }
.sta-game-card.is-active { border-color: var(--sta-primary); box-shadow: 0 0 0 3px #DCE6F2; }
.sta-facts { display: flex; flex-wrap: wrap; gap: 8px 24px; align-items: baseline; }
.sta-facts > div { display: flex; flex-direction: column; gap: 2px; }
.sta-time-left { font-family: var(--sta-font-heading); font-size: 20px; color: var(--sta-warn); }
.sta-link-strong { font-weight: 600; color: var(--sta-primary); }
.sta-num { font-family: var(--sta-font-heading); font-size: 24px; font-weight: 700; }
.sta-attn-icon { width: 36px; height: 36px; border-radius: var(--sta-radius-ctl); background: #F1F5F9; color: var(--sta-text-3); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sta-attn-icon.is-warn { background: #FFEDD5; color: var(--sta-warn); }
.sta-attn-icon svg { width: 18px; height: 18px; }
```

- [ ] **Step 6: Run the tests**

Run `php vendor/bin/pest tests/Feature/Ui/AdminHomeTest.php tests/Feature/Ui/ShellTest.php tests/Feature/PanelAccessTest.php tests/Feature/SmokeTest.php`. Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Pages/Dashboard.php resources/views/filament/pages/dashboard.blade.php app/Providers/Filament/AdminPanelProvider.php resources/css/filament/sta/theme.css tests/Feature/Ui/AdminHomeTest.php
git commit -m "feat: admin home dashboard from the canvas: active games, attention, registry health, associations, activity (M5)"
```

---

### Task 12: Reports, ready-made report preview and report builder

**Files:**
- Create: `tests/Feature/Ui/ReportsUiTest.php`
- Modify:
  - `resources/views/filament/pages/reports.blade.php`
  - `resources/views/filament/resources/games/pages/game-reports.blade.php`
  - `resources/views/filament/pages/report-builder.blade.php`
  - `resources/css/filament/sta/theme.css`

**Interfaces:**
- Consumes:
  - `Reports::mine()`, `shared()`, `games()`
  - `GameReports::$report`, `reportData()`, `export()`
  - `GameReports::REPORTS`
  - `SavedReport::VISIBILITIES`
  - the builder's existing Livewire state (unchanged)
- Produces: no new PHP interfaces. The views keep every `wire:` binding.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ui/ReportsUiTest.php`:

```php
<?php

use App\Filament\Pages\ReportBuilder;
use App\Filament\Pages\Reports;
use App\Filament\Resources\Games\Pages\GameReports;
use App\Models\Game;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(fn () => actingInAdmin(User::factory()->superAdmin()->create()));

it('shows ready-made and saved reports as canvas bands', function () {
    Game::factory()->create(['name' => 'Asian Indoor Games']);

    livewire(Reports::class)->assertSee('Ready-made reports')->assertSee('Saved reports')->assertSee('My reports (0)')
        ->assertSee('Asian Indoor Games')->assertSee('Open ready-made reports')->assertSee('New report');
});

it('lists the ready-made reports beside the preview with phase chips', function () {
    $game = Game::factory()->create();

    livewire(GameReports::class, ['record' => $game->getRouteKey()])
        ->assertSeeHtml('aria-current="true"')->assertSee('Yes / No / Maybe per association and sport')->assertSeeHtml('sta-ph-1')
        ->set('report', 'longList')->assertSee('Everyone entered in Phase 3')->assertSee('Only submitted entries are included');
});

it('lays the builder out as settings beside results', function () {
    livewire(ReportBuilder::class)->assertSeeHtml('sta-builder-settings')->assertSee('Updates as you change settings');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run `php vendor/bin/pest tests/Feature/Ui/ReportsUiTest.php`.

Expected: FAIL, because "Ready-made reports" is not shown.

- [ ] **Step 3: Rewrite the Reports view**

Replace `resources/views/filament/pages/reports.blade.php` with the code below. The header action "New report" stays. `BuilderPageTest` asserts the order "My reports … Shared … by Other".

```blade
<x-filament-panels::page>
    @php $subjects = \App\Services\Reports\Builder\Registry::all(); @endphp

    <section aria-labelledby="ready-title" class="sta-stack sta-band-blue">
        <div class="sta-band-strip"><span class="sta-band-icon" aria-hidden="true"><x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedDocumentChartBar" /></span><h2 id="ready-title">Ready-made reports</h2></div>
        <x-sta.card>
            <ul class="sta-list">
                @forelse ($this->games() as $id => $gameName)
                    <li class="sta-list-item">
                        <span class="sta-who-text" style="flex: 1 1 260px">
                            <span class="sta-who-name">{{ $gameName }}</span>
                            <span class="sta-muted">Phase 1 answers · Phase 2 totals · Long List · Short List · over limit · expiring documents · clothing sizes</span>
                        </span>
                        <a class="sta-btn" href="{{ \App\Filament\Resources\Games\GameResource::getUrl('reports', ['record' => $id]) }}">Open ready-made reports</a>
                    </li>
                @empty
                    <li class="sta-list-item sta-muted">No games yet.</li>
                @endforelse
            </ul>
        </x-sta.card>
    </section>

    <section aria-labelledby="saved-title" class="sta-stack sta-band-indigo">
        <div class="sta-band-strip"><span class="sta-band-icon" aria-hidden="true"><x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedBookmark" /></span><h2 id="saved-title">Saved reports</h2></div>
        @foreach (['My reports' => $this->mine(), 'Shared' => $this->shared()] as $heading => $reports)
            <h3 class="sta-subhead" style="padding-left: 0">{{ $heading }} ({{ $reports->count() }})</h3>
            <div class="sta-grid-cards">
                @forelse ($reports as $report)
                    <article class="sta-card sta-report-card">
                        <div class="sta-row-between" style="align-items: flex-start">
                            <h4 class="sta-card-title" style="font-size: 16px">{{ $report->name }}</h4>
                            <x-sta.pill :status="$report->visibility === 'shared' ? 'ok' : 'closed'">{{ \App\Models\SavedReport::VISIBILITIES[$report->visibility] ?? $report->visibility }}</x-sta.pill>
                        </div>
                        <dl class="sta-dl">
                            <dt>Subject</dt><dd>{{ ($subjects[$report->subject] ?? null)?->label() ?? $report->subject }}</dd>
                            @if ($heading === 'Shared')<dt>Owner</dt><dd>by {{ $report->creator->name }}</dd>@endif
                        </dl>
                        <span class="sta-who-sub">Last run {{ $report->last_run_at?->format('j M Y H:i') ?? 'never' }}</span>
                        <div><a class="sta-btn sta-btn-primary" href="{{ \App\Filament\Pages\ReportBuilder::getUrl(['report' => $report]) }}" aria-label="Open {{ $report->name }}">Open</a></div>
                    </article>
                @empty
                    <p class="sta-muted">No reports yet.</p>
                @endforelse
                @if ($heading === 'My reports')
                    <a href="{{ \App\Filament\Pages\ReportBuilder::getUrl() }}" class="sta-new-tile">+ New report</a>
                @endif
            </div>
        @endforeach
    </section>
</x-filament-panels::page>
```

- [ ] **Step 4: Rewrite the game reports view**

Replace `resources/views/filament/resources/games/pages/game-reports.blade.php` with:

```blade
<x-filament-panels::page>
    @php
        $data = $this->reportData();
        $meta = [
            'phase1Answers' => [1, 'Yes / No / Maybe per association and sport'],
            'phase2Totals' => [2, 'Athletes per association, sport and gender'],
            'longList' => [3, 'Everyone entered in Phase 3'],
            'shortList' => [4, 'Final entries with justifications'],
            'overLimit' => [4, 'Short Lists above a maximum, with justification'],
            'expiringDocuments' => [null, 'Passports expiring before the game ends'],
            'clothingSizeCounts' => [3, 'How many of each size, per item'],
            'clothingSizesByPerson' => [3, 'One row per person, one column per item'],
        ];
        [$phase, $hint] = $meta[$report] ?? [null, ''];
        $total = count($data['rows']);
    @endphp

    <p class="sta-muted">
        Only submitted entries are included. Expiring documents and clothing sizes use an association's Short List when its Phase 4 is submitted, otherwise its Long List.
    </p>

    <div class="sta-split">
        <ul class="sta-card sta-report-list" aria-label="Ready-made reports">
            @foreach (\App\Services\Reports\GameReports::REPORTS as $key => $label)
                <li>
                    <button type="button" class="sta-report-item" wire:click="$set('report', '{{ $key }}')" @if ($report === $key) aria-current="true" @endif>
                        <span class="sta-who-text"><span class="sta-who-name">{{ $label }}</span><span class="sta-who-sub">{{ $meta[$key][1] ?? '' }}</span></span>
                        @if ($meta[$key][0] ?? null)
                            <x-sta.phase-chip :phase="$meta[$key][0]" />
                        @else
                            <x-sta.pill status="closed">Any</x-sta.pill>
                        @endif
                    </button>
                </li>
            @endforeach
        </ul>

        <x-sta.card class="sta-main" :heading="\App\Services\Reports\GameReports::REPORTS[$report] ?? ''"
            :description="$hint.' · preview of the first '.min(500, $total).' of '.$total.' rows'">
            <x-slot:actions>
                @if ($phase)<x-sta.phase-chip :phase="$phase" />@endif
                <x-filament::button color="gray" wire:click="export" icon="heroicon-o-arrow-down-tray">Export to Excel</x-filament::button>
            </x-slot:actions>
            <div class="sta-scroll">
                <table class="sta-table">
                    <thead>
                        <tr>@foreach ($data['headings'] as $h)<th scope="col">{{ $h }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                        @forelse (array_slice($data['rows'], 0, 500) as $row)
                            <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                        @empty
                            <tr><td colspan="{{ count($data['headings']) }}" class="sta-muted">No rows.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-slot:footer>{{ $total > 500 ? 'Showing the first 500 of '.$total.' rows; the Excel export has all of them.' : 'Export to Excel gives every row.' }}</x-slot:footer>
        </x-sta.card>
    </div>
</x-filament-panels::page>
```

- [ ] **Step 5: Re-lay out the report builder**

Make these exact edits in `resources/views/filament/pages/report-builder.blade.php`. They change wrappers and classes only, and every `wire:` attribute stays as it is.

1. In the top `@php` block, change `$input = 'rounded border-gray-300 text-sm dark:bg-white/5';` to `$input = 'sta-input';`.
2. Change `<div class="flex flex-wrap items-end gap-3">` (the line under `{{-- Top bar --}}`) to `<div class="sta-row-between sta-builder-bar">`.
3. Replace `<x-filament::section heading="Settings">` with `<div class="sta-split"><aside class="sta-card sta-aside sta-builder-settings" aria-label="Report settings" style="flex: 1 1 320px"><div class="sta-card-body sta-stack">`.
4. Replace the `</x-filament::section>` right before `{{-- Results --}}` with `</div></aside>`.
5. Replace `<x-filament::section heading="Results">` with `<x-sta.card class="sta-main" heading="Results" description="Updates as you change settings"><div class="sta-card-body">`.
6. Replace the last `</x-filament::section>`, before `</x-filament-panels::page>`, with `</div></x-sta.card></div>`.
7. Replace every `<h3 class="mt-4 text-sm font-semibold">` with `<h3 class="sta-builder-label">`.
8. Change the results `<table class="w-full text-sm">` to `<table class="sta-table">`.

- [ ] **Step 6: Add the CSS**

Append to `theme.css`:

```css
/* Task 12: reports */
.sta-report-list { list-style: none; margin: 0; padding: 8px; flex: 1 1 280px; display: flex; flex-direction: column; gap: 2px; }
.sta-report-item { width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 10px; min-height: 52px; padding: 6px 12px; border: 0; border-radius: var(--sta-radius-ctl); background: transparent; text-align: left; cursor: pointer; font: inherit; color: inherit; }
.sta-report-item[aria-current] { background: var(--sta-selected); }
.sta-report-card { padding: 18px; display: flex; flex-direction: column; gap: 12px; }
.sta-dl { margin: 0; display: grid; grid-template-columns: 76px minmax(0, 1fr); gap: 4px 10px; font-size: 13px; }
.sta-dl dt { color: var(--sta-text-2); }
.sta-dl dd { margin: 0; }
.sta-new-tile { min-height: 200px; border: 1px dashed #94A3B8; border-radius: var(--sta-radius-card); display: flex; align-items: center; justify-content: center; font-weight: 600; color: var(--sta-primary); text-decoration: none; }
.sta-builder-label { margin: 8px 0 0; font-size: 12px; font-weight: 700; color: var(--sta-text-3); text-transform: uppercase; letter-spacing: .05em; }
.sta-builder-bar { align-items: flex-end; }
```

- [ ] **Step 7: Run the tests**

Run `php vendor/bin/pest tests/Feature/Ui/ReportsUiTest.php tests/Feature/ReportBuilder tests/Feature/GameReportsTest.php`. Expected: all pass. "Total (N)", "Choose a game.", "Choose one or more of the listed columns." and the missing `wire:click="save"` for non-editors are all unchanged.

- [ ] **Step 8: Commit**

```bash
git add resources/views/filament/pages resources/views/filament/resources/games/pages/game-reports.blade.php resources/css/filament/sta/theme.css tests/Feature/Ui/ReportsUiTest.php
git commit -m "feat: reports, ready-made report preview and report builder in the canvas layout (M5)"
```

---

### Task 13: PDFs match the samples (PdfPhase1–4)

**Files:**
- Create:
  - `app/Support/PdfContext.php`
  - `public/images/boc-logo.png`
  - `resources/views/pdf/partials/styles.blade.php`
  - `resources/views/pdf/partials/header.blade.php`
  - `resources/views/pdf/partials/signoff.blade.php`
  - `resources/views/pdf/partials/footer.blade.php`
  - `tests/Feature/Ui/PdfDesignTest.php`
- Modify:
  - `resources/views/pdf/entry-by-sport.blade.php`
  - `resources/views/pdf/entry-by-event.blade.php`
  - `resources/views/pdf/long-list.blade.php`
  - `app/Jobs/GenerateEntryBySportPdf.php`
  - `app/Jobs/GenerateEntryByEventPdfs.php`
  - `app/Jobs/GenerateLongListPdf.php` (`GenerateShortListPdf` extends it and uses the same `handle()`)
  - `tests/Feature/LongListPdfTest.php` and `tests/Feature/ShortListPdfTest.php` (the listed lines)

**Interfaces:**
- Consumes: `GenerateEntryByEventPdfs::dataUri(?string): ?string`, `Setting::NOC_LOGO`, `Submission->submitted_by/submitted_at/approved_by/approved_at`, `InterestAnswer->note`.
- Produces:
  - `PdfContext::for(Submission $s): array{phase:int, nocLogo:string, gameLogo:?string, assocLogo:?string, preparedBy:?string, approvedBy:?string, deadline:?string}`
  - `PdfContext::bocLogo(): string`
  - Entry by Sport rows gain `value` (`yes|no|maybe|null`) and `note`.
  - Entry by Event rows gain `max`.

**Listed test changes.** Spec 17 replaces the summary sentence with the canvas summary box.
- In `tests/Feature/LongListPdfTest.php` line 86, replace `->toContain('Athletes: 1 (1 male, 0 female)')->toContain('Team officials: 1');` with `->toContain('Athletes Male <strong>1</strong>')->toContain('Athletes Female <strong>0</strong>')->toContain('Team officials <strong>1</strong>');`.
- In `tests/Feature/ShortListPdfTest.php` line 81, replace `->toContain('Athletes: 5 (3 male, 2 female)')` with `->toContain('Athletes Male <strong>3</strong>')->toContain('Athletes Female <strong>2</strong>')->toContain('Athletes Total <strong>5</strong>')`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ui/PdfDesignTest.php`:

```php
<?php

use App\Enums\PhaseStatus;
use App\Models\Association;
use App\Models\Game;
use App\Models\Submission;
use App\Models\User;
use App\Support\PdfContext;

it('prints X marks, notes, the prepared and approved block and the page counter on Entry by Sport', function () {
    $html = view('pdf.entry-by-sport', [
        'game' => Game::factory()->make(['name' => 'Asian Indoor Games']),
        'association' => Association::factory()->make(['name' => 'Bahrain Weightlifting Federation', 'code' => 'BWF']),
        'rows' => [
            ['sport' => 'Weightlifting', 'answer' => 'Yes', 'value' => 'yes', 'note' => 'Male and female'],
            ['sport' => 'Judo', 'answer' => 'No response', 'value' => null, 'note' => null],
        ],
        'referenceNo' => 'BWF-G001-P1-R1', 'declaration' => 'I confirm.', 'nocLogo' => PdfContext::bocLogo(), 'gameLogo' => null, 'assocLogo' => null,
        'phase' => 1, 'preparedBy' => 'Mariam Khalil, Association Editor · 2 Sep 2026', 'approvedBy' => 'Ahmed Saleh, Association Admin · 3 Sep 2026', 'deadline' => '5 Sep 2026',
    ])->render();

    expect($html)->toContain('chip-1')->toContain('Entry by Sport')->toContain('>X<')->toContain('Male and female')->toContain('No response')
        ->toContain('2 sports invited')->toContain('Prepared by')->toContain('Mariam Khalil, Association Editor · 2 Sep 2026')
        ->toContain('Phase 1 deadline')->toContain('counter(pages)')->toContain('Bahrain Olympic Committee')->toContain('data:image/png;base64,');
});

it('builds the PDF context from the submission', function () {
    $assoc = Association::factory()->create(['code' => 'BWF']);
    $game = Game::factory()->create();
    $game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => '2026-09-05 18:00']);
    $editor = User::factory()->assocEditor($assoc)->create(['name' => 'Mariam Khalil']);
    $admin = User::factory()->assocAdmin($assoc)->create(['name' => 'Ahmed Saleh']);
    $s = Submission::for($game, $assoc, 1);
    $s->update(['submitted_by' => $editor->id, 'submitted_at' => '2026-09-02 10:00', 'approved_by' => $admin->id, 'approved_at' => '2026-09-03 09:00']);

    $ctx = PdfContext::for($s->fresh());

    expect($ctx['phase'])->toBe(1)
        ->and($ctx['preparedBy'])->toBe('Mariam Khalil, Association Editor · 2 Sep 2026')
        ->and($ctx['approvedBy'])->toBe('Ahmed Saleh, Association Admin · 3 Sep 2026')
        ->and($ctx['deadline'])->toBe('5 Sep 2026')
        ->and($ctx['nocLogo'])->toStartWith('data:image/png;base64,');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run `php vendor/bin/pest tests/Feature/Ui/PdfDesignTest.php`.

Expected: FAIL with `Class "App\Support\PdfContext" not found`.

- [ ] **Step 3: Make a PNG logo for dompdf and create `PdfContext`**

Run:

```bash
php -r '$i = imagecreatefromwebp("public/images/boc-logo.webp"); imagesavealpha($i, true); imagepng($i, "public/images/boc-logo.png");'
```

Expected: `public/images/boc-logo.png` exists (96×179).

Create `app/Support/PdfContext.php`:

```php
<?php

namespace App\Support;

use App\Jobs\GenerateEntryByEventPdfs;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Carbon\CarbonInterface;

/** Shared data for the four phase PDFs (spec 17): logos, who prepared and approved it, and the phase deadline. */
final class PdfContext
{
    /** @return array{phase: int, nocLogo: string, gameLogo: ?string, assocLogo: ?string, preparedBy: ?string, approvedBy: ?string, deadline: ?string} */
    public static function for(Submission $s): array
    {
        $s->loadMissing(['game', 'association']);
        $who = function (?int $id, ?CarbonInterface $at): ?string {
            $u = $id ? User::find($id) : null;

            return $u ? $u->name.', '.$u->role->label().($at ? ' · '.$at->format('j M Y') : '') : null;
        };
        $deadline = $s->gamePhase()->effectiveDeadline($s);

        return [
            'phase' => (int) $s->phase,
            'nocLogo' => GenerateEntryByEventPdfs::dataUri(Setting::get(Setting::NOC_LOGO)) ?? self::bocLogo(),
            'gameLogo' => GenerateEntryByEventPdfs::dataUri($s->game->logo_path),
            'assocLogo' => GenerateEntryByEventPdfs::dataUri($s->association->logo_path),
            'preparedBy' => $who($s->submitted_by, $s->submitted_at),
            'approvedBy' => $who($s->approved_by, $s->approved_at),
            'deadline' => $deadline?->format('j M Y'),
        ];
    }

    /** The BOC logo bundled with the app, used when Settings has no NOC logo (dompdf reads PNG, not WebP). */
    public static function bocLogo(): string
    {
        return 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/boc-logo.png')));
    }
}
```

- [ ] **Step 4: Create the PDF partials**

Create `resources/views/pdf/partials/styles.blade.php`:

```blade
<style>
    @page { margin: 36px 40px 56px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #0F172A; }
    table { width: 100%; border-collapse: collapse; }
    .grid td, .grid th { border: 1px solid #94A3B8; padding: 5px 8px; vertical-align: middle; }
    .grid th { background: #F1F5F9; text-align: left; }
    .c { text-align: center; }
    .muted { color: #475569; }
    .rule { border-bottom: 2px solid #0F2440; }
    .rule td { padding-bottom: 8px; vertical-align: middle; }
    .chip { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 9px; font-weight: bold; }
    .chip-1 { background: #E0F2FE; color: #075985; } .chip-2 { background: #CCFBF1; color: #115E59; }
    .chip-3 { background: #FEF3C7; color: #92400E; } .chip-4 { background: #EDE9FE; color: #5B21B6; }
    h1 { font-size: 20px; margin: 4px 0 0; }
    .x { font-weight: bold; font-size: 13px; }
    .total td, .total th { background: #F8FAFC; font-weight: bold; }
    .shade { background: #E2E8F0; }
    .signoff td { border: 1px solid #475569; height: 46px; vertical-align: top; color: #475569; font-size: 9px; padding: 4px 6px; }
    .signoff td.stamp { border-style: dashed; }
    .footer { position: fixed; bottom: -40px; left: 0; right: 0; border-top: 1px solid #CBD5E1; padding-top: 4px; font-size: 9px; color: #475569; }
    .pagenum:after { content: "Page " counter(page) " of " counter(pages); }
    .box { border: 1px solid #94A3B8; padding: 6px 8px; }
</style>
```

Create `resources/views/pdf/partials/footer.blade.php`. This must be the first element in `<body>`, so that dompdf repeats it on every page.

```blade
<div class="footer">
    <table><tr>
        <td>Reference {{ $referenceNo }} · generated {{ now()->timezone('Asia/Bahrain')->format('j M Y H:i') }}</td>
        <td style="text-align: right"><span class="pagenum"></span></td>
    </tr></table>
</div>
```

Create `resources/views/pdf/partials/header.blade.php`. It needs `$game`, `$association`, `$phase`, `$title` and the logos.

```blade
<table class="rule">
    <tr>
        <td style="width: 46px">@if(! empty($nocLogo))<img src="{{ $nocLogo }}" style="max-width:40px;max-height:60px">@endif</td>
        <td><strong style="font-size: 12px">Bahrain Olympic Committee</strong><br><span class="muted">National Olympic Committee of Bahrain</span></td>
        <td style="text-align: right">
            <strong style="font-size: 12px">{{ $game->name }}</strong><br>
            <span class="muted">@if($game->start_date){{ $game->start_date->format('j M') }} – {{ $game->end_date?->format('j M Y') }}@endif</span>
        </td>
        <td style="width: 64px; text-align: right">@if(! empty($gameLogo))<img src="{{ $gameLogo }}" style="max-width:100%;max-height:60px">@endif</td>
    </tr>
</table>
<table style="margin-top: 12px">
    <tr>
        <td style="vertical-align: bottom">
            <span class="chip chip-{{ $phase }}">Phase {{ $phase }}</span>
            <h1>{{ $title }}</h1>
        </td>
        <td style="vertical-align: bottom; text-align: right">
            @if(! empty($assocLogo))<img src="{{ $assocLogo }}" style="max-width:64px;max-height:40px;vertical-align:middle"> @endif
            <span class="muted">Association</span><br>
            <strong style="font-size: 12px">{{ $association->name }} ({{ $association->code }})</strong>
        </td>
    </tr>
</table>
```

Create `resources/views/pdf/partials/signoff.blade.php`:

```blade
<table style="margin-top: 12px; width: auto">
    <tr><td class="muted" style="width: 130px; padding: 2px 0">Prepared by</td><td>{{ $preparedBy ?? '–' }}</td></tr>
    <tr><td class="muted" style="padding: 2px 0">Approved by</td><td>{{ $approvedBy ?? '–' }}</td></tr>
    <tr><td class="muted" style="padding: 2px 0">Phase {{ $phase }} deadline</td><td>{{ $deadline ?? '–' }}</td></tr>
</table>
<p style="margin-top: 12px; line-height: 1.5"><strong>Declaration.</strong> {{ $declaration }}</p>
<table class="signoff" style="margin-top: 8px">
    <tr><td style="width: 42%">Name</td><td style="width: 42%">Position</td><td rowspan="2" class="stamp" style="width: 16%">Stamp</td></tr>
    <tr><td>Signature</td><td>Date</td></tr>
</table>
```

- [ ] **Step 5: Rewrite the three PDF views**

Replace `resources/views/pdf/entry-by-sport.blade.php` with:

```blade
<!DOCTYPE html>
<html>
<head><meta charset="utf-8">@include('pdf.partials.styles')</head>
<body>
@include('pdf.partials.footer')
@include('pdf.partials.header', ['phase' => 1, 'title' => 'Entry by Sport'])
<p style="margin: 10px 0; line-height: 1.5; color: #334155">For each sport you were invited to, tell STA whether you plan to take part. This is not yet an entry: numbers per event are given in Phase 2 (Entry by Event).</p>
<table class="grid">
    <tr><th>Sport</th><th class="c" style="width: 54px">Yes</th><th class="c" style="width: 54px">No</th><th class="c" style="width: 54px">Maybe</th><th>Note</th></tr>
    @foreach($rows as $r)
    <tr>
        <td><strong>{{ $r['sport'] }}</strong></td>
        @foreach(['yes', 'no', 'maybe'] as $v)<td class="c x">{{ ($r['value'] ?? null) === $v ? 'X' : '' }}</td>@endforeach
        <td>{{ $r['note'] ?? (($r['value'] ?? null) === null ? 'No response' : '') }}</td>
    </tr>
    @endforeach
    @php $tally = collect($rows)->countBy(fn ($r) => $r['value'] ?? 'none'); @endphp
    <tr class="total">
        <td>Total</td><td class="c">{{ $tally['yes'] ?? 0 }}</td><td class="c">{{ $tally['no'] ?? 0 }}</td><td class="c">{{ $tally['maybe'] ?? 0 }}</td>
        <td>{{ count($rows) }} sports invited</td>
    </tr>
</table>
@include('pdf.partials.signoff', ['phase' => 1])
</body>
</html>
```

Replace `resources/views/pdf/entry-by-event.blade.php` with the code below. It must not contain the words "Men" or "Women", and the literal `max-height:60px` stays in the header logo style.

```blade
<!DOCTYPE html>
<html>
<head><meta charset="utf-8">@include('pdf.partials.styles')</head>
<body>
@include('pdf.partials.footer')
@include('pdf.partials.header', ['phase' => 2, 'title' => 'Entry by Event — '.$sportName])
<table class="grid" style="margin-top: 10px">
    <tr><th>Event</th><th style="width: 18%">Allowed</th><th class="c" style="width: 15%">Male</th><th class="c" style="width: 15%">Female</th></tr>
    @foreach($rows as $r)
    <tr>
        <td>{{ $r['event'] }}</td>
        <td>{{ ($r['max'] ?? null) !== null ? 'Up to '.$r['max'] : '–' }}</td>
        @if($r['gender'] === 'mixed')
            <td class="c" colspan="2">{{ $r['count'] }} (mixed)</td>
        @else
            <td class="c {{ $r['gender'] === 'men' ? '' : 'shade' }}">{{ $r['gender'] === 'men' ? $r['count'] : '' }}</td>
            <td class="c {{ $r['gender'] === 'women' ? '' : 'shade' }}">{{ $r['gender'] === 'women' ? $r['count'] : '' }}</td>
        @endif
    </tr>
    @endforeach
    <tr class="total"><td colspan="2">Event entries</td><td class="c">{{ $totals['men'] }}</td><td class="c">{{ $totals['women'] }}</td></tr>
    <tr class="total"><td colspan="2">All events (incl. mixed)</td><td class="c" colspan="2">{{ $totals['total'] }} entries</td></tr>
</table>
<p class="muted" style="font-size: 9px">Grey cells: event not open to that gender.</p>
<table style="margin-top: 8px"><tr>
    <td style="width: 50%; padding-right: 6px; vertical-align: top">
        <table class="grid">
            <tr><th colspan="3">Total number of participants</th></tr>
            <tr><th class="c">Male</th><th class="c">Female</th><th class="c">Total</th></tr>
            <tr>
                <td class="c">{{ $participants['male'] ?? '–' }}</td><td class="c">{{ $participants['female'] ?? '–' }}</td>
                <td class="c">{{ ($participants['male'] ?? null) === null && ($participants['female'] ?? null) === null ? '–' : (int) ($participants['male'] ?? 0) + (int) ($participants['female'] ?? 0) }}</td>
            </tr>
        </table>
    </td>
    <td style="width: 50%; padding-left: 6px; vertical-align: top">
        <table class="grid">
            <tr><th colspan="3">Expected medals</th></tr>
            <tr><th class="c">Gold</th><th class="c">Silver</th><th class="c">Bronze</th></tr>
            <tr><td class="c">{{ $medals['gold'] ?? '–' }}</td><td class="c">{{ $medals['silver'] ?? '–' }}</td><td class="c">{{ $medals['bronze'] ?? '–' }}</td></tr>
        </table>
    </td>
</tr></table>
<p class="muted" style="font-size: 9px">Participants are different athletes (one athlete can enter several events); the sport limits apply to these totals. Medals are not linked to an athlete or event.</p>
<table style="margin-top: 8px"><tr>
    <td style="width: 65%; padding-right: 6px; vertical-align: top"><div class="box"><strong>Entry policy</strong><br>{{ $gameSport->entry_policy ?: 'Each event allows the number of athletes shown above.' }}</div></td>
    <td style="width: 35%; padding-left: 6px; vertical-align: top"><div class="box"><strong>Age eligibility</strong><br>Born from: {{ $gameSport->dob_from?->format('j M Y') ?? 'no lower limit' }}<br>Born to: {{ $gameSport->dob_to?->format('j M Y') ?? 'no upper limit' }}</div></td>
</tr></table>
@include('pdf.partials.signoff', ['phase' => 2])
</body>
</html>
```

Replace `resources/views/pdf/long-list.blade.php` with the code below. It is used for both the Long List and the Short List (`$title`), and its data keys are unchanged.

```blade
<!DOCTYPE html>
<html>
<head><meta charset="utf-8">@include('pdf.partials.styles')<style>body { font-size: 9px; }</style></head>
<body>
@include('pdf.partials.footer')
@include('pdf.partials.header', ['phase' => $phase ?? (($title ?? 'Long List') === 'Short List' ? 4 : 3), 'title' => $title ?? 'Long List'])
<table class="grid" style="margin-top: 8px; width: auto">
    <tr>
        <td><strong>Summary</strong></td>
        <td>Athletes Male <strong>{{ $summary['male'] }}</strong></td>
        <td>Athletes Female <strong>{{ $summary['female'] }}</strong></td>
        <td>Athletes Total <strong>{{ $summary['male'] + $summary['female'] }}</strong></td>
        <td>Team officials <strong>{{ $summary['officials'] }}</strong></td>
    </tr>
</table>
@if(! empty($preparedBy))<p class="muted">Submitted by {{ $preparedBy }}</p>@endif

<p style="font-weight: bold; font-size: 11px; margin: 10px 0 4px">Athletes</p>
<table class="grid">
    <tr>
        <th class="c" style="width: 30px">S.No</th><th style="width: 46px">Photo</th><th>Name</th><th>Sport</th><th>Event</th><th>Date of birth</th>
        @foreach($athleteItems as $item)<th class="c">{{ $item }}</th>@endforeach
        <th class="c">Passport scan</th>
    </tr>
    @forelse($athletes as $r)
    <tr>
        <td class="c">{{ $loop->iteration }}</td>
        <td class="c">@if($r['photo'])<img src="{{ $r['photo'] }}" style="width:36px;height:46px">@endif</td>
        <td>{{ $r['name'] }}</td>
        <td>{{ $r['sport'] }}</td>
        <td>{{ $r['events'] }}</td>
        <td>{{ $r['dob'] }}</td>
        @foreach($r['sizes'] as $size)<td class="c">{{ $size }}</td>@endforeach
        <td class="c">{{ $r['passport'] ? 'Yes' : 'No' }}</td>
    </tr>
    @empty
    <tr><td colspan="{{ 7 + count($athleteItems) }}">No athletes.</td></tr>
    @endforelse
</table>

<p style="font-weight: bold; font-size: 11px; margin: 10px 0 4px">Team officials</p>
<table class="grid">
    <tr>
        <th class="c" style="width: 30px">S.No</th><th style="width: 46px">Photo</th><th>Name</th><th>Function</th><th>Date of birth</th><th>Phone</th><th>Email</th><th>Sizes</th><th class="c">Passport scan</th>
    </tr>
    @forelse($officials as $r)
    <tr>
        <td class="c">{{ $loop->iteration }}</td>
        <td class="c">@if($r['photo'])<img src="{{ $r['photo'] }}" style="width:36px;height:46px">@endif</td>
        <td>{{ $r['name'] }}</td>
        <td>{{ $r['function'] }}</td>
        <td>{{ $r['dob'] }}</td>
        <td>{{ $r['phone'] }}</td>
        <td>{{ $r['email'] }}</td>
        <td>{{ $r['sizes'] }}</td>
        <td class="c">{{ $r['passport'] ? 'Yes' : 'No' }}</td>
    </tr>
    @empty
    <tr><td colspan="9">No team officials.</td></tr>
    @endforelse
</table>

@if(! empty($justification))
<div style="border: 1px solid #6D28D9; padding: 8px 12px; margin-top: 10px">
    <strong>Justification (over limit)</strong>
    <p style="white-space: pre-line; margin: 4px 0 0">{{ $justification }}</p>
</div>
@endif
@include('pdf.partials.signoff', ['phase' => $phase ?? (($title ?? 'Long List') === 'Short List' ? 4 : 3)])
</body>
</html>
```

- [ ] **Step 6: Pass the context from the jobs**

In each of the three jobs, add `use App\Support\PdfContext;`.

`GenerateEntryBySportPdf::handle()`: replace the `$answers`, `$rows` and `view(...)` lines with:

```php
        $answers = $s->answers()->get()->keyBy('game_sport_id');
        $rows = $s->game->sportsFor($s->association)->map(fn ($gs) => [
            'sport' => $gs->sport->name,
            'answer' => $answers->get($gs->id)?->answer->label() ?? 'No response',
            'value' => $answers->get($gs->id)?->answer->value,
            'note' => $answers->get($gs->id)?->note,
        ])->all();
        $html = view('pdf.entry-by-sport', [
            'game' => $s->game, 'association' => $s->association, 'rows' => $rows, 'referenceNo' => $s->reference_no,
            'declaration' => Setting::get(Setting::DECLARATION, Setting::DEFAULT_DECLARATION),
            ...PdfContext::for($s),
        ])->render();
```

`GenerateEntryByEventPdfs::handle()`: change the row map to `fn ($ge) => ['event' => $ge->event->name, 'gender' => $ge->event->gender->value, 'count' => (int) ($counts[$ge->id] ?? 0), 'max' => $ge->p2_max_athletes]`. In the `view(...)` array, replace `'nocLogo' => ..., 'gameLogo' => ...,` with `...PdfContext::for($s),`.

`GenerateLongListPdf::handle()`: in the `view('pdf.long-list', [...])` array, replace the `'nocLogo' => ..., 'gameLogo' => ...,` line with `...PdfContext::for($s),`.

- [ ] **Step 7: Update the two listed test lines**

Apply the two replacements listed under "Listed test changes" above.

- [ ] **Step 8: Run the tests and look at one real PDF**

Run `php vendor/bin/pest tests/Feature/Ui/PdfDesignTest.php tests/Feature/EntryBySportPdfTest.php tests/Feature/EntryByEventPdfTest.php tests/Feature/LongListPdfTest.php tests/Feature/ShortListPdfTest.php tests/Feature/GenderLabelsTest.php tests/Feature/StaSubmissionEditTest.php`. Expected: all pass.

Next, use the Task 14 demo data to generate one PDF per phase. The `--class` seeding is local only.

```bash
php artisan tinker --execute='$s = App\Models\Submission::withoutGlobalScopes()->where("phase", 3)->where("status", "submitted")->first(); (new App\Jobs\GenerateLongListPdf($s))->handle(); echo $s->fresh()->pdf_paths["long_list"];'
```

Open the file from the private disk and compare it with `PdfPhase3.dc.html`. If "Page N of M" prints an empty `M`, the installed dompdf does not support `counter(pages)`. In that case, replace `.pagenum:after` with a `<script type="text/php">` `page_text` call, and set `->setOption('isPhpEnabled', true)` on the four `Pdf::loadHTML` calls. Note this in the commit message.

- [ ] **Step 9: Commit**

```bash
git add app/Support/PdfContext.php public/images/boc-logo.png resources/views/pdf app/Jobs tests/Feature/Ui/PdfDesignTest.php tests/Feature/LongListPdfTest.php tests/Feature/ShortListPdfTest.php
git commit -m "feat: phase PDFs match the samples: BOC header, phase chip, association logo, X marks and notes, prepared/approved block, page N of M (M5)"
```

---

### Task 14: Demo data for visual QA and the side-by-side checklist

**Files:**
- Create:
  - `database/seeders/DemoSeeder.php`
  - `tests/Feature/Ui/DemoSeederTest.php`

**Interfaces:**
- Consumes: the factories, `Submission::for`, `Person::syncSizes(array<int, string>)` and every screen from Tasks 1–13.
- Produces:
  - `Database\Seeders\DemoSeeder` (local only)
  - `DemoSeeder::PASSWORD` (a test value, recorded in the class)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Ui/DemoSeederTest.php`:

```php
<?php

use App\Models\Association;
use App\Models\Game;
use App\Models\Person;
use App\Models\Submission;
use Database\Seeders\DemoSeeder;

it('builds the canvas sample world', function () {
    $this->seed(DemoSeeder::class);

    expect(Game::count())->toBe(2)
        ->and(Association::count())->toBe(10)
        ->and(Person::withoutGlobalScopes()->where('last_name', 'Buali')->exists())->toBeTrue()
        ->and(Submission::withoutGlobalScopes()->where('phase', 3)->count())->toBe(8)
        ->and(Submission::withoutGlobalScopes()->where('phase', 2)->where('status', 'submitted')->count())->toBe(11);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => $this->seed(DemoSeeder::class))->toThrow(RuntimeException::class);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run `php vendor/bin/pest tests/Feature/Ui/DemoSeederTest.php`.

Expected: FAIL with `Target class [Database\Seeders\DemoSeeder] does not exist`.

- [ ] **Step 3: Write the seeder**

Create `database/seeders/DemoSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Enums\AppliesTo;
use App\Enums\PersonGender;
use App\Enums\PersonType;
use App\Enums\PhaseStatus;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Association;
use App\Models\ClothingItem;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSport;
use App\Models\Invitation;
use App\Models\NumberEntry;
use App\Models\Person;
use App\Models\Phase2SportTotal;
use App\Models\Sport;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Local visual QA only (M5): the canvas sample world (6th Asian Indoor & Martial Arts Games, BWF, BJF, …) so each page can be
 * compared with docs/design/canvas/project/*.dc.html. Never called from DatabaseSeeder; refuses to run in production.
 * Logins: ali.haroona@sta.example (Super Admin), ahmed.saleh@bwf.example (association admin), m.khalil@bwf.example (editor); password DemoSeeder::PASSWORD.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'demo-password-2026';

    /** code => [name, admin name or null, [sport => [[event, gender, max], …]]] */
    private const ASSOCIATIONS = [
        'BWF' => ['Bahrain Weightlifting Federation', 'Ahmed Saleh', ['Weightlifting' => [['Male 60kg', 'men', 1], ['Male 75kg', 'men', 1], ['Male 95kg', 'men', 1], ['Male 110kg', 'men', 1],
            ['Female 49kg', 'women', 1], ['Female 57kg', 'women', 1], ['Female 69kg', 'women', 1], ['Female 86kg', 'women', 1]]]],
        'BJF' => ['Bahrain Judo Federation', 'Yusuf Kamal', ['Judo' => [['-66kg', 'men', 2], ['-52kg', 'women', 2], ['Mixed Team', 'mixed', 1]]]],
        'BSF' => ['Bahrain Squash & Badminton Federation', 'Huda Nasser', ['Squash' => [['Male Singles', 'men', 2], ['Female Singles', 'women', 2]],
            'Badminton' => [['Male Singles', 'men', 2], ['Mixed Doubles', 'mixed', 1]]]],
        'BKF' => ['Bahrain Karate Federation', null, ['Karate' => [['Male Kata', 'men', 2], ['Female Kata', 'women', 2]]]],
        'BCF' => ['Bahrain Chess Federation', 'Nawaf Isa', ['Chess' => [['Rapid', 'mixed', 2], ['Blitz', 'mixed', 2]]]],
        'BTF' => ['Bahrain Taekwondo Federation', 'Hussain Ebrahim', ['Taekwondo' => [['Male -58kg', 'men', 2], ['Female -49kg', 'women', 2]]]],
        'BEF' => ['Bahrain eSports Federation', null, ['eSports' => [['Open', 'mixed', 2]]]],
        'BBF' => ['Bahrain Billiards Federation', 'Majed Saeed', ['Billiards & Snooker' => [['Snooker', 'men', 2]]]],
        'BAA' => ['Bahrain Athletics Association', 'Salman Jaber', ['Athletics' => [['Male 100m', 'men', 2], ['Female 100m', 'women', 2]]]],
        'BSW' => ['Bahrain Swimming Association', 'Fatima Radhi', ['Swimming' => [['Male 50m Freestyle', 'men', 2], ['Female 50m Freestyle', 'women', 2]]]],
    ];

    /** Phase 3 state per association, as on the canvas game dashboard. */
    private const LONG_LIST = ['BWF' => 'draft', 'BJF' => 'awaiting', 'BSF' => 'draft', 'BKF' => null, 'BCF' => 'awaiting',
        'BTF' => 'draft', 'BEF' => null, 'BBF' => 'draft', 'BAA' => 'submitted', 'BSW' => 'submitted'];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('DemoSeeder is for local visual QA only.');
        }
        $this->call([PermissionSeeder::class]);

        $items = collect(['T-Shirt' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'], 'Tracksuit' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'], 'Suit' => ['44', '46', '48', '50', '52', '54', '56']])
            ->map(fn ($sizes, $name) => ClothingItem::updateOrCreate(['name' => $name], ['sizes' => $sizes, 'applies_to' => AppliesTo::Both, 'is_active' => true]));
        $sta = User::updateOrCreate(['email' => 'ali.haroona@sta.example'],
            ['name' => 'Ali Haroona', 'password' => self::PASSWORD, 'role' => Role::SuperAdmin, 'is_active' => true]);

        $game = Game::updateOrCreate(['name' => '6th Asian Indoor & Martial Arts Games 2026'],
            ['start_date' => now()->addDays(40)->toDateString(), 'end_date' => now()->addDays(50)->toDateString(), 'reminder_days' => [7, 3, 0], 'published_at' => now()]);
        $game->clothingItems()->sync($items->pluck('id'));
        $game->phase(1)->update(['enabled' => false]);
        $game->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Closed, 'opens_at' => now()->subDays(30), 'deadline' => now()->subDays(12)]);
        $game->phase(3)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'opens_at' => now()->subDays(8), 'deadline' => now()->addDays(6)]);
        $game->phase(4)->update(['enabled' => true, 'status' => PhaseStatus::Draft, 'opens_at' => now()->addDays(10), 'deadline' => now()->addDays(20)]);

        $gcc = Game::updateOrCreate(['name' => 'GCC Youth Games 2027'],
            ['start_date' => now()->addDays(90)->toDateString(), 'end_date' => now()->addDays(99)->toDateString(), 'reminder_days' => [7, 3, 0], 'published_at' => now()]);
        $gcc->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Closed, 'deadline' => now()->subDays(14)]);
        $gcc->phase(2)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addDays(5)]);

        $i = 0;
        foreach (self::ASSOCIATIONS as $code => [$name, $adminName, $sports]) {
            $i++;
            $a = Association::updateOrCreate(['code' => $code], ['name' => $name, 'email' => 'info@'.strtolower($code).'.example',
                'phone' => '+973 1700 '.str_pad((string) ($i * 1001 % 10000), 4, '0', STR_PAD_LEFT), 'is_active' => $code !== 'BEF']);
            $game->associations()->syncWithoutDetaching([$a->id]);
            if ($i <= 4) {
                $gcc->associations()->syncWithoutDetaching([$a->id]);
            }
            $admin = $adminName ? User::updateOrCreate(['email' => Str::slug($adminName, '.').'@'.strtolower($code).'.example'],
                ['name' => $adminName, 'password' => self::PASSWORD, 'role' => Role::AssocAdmin, 'association_id' => $a->id, 'is_active' => true]) : null;
            if ($code === 'BKF') {
                Invitation::updateOrCreate(['association_id' => $a->id, 'email' => 'info@bkf.example'],
                    ['role' => Role::AssocAdmin, 'token_hash' => hash('sha256', Str::random(40)), 'invited_by' => $sta->id, 'expires_at' => now()->addDays(7)]);
            }

            $p2 = Submission::for($game, $a, 2);
            foreach ($sports as $sportName => $events) {
                $sport = Sport::updateOrCreate(['name' => $sportName], ['association_id' => $a->id, 'is_active' => true]);
                foreach ([$game, $gcc] as $g) {
                    if (! $g->associations()->whereKey($a->id)->exists()) {
                        continue;
                    }
                    $gs = GameSport::updateOrCreate(['game_id' => $g->id, 'sport_id' => $sport->id], ['p2_max_men' => 4, 'p2_max_women' => 4, 'p2_max_total' => 8,
                        'p4_max_men' => 3, 'p4_max_women' => 3, 'p4_max_officials' => 2, 'dob_to' => '2011-12-31',
                        'entry_policy' => 'Each NOC may enter a maximum of eight (8) athletes, including four (4) male and four (4) female athletes.']);
                    foreach ($events as [$evName, $gender, $max]) {
                        $event = Event::updateOrCreate(['sport_id' => $sport->id, 'name' => $evName, 'gender' => $gender], ['is_active' => true]);
                        $ge = GameEvent::updateOrCreate(['game_id' => $g->id, 'game_sport_id' => $gs->id, 'event_id' => $event->id], ['p2_max_athletes' => $max]);
                        if ($g->is($game)) {
                            NumberEntry::updateOrCreate(['submission_id' => $p2->id, 'game_event_id' => $ge->id], ['count' => 1]);
                        }
                    }
                    if ($g->is($game)) {
                        Phase2SportTotal::updateOrCreate(['submission_id' => $p2->id, 'game_sport_id' => $gs->id], ['male' => 3, 'female' => 1, 'gold' => 1, 'silver' => 1, 'bronze' => 2]);
                    }
                }
            }
            $this->finalise($p2, $code, $game, $admin);

            if ($state = self::LONG_LIST[$code]) {
                $p3 = Submission::for($game, $a, 3);
                $this->longList($p3, $a, $items);
                match ($state) {
                    'awaiting' => $p3->update(['status' => SubmissionStatus::AwaitingAssocAdmin, 'submitted_by' => $admin?->id, 'submitted_at' => now()->subDay()]),
                    'submitted' => $this->finalise($p3, $code, $game, $admin),
                    default => null,
                };
            }
        }

        $bwf = Association::where('code', 'BWF')->sole();
        User::updateOrCreate(['email' => 'm.khalil@bwf.example'],
            ['name' => 'Mariam Khalil', 'password' => self::PASSWORD, 'role' => Role::AssocEditor, 'association_id' => $bwf->id, 'is_active' => true]);
        $this->finalise(Submission::for($gcc, Association::where('code', 'BJF')->sole(), 2), 'BJF', $gcc, null);
    }

    private function finalise(Submission $s, string $code, Game $game, ?User $admin): void
    {
        $s->update(['status' => SubmissionStatus::Submitted, 'reference_no' => $code.'-G'.str_pad((string) $game->id, 3, '0', STR_PAD_LEFT).'-P'.$s->phase.'-R1',
            'submitted_by' => $admin?->id, 'submitted_at' => now()->subDays(2), 'approved_by' => $admin?->id, 'approved_at' => now()->subDays(2)]);
    }

    /** BWF gets the canvas people; the others get two athletes and an official per sport so lists and flags have content. */
    private function longList(Submission $s, Association $a, $items): void
    {
        $people = $a->code === 'BWF' ? [
            ['Yousif Abdulla', 'Al Mannai', PersonGender::Male, '2003-03-14', 'Male 60kg', ['M', 'M', '48']],
            ['Hamad Jassim', 'Buali', PersonGender::Male, '2005-07-02', 'Male 60kg', ['S', 'M', null]],
            ['Ali Redha', 'Isa', PersonGender::Male, '2001-01-19', 'Male 95kg', ['XL', 'XL', '54']],
            ['Sayed Mahmood', 'Hashim', PersonGender::Male, '1999-10-08', 'Male 110kg', ['XXL', 'XXL', '56']],
            ['Noor Khalid', 'Al Sayed', PersonGender::Female, '2006-05-27', 'Female 57kg', ['S', 'S', '44']],
        ] : [
            ['Ahmed', 'Isa '.$a->code, PersonGender::Male, '2002-02-04', null, ['M', 'M', '48']],
            ['Sara', 'Jaffar '.$a->code, PersonGender::Female, '2004-06-17', null, ['S', 'S', '44']],
        ];
        $events = $s->game->gameEvents()->whereHas('gameSport.sport', fn ($q) => $q->where('association_id', $a->id))->with(['event', 'gameSport'])->get();
        foreach ($people as [$first, $last, $gender, $dob, $eventName, $sizes]) {
            $p = Person::factory()->complete()->create(['association_id' => $a->id, 'first_name' => $first, 'last_name' => $last, 'full_name' => strtoupper("$first $last"),
                'gender' => $gender, 'dob' => $dob, 'passport_scan_path' => $last === 'Al Sayed' ? null : 'scans/demo.pdf']);
            $p->syncSizes($items->values()->pluck('id')->combine($sizes)->all());
            $ge = $eventName ? $events->first(fn ($e) => $e->event->name === $eventName)
                : $events->first(fn ($e) => $e->event->gender->value === ($gender === PersonGender::Male ? 'men' : 'women'));
            if ($ge) {
                $s->entries()->create(['person_id' => $p->id, 'game_event_id' => $ge->id, 'game_sport_id' => $ge->game_sport_id]);
            }
        }
        foreach ($a->code === 'BWF' ? [['Ebrahim Ahmed', 'Sanad', 'Head Coach'], ['Fatema Hasan', 'Ali', 'Physio'], ['Khalid Yusuf', 'Murad', 'Team Manager']]
            : [['Coach', 'Karim '.$a->code, 'Head Coach']] as [$first, $last, $function]) {
            $o = Person::factory()->official()->complete()->create(['association_id' => $a->id, 'type' => PersonType::Official,
                'first_name' => $first, 'last_name' => $last, 'full_name' => strtoupper("$first $last"), 'function' => $function]);
            $o->syncSizes($items->values()->pluck('id')->combine(['L', 'L', $last === 'Ali' ? null : '52'])->all());
            if ($gs = $events->first()?->gameSport) {
                $s->entries()->create(['person_id' => $o->id, 'game_event_id' => null, 'game_sport_id' => $gs->id]);
            }
        }
    }
}
```

The counts in the test work out as follows:
- **Phase 2 submissions:** there are 11 submitted. One per association for the main game makes 10, and BJF's GCC submission is the eleventh.
- **Phase 3 submissions:** there are 8. Every association has one except BKF and BEF.
- **BWF numbers:** Phase 2 has 1 entry in Male 60kg, and the Long List has 2 there. That gives the canvas flag "1 differs from Phase 2".

- [ ] **Step 4: Run the tests**

Run `php vendor/bin/pest tests/Feature/Ui/DemoSeederTest.php`. Expected: 2 passed.

If `GameEvent::gameSport`, `GameSport::sport` or `Game::gameEvents` have different relation names, check them with `grep -n "function gameSport\|function sport\b\|function gameEvents" app/Models/GameEvent.php app/Models/GameSport.php app/Models/Game.php`. Adjust the `whereHas` path to match; do not add new relations.

- [ ] **Step 5: Run the full suite and the build**

Run `php vendor/bin/pest`. Expected: all green, which means the existing tests plus every `tests/Feature/Ui/*` test.

Run `npm run build`. Expected: success, and the theme CSS contains `.sta-phase-record`, `.sta-report-item` and `.sta-game-card`.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/DemoSeeder.php tests/Feature/Ui/DemoSeederTest.php
git commit -m "chore: local-only DemoSeeder with the canvas sample world for visual QA (M5)"
```

- [ ] **Step 7: Visual QA, side by side (done by the controller in the browser)**

Set up the data:

```bash
php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder && php artisan serve
```

Open each design file next to its page. The design files are `docs/design/canvas/project/<File>.dc.html` and open in a browser. Check each page at 1440 px wide and at 375 px. Record **pass**, or a **fix** with a note, in this table. Fix in the view or theme, re-run the related UI test, and commit `fix: visual QA – <screen>`.

| # | Canvas | URL (login) | Compare |
|---|---|---|---|
| 1 | Main | `/admin/games/{id}/dashboard` (Ali Haroona) | phase cards (Phase 3 active ring), 5 KPI tiles and colours, All / Pending / Submitted switch, BWF row flag "1 differs from Phase 2", Last activity, chip headers |
| 2 | AdminDashboard | `/admin` | `#E9EEF5` page, blue Active games strip, two game cards, amber attention list, teal registry with bars, indigo associations table, slate activity list |
| 3 | Phase2 | `/portal/{BWF}/games/{gcc}/phase-2` (Ahmed Saleh) | teal header band, stepper (current solid teal), participants fieldset tint, greyed cells, Event entries row, medals dots, limits meters, policy card |
| 4 | LongList / LongListGrid | `/portal/{BWF}/games/{id}/phase-3` | amber band, compare chips (Male 60kg orange), List / Grid switch, grid cards with size tags and the warning banner, Add athlete slide-over (theme) |
| 5 | LongListEvents | same page, sport card | event boxes in 2 columns, person chips with ×, "Search athletes…" picker, officials box `#F8FAFC` |
| 6 | ShortList | open Phase 4 for BWF in `/admin` first (Phase 3 BWF must be submitted: approve it as Ahmed), then `/portal/{BWF}/games/{id}/phase-4` | violet band, tick tables with 22px violet boxes, aside meters, violet justification card |
| 7 | Registry | `/portal/{BWF}/people` | subtitle counts, All / Athletes / Officials tabs, Sizes pills, orange expiry dates |
| 8 | PlayerForm / OfficialForm | `/portal/{BWF}/people/{id}/edit` | `#E9EEF5` page, code tag, Incomplete pill and missing text, coloured section bands with icons |
| 9 | SetupInvite / Limits / ShortList / Phases | `/admin/games/{id}/edit` | "Game setup", draft pill, 3 underlined tabs, amber clothing band, sports table with P2 and P4 columns, tinted phase cards with number squares |
| 10 | AdminAssociations | `/admin/associations` | logo or initials, email under name, admin pills (Ahmed Saleh green, BKF "Invite pending", BEF "No admin"), editors "n / 2", sports |
| 11 | AdminSports | `/admin/sports`, `/admin/sports/{id}/edit` | association under the sport, gender badges Male indigo / Female pink / Mixed slate |
| 12 | AdminPeople | `/admin/people` | 4 KPI tiles (two orange), tabs, table |
| 13 | AdminReports | `/admin/reports`, `/admin/games/{id}/reports` | blue and indigo strips, game rows, report list with chips, active row `#E8EEF6`, preview card |
| 14 | AdminReportBuilder | `/admin/report-builder` | settings aside with uppercase labels, results card with group rows and total |
| 15 | PdfPhase1–4 | generate as in Task 13 Step 8 for each phase (enable GCC Phase 1 for a Phase 1 sample) | header rule, chip, association block, X marks, summary box, signoff grid with dashed Stamp, "Page 1 of 1" |
| 16 | (no canvas) | `/admin/login`, `/portal/login`, the Extra fields, Settings, STA users, Audit log, Clothing items, export layouts, and submission view pages, the Travel modal, `/invitations/bad` | same tokens: navy menu, 44px controls, 14px cards, Space Grotesk headings, brand on the login card |

---

## Self-review

**Spec 17 coverage:**

| Spec 17 requirement | Task |
|---|---|
| Theme for both panels | 1 |
| Fonts | 1 |
| Colours, shapes, 44px targets | 1, 2 |
| Navy menu with brand, panel name, association logo and name, user block | 1 |
| Phase colours (chips, cards, column headers) | 2, 3, 8, 10, 12 |
| Status pills | 2 |
| Section colours | 7, 8, 11, 12 |
| Responsive | the theme's media queries, `auto-fit` grids, `sta-scroll`, and QA at 375 px |
| Game dashboard | 10 |
| Phase 2 | 4 |
| Long List list, grid and events | 5 |
| Short List | 6 |
| Registry | 7 |
| Game setup | 8 |
| Admin associations, sports, people | 9, 7 |
| Admin home | 11 |
| Athlete and official forms | 7 |
| Reports and builder | 12 |
| Pages without a canvas (Phase 1, travel modal, submission view, comments, extra fields, export layouts, STA users, audit log, settings, login) | 4, 9 (theme), 14 QA row 16 |
| Additions: dashboard cards, KPIs, last activity, switch | 10, 11 |
| Addition: Phase 1 note | 4, 13 |
| Additions in the PDFs | 13 |
| Behaviour unchanged | every task keeps the `wire:` bindings, action names and permission checks |
| Test changes listed | Task 10 (1 line) and Task 13 (2 lines) |

**Placeholder scan:** every code step has full code. The two conditional fallbacks are explicit commands with exact replacements. One is the dompdf `counter(pages)` fallback; the other is relation names in the seeder, which uses relations that already exist.

**Type consistency:**
- Task 2 defines `Sta::cellPill`, `submissionPill`, `phaseStatus`, `daysLeft` and `heading`, and Task 10 adds `when`. Tasks 3–13 use them with these exact signatures.
- `GameProgress::status/counts/openPhase/lastActivity/flags` are defined in Task 10 and used in Task 11.
- `PdfContext::for` and `bocLogo` are defined and used in Task 13.
- `EditsPhase3::addAthlete/removeAthlete/addOfficial/removeOfficial` and `EditsPhase4::tickedCounts` are used only in their own task's views and tests.
- `Games::forTenant` is used by `Home` in the same task.
