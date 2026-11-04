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
| PDF | Browsershot (headless Chrome) for Long List / Short List PDFs |
| Excel | `maatwebsite/excel` (or Filament import/export actions) for people import and report export |
| Language | English only |
| Registry | Permanent per association; reused across games |
| Permissions | `spatie/laravel-permission` + `filament-shield`; STA rights are per-module checkboxes |

Out of scope: Arabic/RTL, SMS/WhatsApp, mobile app, end-to-end browser test suite.

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

### Registry (owned by association, permanent)
- `people`: `association_id`, `type` (`athlete|official`), `first_name`, `second_name`, `third_name`, `last_name`, `photo_path`, `passport_no` (encrypted), `passport_expiry`, `cpr_no` (encrypted), `cpr_expiry`, `nationality`, `dob`, `gender`, `height_cm`, `weight_kg`, `function` (officials only: physio, team admin, …).
- Age is computed from `dob`; it is not stored.

### Game setup (admin only)
- `games`: `name`, `logo`, `start_date`, `end_date`.
- `game_associations`: `game_id`, `association_id`. These are the invited associations.
- `game_sports`: `game_id`, `sport_id`, `p2_max_men`, `p2_max_women`, `p4_max_men`, `p4_max_women`, `p4_max_officials`.
- `game_events`: `game_id`, `event_id`, `p2_max_athletes`.
- `game_phases`: `game_id`, `phase` (1–4), `enabled`, `status` (`draft|open|closed`), `opens_at`, `deadline`.

Phase 2 counts athletes only. Team officials are counted only in Phase 4 (`p4_max_officials`, total per sport).

### Workflow
- `submissions`: `game_id`, `association_id`, `phase`, `status` (`draft|awaiting_assoc_admin|submitted`), `submitted_by`, `submitted_at`, `approved_by`, `approved_at`, `pdf_path`, `justification` (Phase 4 only; one text field per submission).
- `interest_answers` (Phase 1): `submission_id`, `game_sport_id`, `answer` (`yes|no|maybe`).
- `number_entries` (Phase 2): `submission_id`, `game_event_id`, `count`.
- `entries` (Phases 3 and 4): `submission_id`, `person_id`, `game_event_id` (athletes) or `game_sport_id` (officials).
- `comments`: `submission_id`, `user_id`, `body`.
- Audit log (`spatie/laravel-activitylog`) on submissions, entries, number_entries, interest_answers, people.

## 5. Phases and Gating

- Admin chooses which phases are enabled per game. Disabled phases are skipped.
- A phase is editable by an association only while its status is `open` **and** before its deadline. Admin opens and closes phases manually. Closed phases are read-only to associations.
- Admin selects participating associations and sports up front. Phase 1 is optional (often skipped). When Phase 1 is used, sports answered `no` are hidden for that association in later phases.

### Phase 1 — Entry by Sport
Per invited sport, the association answers Yes / No / Maybe. Not responding is tracked as "no response".

### Phase 2 — Entry by Number
Grid of sports × events with one number box per event. Hard limits are checked live:
- per event: `count ≤ game_events.p2_max_athletes`
- per sport: sum of men's events ≤ `p2_max_men`, sum of women's events ≤ `p2_max_women`

### Phase 3 — Long List
1. Association maintains its registry (form or Excel import).
2. Assign athletes to game events. The athlete's gender must match the event's gender (mixed accepts both).
3. Assign team officials to game sports.
4. Each event shows its Phase 2 number. If the Long List exceeds it, the event is **flagged, not blocked**.
5. Warning if a passport or CPR expires before `games.end_date`.
6. When the submission reaches `submitted`, a PDF is generated in the background. The association and STA can download it.

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
- STA users CRUD with permission checkboxes.
- Game setup, as tabs: Details → Associations → Sports & Events (limits) → Phases (enable, deadlines, Open/Close).
- Game dashboard: matrix of associations × enabled phases, colored by status, with a filter for pending and a **Remind pending** button.
- Submission view: read and edit, comments, audit trail, PDF download.
- People registry: search across all associations.
- Reports and Excel exports: Phase 1 answers, Phase 2 totals, Long List, Short List, over-limit (with justifications), expiring documents.

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
- Daily scheduler, while a phase is open and the association has not submitted: 3 days before the deadline, on the deadline day, and daily while overdue.
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
| Hard error | Missing required field; Phase 2 count over event or sport limit; gender mismatch; editing a closed or past-deadline phase; more than 2 editors | Blocks save |
| Warning | Long List differs from Phase 2 numbers; document expires before the game ends; Short List over limit (athletes or officials) | Saves; flagged in UI and reports (over-limit requires justification) |

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

1. **Foundation:** roles and permissions, invitations, associations, sports and events, people registry (encryption, S3 photos, Excel import), audit log.
2. **Games and Phases 1–2:** game setup, phase open/close and deadlines, Phase 1, Phase 2 grid with limits, submit flow, status matrix, reminders.
3. **Phase 3 Long List:** assignments, flags, warnings, PDF, STA comments and edits.
4. **Phase 4 and Reports:** Short List, justification, over-limit report, Excel exports.
