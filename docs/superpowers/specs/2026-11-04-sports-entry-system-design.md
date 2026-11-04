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
| PDF | Browsershot (headless Chrome). Phase 2 "Entry by Event" PDF per sport (layout from the Weightlifting sample), plus Long List and Short List PDFs (layout from the Phase 3 sample). Every PDF has the NOC and game logos, a declaration, signature/stamp boxes and a `reference_no` |
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
- Headless Chrome is installed on EC2 for Browsershot.
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
