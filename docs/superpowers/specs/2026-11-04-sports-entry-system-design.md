# Sports Entry System — Design

Date: 2026-11-04
Status: Approved design, pending spec review

## 1. Problem

For every multi-sport game (Arab Games, Asian Youth Games, Aichi-Nagoya, …) STA collects
participation intent, entry numbers, and athlete / team official details from ~50 sports
associations by email and Excel, then chases each one manually. This system replaces the
email/Excel loop with a portal where associations enter data per phase, STA tracks status
on one screen, and reminders are automatic.

## 2. Decisions

| Topic | Decision |
|---|---|
| Stack | Latest stable Laravel and Filament (v4+, which provides built-in MFA and multi-tenancy), PHP 8.5, Livewire for custom screens. Pin exact versions at project setup. |
| App shape | One Laravel app, two Filament panels: `/admin` (Super Admin + STA), `/portal` (associations) |
| Hosting | AWS `me-south-1` (Bahrain): EC2 (Nginx + PHP-FPM 8.5), RDS MySQL 8, S3 (presigned URLs), SES email |
| Background | Laravel queue (database driver) under Supervisor; Laravel scheduler via cron |
| PDF | dompdf (`barryvdh/laravel-dompdf`, pure PHP, table layouts; decided 2026-11-09 instead of Browsershot so no Chrome/Node is needed on EC2 or dev machines). Phase 2 "Entry by Event" PDF per sport (layout from the Weightlifting sample), plus Long List and Short List PDFs (layout from the Phase 3 sample). Every PDF has the NOC and game logos, a declaration, signature/stamp boxes and a `reference_no` |
| Excel | `maatwebsite/excel` (or Filament import/export actions) for people import and report export |
| Language | English only |
| Registry | Permanent per association; reused across games |
| Permissions | `spatie/laravel-permission` + `filament-shield`; STA rights are per-module checkboxes |

Out of scope: Arabic/RTL, SMS/WhatsApp, mobile app, end-to-end browser test suite, kit ordering and stock, migration from the earlier "BOC Athlete Registration" design prototype (it has no real data; this is a new build).

Decisions taken on 2026-11-04 (full log in the scope document, section 15):
- Phase 1 stays Yes / No / Maybe per sport.
- Mixed events count only against their own event maximum.
- STA can extend the deadline or re-open the phase for a single association.
- Passport and CPR scans: optional upload, flagged if missing.
- Reminder days are set per game.
- Age is calculated as of the game start date. An athlete outside the sport's age range gets a warning, not a block.
- Clothing items are created by the admin and chosen per game. Sizes are stored on the person and reused.
- Personal data is kept while the association is active and deleted on request.

## 3. Roles

| Role | Scope | Created by |
|---|---|---|
| Super Admin | Everything | Seeded |
| STA user | Modules ticked by Super Admin (e.g. view games, edit submissions, manage associations, export reports) | Super Admin |
| Association Admin | Own association; approves editor submissions; invites editors | Invitation link from admin |
| Association Editor (max 2 per association) | Own association; data entry; submits to Association Admin | Invitation from Association Admin |

Associations cannot create or edit sports, events, or games.

## 4. Data Model

Hierarchy: **Association → Sports → Events**.

### Access
- `associations`: `name`*, `email`*, `logo`, `phone`, `address`, `website` (* required).
- `users`: `name`, `email`, `password`, `role` (`super_admin|sta|assoc_admin|assoc_editor`), `association_id` (null for admin/STA), `is_active`.
- `invitations`: `email`, `association_id`, `role`, `token_hash`, `expires_at` (+7 days), `accepted_at`, `invited_by`. Single use.

### Master data (admin only)
- `sports`: `association_id`, `name`. One association may own several sports (e.g. Badminton and Squash).
- `events`: `sport_id`, `name`, `gender` (`men|women|mixed`).
- `clothing_items`: `name` (T-Shirt, Suit, Tracksuit, Shoes, …), `sizes` (ordered JSON list, e.g. XS–3XL or 36–47), `applies_to` (`athlete|official|both`), `is_active`.
- Settings: NOC logo and the declaration text printed on PDFs.

### Registry (owned by association, permanent)
- `people`: `association_id`, `type` (`athlete|official`), `first_name`, `second_name`, `third_name`, `last_name`, `photo_path`, `passport_no` (encrypted), `passport_expiry`, `passport_scan_path`, `cpr_no` (encrypted), `cpr_expiry`, `cpr_scan_path`, `nationality`, `dob`, `gender`, `height_cm`, `weight_kg`, `function` / `phone` / `email` (officials).
  - Scans are optional.
  - `passport_no` and `cpr_no` are unique.
- `person_sizes`: `person_id`, `clothing_item_id`, `size`. The size must be in the item's list. One size per person per item.
- Age is computed from `dob` as of the game start date; it is not stored.

### Game setup (admin only)
- `games`: `name`, `logo`, `start_date`, `end_date`, `reminder_days` (e.g. `[7,3,0]`).
- `game_associations`: `game_id`, `association_id`. These are the invited associations.
- `game_sports`: `game_id`, `sport_id`, `p2_max_men`, `p2_max_women`, `p2_max_total`, `p4_max_men`, `p4_max_women`, `p4_max_officials`, `dob_from`, `dob_to` (age eligibility, optional), `entry_policy` (text).
- `game_events`: `game_id`, `event_id`, `p2_max_athletes`.
- `game_clothing_items`: `game_id`, `clothing_item_id`. The clothing items collected for this game.
- `game_phases`: `game_id`, `phase` (1–4), `enabled`, `status` (`draft|open|closed`), `opens_at`, `deadline`.

Phase 2 counts athletes only. Team officials are counted only in Phase 4 (`p4_max_officials`, total per sport).

### Workflow
- `submissions`: `game_id`, `association_id`, `phase`, `status` (`draft|awaiting_assoc_admin|submitted`), `reference_no`, `deadline_override` (per-association extension), `submitted_by`, `submitted_at`, `approved_by`, `approved_at`, `pdf_path`, `justification` (Phase 4 only; one text field per submission).
- `interest_answers` (Phase 1): `submission_id`, `game_sport_id`, `answer` (`yes|no|maybe`).
- `number_entries` (Phase 2): `submission_id`, `game_event_id`, `count`.
- `entries` (Phases 3 and 4): `submission_id`, `person_id`, `game_event_id` (athletes) or `game_sport_id` (officials).
- `comments`: `submission_id`, `user_id`, `body`.
- Audit log (`spatie/laravel-activitylog`) on submissions, entries, number_entries, interest_answers, people.

## 5. Phases and Gating

- Admin chooses which phases are enabled per game. Disabled phases are skipped.
- A phase is editable by an association only while its status is `open` **and** before its deadline (or that association's `deadline_override`). Admin opens and closes phases manually. Closed phases are read-only to associations.
- STA can re-open one association's submitted phase. It returns to `draft` and the association is emailed.
- Admin selects participating associations and sports up front. Phase 1 is optional (often skipped). When Phase 1 is used, sports answered `no` are hidden for that association in later phases.

### Phase 1 — Entry by Sport
Per invited sport, the association answers Yes / No / Maybe. Not responding is tracked as "no response".

### Phase 2 — Entry by Number
Grid of sports × events with one number box per event. Hard limits are checked live:
- per event: `count ≤ game_events.p2_max_athletes`
- per sport: sum of men's events ≤ `p2_max_men`, sum of women's events ≤ `p2_max_women`, sum of all ≤ `p2_max_total`
- mixed events: only the event maximum applies
- the sport's `entry_policy` text is shown above the grid
- on submit, one "Entry by Event" PDF is generated per sport. It shows events with Male / Female columns (the other gender greyed out), totals, entry policy, age eligibility, and Name / Position / Date / Stamp boxes.

### Phase 3 — Long List
1. Association maintains its registry (form or Excel import), including clothing sizes for the game's clothing items.
2. Assign athletes to game events. The athlete's gender must match the event's gender (mixed accepts both).
3. Assign team officials to game sports.
4. Each event shows its Phase 2 number. If the Long List exceeds it, the event is **flagged, not blocked**.
5. These are flagged as warnings, never blocked:
   - passport or CPR expires before `games.end_date`
   - passport or CPR scan is missing
   - athlete's date of birth is outside `dob_from`–`dob_to`
   - a required clothing size is missing
6. When the submission reaches `submitted`, a PDF is generated in the background. The association and STA can download it.
   - **Athlete columns:** photo, name, sport, event, date of birth, one column per game clothing item, passport scan (Yes/No).
   - **Official columns:** photo, name, function, date of birth, phone, email, sizes, passport scan (Yes/No).
   - **Footer:** declaration, signature and stamp boxes, `reference_no`.

### Phase 4 — Short List
- Preloaded with a copy of the association's Long List entries. The association unticks people to shorten it.
- Limits per sport: athletes `p4_max_men` / `p4_max_women`; team officials `p4_max_officials`.
- If any limit is exceeded, the single `justification` field becomes required before submit. The submission is still allowed.
- Generates a PDF and appears in the over-limit report.

### Submit flow (all phases)
- Editor submits: status becomes `awaiting_assoc_admin`, and the association admin is emailed.
- Association Admin approves: status becomes `submitted`, and STA is notified (plus a PDF for Phases 3 and 4).
- Association Admin submitting directly: status goes straight to `submitted`.
- STA can edit any submission directly at any status (audited) and add comments. Each comment emails the association's users.

## 6. Screens

### `/admin` (Super Admin + STA)
- Associations CRUD with an **Invite** action (emails the link and offers a copy-link button).
- Sports and Events CRUD.
- Clothing items CRUD (name, size list, applies to athlete / official / both).
- Settings: NOC logo, PDF declaration text.
- STA users CRUD with permission checkboxes.
- Game setup, as tabs:
  1. Details (including reminder days)
  2. Associations
  3. Sports & Events (limits, age range, entry policy)
  4. Clothing items
  5. Phases (enable, deadlines, Open/Close, per-association extension and re-open)
- Game dashboard: matrix of associations × enabled phases, colored by status, with a filter for pending and a **Remind pending** button.
- Submission view: read and edit, comments, audit trail, PDF download.
- People registry: search across all associations.
- Reports and Excel exports: Phase 1 answers, Phase 2 totals, Long List, Short List, over-limit (with justifications), expiring documents, clothing sizes (counts per item and size, by association and overall, plus a per-person list).

### `/portal` (associations, Filament tenancy = association)
- Association profile (logo and details, optional).
- Users: the Association Admin invites up to 2 editors.
- People registry: CRUD plus Excel import (downloadable template, row-level validation errors shown before commit).
- Games: one card per invited game with the current open phase and its deadline; leads into the phase screen.
- Approve / return-to-draft for Association Admin on pending submissions.

## 7. Notifications

Sent by email (SES) through queued Laravel notifications:
- Invitation.
- Phase opened (all invited associations).
- Daily scheduler, while a phase is open and the association has not submitted: on each day in `games.reminder_days` before its (possibly extended) deadline, and daily while overdue.
- Deadline extended or phase re-opened for one association.
- **Remind pending** button (admin, manual).
- Submission awaiting association admin approval; submission received (STA); new comment.

## 8. Security

- Tenant isolation: a global scope on every association-owned model, plus Filament tenancy on `/portal`. Covered by a test proving that association A cannot read or write association B's data.
- `passport_no` and `cpr_no` use Laravel's `encrypted` cast.
- Photos, logos and PDFs live in a private S3 bucket and are served only via `temporaryUrl()` presigned URLs (5 minutes).
- HTTPS only; session idle timeout of 30 minutes; login rate limiting.
- Invitation tokens are hashed, single-use and expire after 7 days.
- Optional app-based MFA for STA users (Filament built-in).

## 9. Validation and Errors

| Type | Cases | Behavior |
|---|---|---|
| Hard error | Missing required field; Phase 2 count over event, gender or total limit; gender mismatch; editing a closed or past-deadline phase; more than 2 editors; size not in the item's list; duplicate passport/CPR | Blocks save |
| Warning | Long List differs from Phase 2 numbers; document expires before the game ends; missing scan; outside age range; missing clothing size; Short List over limit (athletes or officials) | Saves; flagged in UI and reports (over-limit requires justification) |

Concurrency: optimistic locking on submissions via an `updated_at` check. A stale save shows "changed by X, reload" instead of overwriting.

## 10. Operations

- EC2 (Amazon Linux 2023, Nginx, PHP-FPM 8.5), RDS MySQL 8, S3, SES, all in `me-south-1`.
- Supervisor runs `queue:work`; cron runs `schedule:run` every minute.
- PDFs are rendered with dompdf (pure PHP); no headless Chrome is needed.
- RDS automated backups, 35-day retention. S3 versioning on the files bucket.

## 11. Testing

Pest feature tests for:
- tenant isolation
- invitation acceptance, expiry and single use; the 2-editor cap
- phase gating (disabled, draft, closed, past deadline)
- Phase 2 limit validation (event and sport)
- Phase 3 gender match, Phase 2 comparison flag, expiry warning
- Phase 4 copy from Long List and the justification requirement when over limit
- submit flow: editor → association admin → submitted; STA edits are audited
- Excel import validation

## 12. Delivery Milestones

1. **Foundation:**
   - roles and permissions, invitations
   - associations, sports and events, clothing items, settings
   - people registry (encryption, phone/email, sizes, S3 photos and scans, Excel import)
   - audit log
2. **Games and Phases 1–2:**
   - game setup (limits incl. total, age range, entry policy, clothing items, reminder days)
   - phase open/close, deadlines and per-association extensions
   - Phase 1, Phase 2 grid, Entry by Event PDF
   - submit flow, status matrix, reminders
3. **Phase 3 Long List:** assignments, flags, warnings, Long List PDF, STA comments and edits.
4. **Phase 4 and Reports:** Short List, justification, over-limit and clothing reports, Excel exports.

Milestone 1.1, the registry update in section 13, comes before Milestone 2.

## 13. Registry update v1.2 (decided 2026-11-09)

The user chose the fields from the player and team-official design drafts, then commented on the selection. This section overrides anything earlier in this spec where they conflict.

### Codes and file names
- `associations.code`: entered by STA when creating the association (2–10 characters, letters and digits, upper case, unique). The admin form requires it. Existing associations get it filled in by STA before go-live.
- `people.code`: generated on create as `{association code}-AT-{0001}` for athletes and `{association code}-TO-{0001}` for team officials. There is a running number per association and type, which is never reused and stays the same if the person changes later. It is unique system-wide.
- Every uploaded attachment is stored as `{code}_{kind}.{ext}`, for example `BWF-AT-0001_passport.pdf` or `BWF-AT-0001_cpr_front.jpg`. A replaced file overwrites the old name, and the old file is deleted.
- Every attachment has a **View** action in the form and in the people list. It opens a 5-minute temporary URL on the private disk.

### Common fields (athletes and team officials)
- **Names:** first, second, third and last name, plus **full name**, which replaces "preferred name". The passport reader fills full name; it can still be edited.
- **Personal details:** date of birth, age (automatic, at the game start date), gender, nationality, photo, height and weight.
- **Passport:** number, **issuing country**, **issue date**, expiry and passport scan.
- **CPR:** number and expiry, plus three attachments: **CPR front**, **CPR back**, and the **CPR reader copy** (the printout from the smart-card reader). The earlier single `cpr_scan_path` becomes `cpr_front_path`.
- **Clothing:** fit (Men's, Women's or Youth), chest in cm and waist in cm on the person. **T-shirt, suit and shoe sizes** come from the STA clothing items. The items "T-Shirt", "Suit" and "Shoes" are seeded, with `applies_to = both`. Per-game clothing item selection stays as it is.
- **Phone numbers:** every phone field has a country-code select defaulting to **+973**, stored as E.164-style `country_code` + `number`. There is a **"WhatsApp same as mobile"** checkbox, which copies the mobile number on save.

### Athletes only
- **Contact:** Player Mobile, Player Email, Player Address.
- **Emergency contact:** name, relationship and phone.
- **Guardian:** name, relationship (Father, Mother or Legal guardian), phone and email.
- **WADA certificate:** an attachment, renamed from "medical certificate".
- **Medical:** blood group, medical clearance expiry, insurance policy number, allergies, chronic conditions and current medication, plus **injury history** records (date, description and status) in a separate `person_injuries` table.
- **Consents:** to store personal data, to use photos in media, and for emergency medical treatment. Each is a boolean with an `_at` timestamp.

### Team officials only
- **Function:** the expanded role list. Head of Delegation, Team Manager, Administrator, Head Coach, Assistant Coach, Goalkeeper Coach, Fitness Coach, Performance Analyst, Team Doctor, Physiotherapist, Massage Therapist, Kit Manager, Media Officer, Security Officer, Interpreter and Other.
- **Languages spoken**.
- **Contact:** mobile, WhatsApp, email, and emergency contact (name, relationship and phone).
- **Visas:** a separate list of zero or more visas in `person_visas`. Each visa has country, status (Not started, Applied, Approved, Rejected or Visa on arrival/e-Visa), visa number, valid until and a visa copy attachment. Visas are stored on the person, not per game, and an official may hold several.
- **Health:** blood group, meal requirement (Halal, Vegetarian, Vegan, Gluten-free or No restriction), allergies, and medical conditions / medication.
- **Declarations:** accept the code of conduct, follow anti-doping and integrity rules, and consent to share data with organisers, airline and hotel. Each is a boolean with an `_at` timestamp.

### Per game (Long List, Milestone 3)
Travel insurance company, policy number and valid-until date, the insurance certificate, and vaccinations for the destination. These are recorded per game entry, not on the person.

### Required fields and completeness
- **Required to save:** the minimum stays what Milestone 1 enforces. That is type, first name, last name, date of birth, gender, nationality, passport number and passport expiry, plus function for officials. The Excel import keeps working.
- **Required to be complete:** everything else the user marked required. This is checked by `Person::missingFields(): array`.
  - **Athlete:** full name, photo, passport issuing country, passport issue date, passport scan and CPR number.
  - **Official:** full name, photo, passport issuing country, issue date and scan, mobile, WhatsApp, email, and emergency contact name, relationship and phone.
- The people list shows a **Complete** or **Incomplete** badge with the missing fields. Milestone 3 blocks Long List submission for incomplete people.

### Sensitive data
- **Encryption:** the medical and health fields are encrypted at rest. That covers blood group, allergies, conditions, medication, insurance policy number and injury descriptions.
- **Who sees them:** they are visible in the portal to the person's own association. In `/admin` they are visible only to STA users with the new permission **`view_medical` ("View medical & health data")**, and to the Super Admin. For everyone else the medical and health sections are hidden in forms and lists.
- **Audit log:** these fields are excluded from the audit log, the same way passport and CPR numbers are. Only a `medical_changed` marker is recorded.

### Passport reader (MRZ)
- **How it reads:** a "Read details from passport" action on the person form. It reads the passport scan's machine-readable zone on the server, using Tesseract restricted to `A–Z0–9<`. PDFs are converted to an image with `pdftoppm`.
- **Parsing and checks:** the result is parsed by a pure-PHP `MrzParser` with ICAO 9303 check digits.
- **What it fills:** full name, last name (the surname), first/second/third name (the given names split on spaces, with extras joined into third), passport number, issuing country, nationality, date of birth, gender and passport expiry. Issue date is not in the MRZ and stays manual.
- **On failure:** a field whose check digit fails is not filled, and is flagged.
- **Nothing is saved automatically:** the user reviews and saves.
- **Server requirement:** Tesseract and poppler-utils on the server.

## 14. Custom report builder (decided 2026-11-10)

The user asked for the custom report generation they have in their Rental Management System, in addition to the ready-made Milestone 4 reports. It is built after Milestone 4 as Milestone 4.1, using the same approach, cut to what this system needs.

### Approach
- Each **subject** is a small PHP class. It declares its base query and the fields it offers.
- One engine turns a saved **report definition** into a query. The definition is validated JSON and never SQL, and values are bound parameters.
- Database columns are never exposed automatically.

### Screens
- **Reports page in `/admin`:** "My reports" and "Shared" lists, plus a **New report** button. The ready-made Milestone 4 reports stay as they are.
- **Builder (`/admin/report-builder/{report?}`):**
  - Settings: subject, columns (tick and order), filters (rules joined by AND), group by (up to 2 levels) with counts per group, and sort.
  - Results update live: up to 500 rows on screen, with a grand total row.
  - Top bar: name, Save, Save as copy, Share (Only me, or Everyone with report access), Export to Excel (all rows, with group subtotal rows), and Delete.
- **Running a saved report:** anyone who can see it may change its filters for that run only. Only the creator or a Super Admin saves changes; anyone else uses Save as copy.

### Subjects
Every subject has a **game** filter, which is required for game-based subjects.

| Subject | One row is | Main fields |
|---|---|---|
| People | a registry person | association, code, type, full name, gender, nationality, date of birth, age (today), passport expiry, CPR expiry, function, has photo, has passport scan, complete (yes/no) |
| Entries | a Long or Short List entry | game, phase (Long List / Short List), association, sport, event (or "Official"), event gender, person code, name, type, gender, date of birth, age at game start, nationality, passport expiry |
| Submissions | a submission | game, association, phase, status, reference, revision, submitted at, approved at, deadline (effective), over limit (Phase 4), justification |
| Phase 1 answers | an answer | game, association, sport, answer |
| Phase 2 numbers | a number entry | game, association, sport, event, gender, count |
| Clothing sizes | a person's size | association, person code, name, type, item, size |

### Fields and filter operators
| Type | Operators | Group | Totals |
|---|---|---|---|
| text | is, is not, contains, is empty, is not empty | yes | count |
| enum | is any of, is none of | yes | count |
| number | =, ≠, >, ≥, <, ≤, between | no | sum, average, min, max |
| date | on, before, after, between | by month, by year | min, max |
| boolean | is yes, is no | yes | count |

Every subject offers **Count of rows**.

### Permissions and safety
- **Access:** you need `StaPermission::ViewReports`. Super Admin always has access. Associations have no builder in this version.
- **Data that is never a field:** medical and health data, insurance policy numbers, vaccinations, and passport and CPR numbers.
- **Validation:** a definition is checked on save and on every run. An unknown subject, field or operator, or a bad value, is refused.
- **Limits:** 30 columns, 20 filters and 2 group levels.
- **Editing and deleting:** only the creator or a Super Admin.

### Storage
`saved_reports`: name (up to 120 characters), subject, definition (JSON with a `version`), visibility (`private` or `shared`), created_by, updated_by, last_run_at and timestamps.

### Left out for now, from the Rental Management builder
Charts, period comparison, scheduled email delivery and relative date ranges. Each can be added later on top of the same engine.
