// Builds the scope document. Usage: node build-doc.js <imgDir> <out.docx>
const fs = require('fs');
const path = require('path');
const {
  Document, Packer, Paragraph, TextRun, HeadingLevel, Table, TableRow, TableCell, WidthType, ShadingType,
  AlignmentType, LevelFormat, ImageRun, PageOrientation, Footer, Header, PageNumber, TableOfContents, PageBreak, BorderStyle,
} = require('docx');

const [imgDir, outFile] = process.argv.slice(2);
const NAVY = '1F3A5F', LIGHT = 'E8EEF6', FONT = 'Arial';
const PORTRAIT_W = 11906 - 2 * 1134; // A4 content width (DXA)
const LAND_W = 16838 - 2 * 1134;

// ---------- helpers
const runs = parts => (Array.isArray(parts) ? parts : [parts]).map(p =>
  typeof p === 'string' ? new TextRun(p) : new TextRun({ text: p.t, bold: p.b, italics: p.i, color: p.c }));
const P = (parts, o = {}) => new Paragraph({ children: runs(parts), spacing: { after: 120 }, ...o });
const H1 = t => new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun(t)] });
const H2 = t => new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun(t)] });
const H3 = t => new Paragraph({ heading: HeadingLevel.HEADING_3, children: [new TextRun(t)] });
const B = (parts, level = 0) => new Paragraph({ numbering: { reference: 'bullets', level }, children: runs(parts), spacing: { after: 60 } });
const bullets = list => list.map(x => B(x));
const N = (parts, ref = 'num') => new Paragraph({ numbering: { reference: ref, level: 0 }, children: runs(parts), spacing: { after: 60 } });
const H1pb = t => new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true, children: [new TextRun(t)] });
const H2pb = t => new Paragraph({ heading: HeadingLevel.HEADING_2, pageBreakBefore: true, children: [new TextRun(t)] });
const brk = () => new Paragraph({ children: [new PageBreak()] });

function table(headers, rows, fractions, totalW = PORTRAIT_W) {
  const widths = fractions.map(f => Math.round(totalW * f));
  widths[widths.length - 1] = totalW - widths.slice(0, -1).reduce((a, b) => a + b, 0);
  const cell = (content, i, header, shade) => new TableCell({
    width: { size: widths[i], type: WidthType.DXA },
    shading: header ? { type: ShadingType.CLEAR, fill: NAVY, color: 'auto' } : shade ? { type: ShadingType.CLEAR, fill: 'F5F7FA', color: 'auto' } : undefined,
    margins: { top: 60, bottom: 60, left: 100, right: 100 },
    children: String(content).split('\n').map(line => new Paragraph({
      children: [new TextRun({ text: line, bold: header, color: header ? 'FFFFFF' : undefined, size: 18 })],
    })),
  });
  return [new Table({
    width: { size: totalW, type: WidthType.DXA },
    columnWidths: widths,
    rows: [
      new TableRow({ tableHeader: true, children: headers.map((h, i) => cell(h, i, true)) }),
      ...rows.map((r, ri) => new TableRow({ cantSplit: true, children: r.map((c, i) => cell(c, i, false, ri % 2 === 1)) })),
    ],
  }), new Paragraph({ spacing: { after: 120 }, children: [] })];
}

function image(name, maxWpx, maxHpx, caption) {
  const svg = fs.readFileSync(path.join(imgDir, name + '.svg'), 'utf8');
  const [, w, h] = svg.match(/width="(\d+)" height="(\d+)"/).map(Number);
  const s = Math.min(maxWpx / w, maxHpx / h);
  return [
    new Paragraph({ alignment: AlignmentType.CENTER, keepNext: true, children: [new ImageRun({ type: 'png', data: fs.readFileSync(path.join(imgDir, name + '.png')), transformation: { width: Math.round(w * s), height: Math.round(h * s) }, altText: { title: caption, description: caption, name } })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 200 }, children: [new TextRun({ text: caption, italics: true, size: 18, color: '475569' })] }),
  ];
}
const landImg = (name, caption, maxH = 520) => image(name, 960, maxH, caption);

const footer = new Footer({ children: [new Paragraph({ alignment: AlignmentType.RIGHT, children: [new TextRun({ text: 'Sports Entry Management System – Scope & Design  |  Page ', size: 16, color: '64748B' }), new TextRun({ children: [PageNumber.CURRENT], size: 16, color: '64748B' })] })] });
const header = new Header({ children: [new Paragraph({ alignment: AlignmentType.RIGHT, children: [new TextRun({ text: 'DRAFT FOR REVIEW – v1.0', size: 16, color: '94A3B8' })] })] });
const portrait = { page: { size: { width: 11906, height: 16838 }, margin: { top: 1134, bottom: 1134, left: 1134, right: 1134 } } };
const landscape = { page: { size: { width: 11906, height: 16838, orientation: PageOrientation.LANDSCAPE }, margin: { top: 1134, bottom: 1134, left: 1134, right: 1134 } } };

// ---------- content
const cover = [
  new Paragraph({ spacing: { before: 2400 }, children: [] }),
  new Paragraph({ children: [new TextRun({ text: 'Sports Entry Management System', bold: true, size: 56, color: NAVY })], spacing: { after: 200 } }),
  new Paragraph({ children: [new TextRun({ text: 'Project Scope, Data Flow & Solution Design', size: 32, color: '475569' })], spacing: { after: 600 },
    border: { bottom: { style: BorderStyle.SINGLE, size: 12, color: NAVY, space: 8 } } }),
  ...table(['Item', 'Detail'], [
    ['Document version', '1.0 (Draft for review)'],
    ['Date', '4 November 2026'],
    ['Prepared for', 'STA management, Super Admin and project sponsors'],
    ['Status', 'Awaiting stakeholder sign-off before implementation'],
    ['Related document', 'docs/superpowers/specs/2026-11-04-sports-entry-system-design.md'],
  ], [0.3, 0.7]),
  H1pb('Document Control'),
  ...table(['Version', 'Date', 'Author', 'Change'], [
    ['0.1', '04-Nov-2026', 'Solution Architect', 'Initial design workshop output'],
    ['1.0', '04-Nov-2026', 'Solution Architect', 'Complete scope, data flow diagrams, data model and delivery plan'],
  ], [0.15, 0.2, 0.25, 0.4]),
  H2('Sign-off'),
  ...table(['Role', 'Name', 'Signature', 'Date'], [
    ['Project Sponsor', '', '', ''], ['Super Admin (STA)', '', '', ''], ['Technical Lead', '', '', ''],
  ], [0.3, 0.3, 0.2, 0.2]),
  brk(),
  new Paragraph({ children: [new TextRun({ text: 'Contents', bold: true, size: 32, color: NAVY })], spacing: { after: 200 } }),
  new TableOfContents('Contents', { hyperlink: true, headingStyleRange: '1-2' }),
  brk(),
];

const partA = [
  H1('1. Introduction'),
  H2('1.1 Purpose of this document'),
  P('This document defines the complete scope of the Sports Entry Management System before implementation starts. It covers the business problem, users and permissions, functional requirements, phase workflow, data flow diagrams, data model, business rules, non-functional requirements, technical architecture, delivery plan, risks and open questions. Once signed off, it is the baseline for the implementation plan and for change control.'),
  H2('1.2 Background and current problem'),
  P('For every multi-sport event (for example the Arab Games, Asian Youth Games or Aichi-Nagoya Asian Games), STA must collect participation intent, entry numbers and full athlete and team official details from around 50 national sports associations. Today this is done entirely by email and Excel:'),
  ...bullets([
    'STA emails each association individually with the event, the sports and the events on offer.',
    'Associations reply with Excel sheets in inconsistent formats, often incomplete or with errors.',
    'STA staff chase each association by email and phone until every reply is received.',
    'Data is re-typed and consolidated manually; passport and CPR details travel as email attachments.',
    'There is no single view of who has submitted, who is late, or how a list changed over time.',
  ]),
  H2('1.3 Objectives'),
  ...bullets([
    'Replace email and Excel collection with one secure web portal for all associations.',
    'Remove manual follow-up through deadlines, automatic reminders and a live status dashboard.',
    'Enforce entry limits and data quality at the point of entry.',
    'Keep a permanent athlete and team official registry per association, reused across games.',
    'Produce consistent Long List and Short List PDFs and reports with no manual consolidation.',
    'Protect personal data (passport, CPR, photos) with encryption, access control and an audit trail.',
  ]),
  H2('1.4 Success criteria'),
  ...table(['Measure', 'Target'], [
    ['Associations submitting through the portal (no email/Excel)', '100% from the first game run on the system'],
    ['Manual follow-up emails sent by STA per phase', 'Replaced by automatic reminders and one "Remind pending" click'],
    ['Time to see which associations have not submitted', 'Real time, on one dashboard'],
    ['Entries exceeding Phase 2 limits', 'Zero (blocked at entry)'],
    ['Time to produce consolidated Long / Short List', 'Minutes (generated), instead of days (manual)'],
  ], [0.6, 0.4]),

  H1('2. Scope'),
  H2('2.1 In scope'),
  ...table(['#', 'Module', 'Summary'], [
    ['1', 'Access & onboarding', 'Super Admin, STA users with permission checkboxes, association admin invitation links, up to 2 association editors per association'],
    ['2', 'Master data', 'Associations (logo and details optional), sports per association, events per sport (name + gender)'],
    ['3', 'People registry', 'Permanent registry of athletes and team officials per association, with photo, passport, CPR and physical details; Excel import'],
    ['4', 'Game setup', 'Game name, logo, dates; invited associations; sports and events offered; Phase 2 and Phase 4 limits; phases enabled, deadlines, open/close'],
    ['5', 'Phase 1 – Entry by Sport', 'Optional. Association answers Yes / No / Maybe per sport'],
    ['6', 'Phase 2 – Entry by Number', 'Association enters number of athletes per event within event and sport (gender) limits'],
    ['7', 'Phase 3 – Long List', 'Association assigns registry athletes to events and officials to sports; approval; PDF'],
    ['8', 'Phase 4 – Short List', 'Association reduces the Long List to the admin maximums (athletes and officials); single justification if over limit; PDF'],
    ['9', 'Approval & review', 'Editor → Association Admin approval → STA; STA direct edits and comments; full audit log'],
    ['10', 'Notifications', 'Invitation, phase opened, deadline reminders, overdue, approval requests, comments'],
    ['11', 'Dashboard & reports', 'Association × phase status matrix, people search, PDFs, Excel exports, over-limit and expiring document reports'],
  ], [0.06, 0.24, 0.7]),
  H2('2.2 Out of scope (this release)'),
  ...bullets([
    'Arabic language and right-to-left layout (English only).',
    'SMS / WhatsApp notifications (email only).',
    'Native mobile apps (the web portal is responsive and works on tablets and phones).',
    'Accreditation, travel, accommodation, uniforms and results management.',
    'Direct integration with games organising committee systems (PDF and Excel are shared manually).',
    'Uploading passport / CPR scanned copies (only the numbers and expiry dates, plus a photo).',
    'Online payments.',
  ]),
  H2('2.3 Assumptions'),
  ...bullets([
    'Each association has one Association Admin and at most two Association Editors.',
    'Sports belong to exactly one association; an association can own several sports (e.g. Badminton and Squash).',
    'Events belong to one sport and have one gender: men, women or mixed.',
    'Team officials are not counted in Phase 2; they are counted only in Phase 4 (one total per sport).',
    'Age is calculated from date of birth; the reference date is the game start date.',
    'STA shares the final PDFs with organising committees by email outside the system.',
    'Users have a modern browser (latest Chrome, Edge, Safari or Firefox).',
  ]),
  H2('2.4 Constraints'),
  ...bullets([
    'Technology: Laravel (PHP 8.5) with Filament; MySQL 8.',
    'Hosting: AWS me-south-1 (Bahrain) – EC2, RDS, S3 with presigned URLs, SES.',
    'Personal data must not be publicly accessible at any time.',
  ]),

  H1('3. Users, Roles and Permissions'),
  H2('3.1 Roles'),
  ...table(['Role', 'Who', 'Created by', 'Scope'], [
    ['Super Admin', 'STA system owner', 'Seeded at installation', 'Full access to everything, including STA user permissions'],
    ['STA User', 'STA staff', 'Super Admin', 'Only the modules ticked by the Super Admin (see 3.3)'],
    ['Association Admin', 'Head / secretary of an association', 'Invitation link from Super Admin', 'Own association only; approves submissions; invites editors'],
    ['Association Editor', 'Data entry person in an association (max 2)', 'Invitation from Association Admin', 'Own association only; enters data and submits for approval'],
  ], [0.18, 0.24, 0.24, 0.34]),
  H2('3.2 Permission matrix'),
  ...table(['Action', 'Super Admin', 'STA User*', 'Assoc. Admin', 'Assoc. Editor'], [
    ['Create / edit associations and send invitations', '✓', 'if granted', '–', '–'],
    ['Create / edit sports and events', '✓', 'if granted', '–', '–'],
    ['Create / edit games, limits, phases', '✓', 'if granted', '–', '–'],
    ['Open / close phases, set deadlines', '✓', 'if granted', '–', '–'],
    ['Manage STA users and their permissions', '✓', '–', '–', '–'],
    ['Edit own association profile and logo', '✓', 'if granted', '✓', '–'],
    ['Invite / deactivate association editors', '✓', '–', '✓', '–'],
    ['Add / edit athletes and team officials', '✓', 'if granted', '✓', '✓'],
    ['Enter phase data (Phases 1–4)', '✓', 'if granted', '✓', '✓'],
    ['Submit phase data', '–', '–', '✓ (direct to STA)', '✓ (to Assoc. Admin)'],
    ['Approve / return editor submission', '–', '–', '✓', '–'],
    ['Edit submitted data directly', '✓', 'if granted', '–', '–'],
    ['Comment on submissions', '✓', 'if granted', '✓', '✓'],
    ['Send "Remind pending"', '✓', 'if granted', '–', '–'],
    ['View dashboard, reports and exports (all associations)', '✓', 'if granted', '–', '–'],
    ['Download own association PDFs', '✓', 'if granted', '✓', '✓'],
    ['View audit log', '✓', 'if granted', '–', '–'],
  ], [0.4, 0.13, 0.13, 0.17, 0.17]),
  P([{ t: '* ', b: true }, 'STA user rights are per-module checkboxes set by the Super Admin. Association users can never see another association\'s data.'], { spacing: { after: 120 } }),
  H2('3.3 STA permission checkboxes'),
  ...table(['Permission', 'Allows'], [
    ['manage_associations', 'Create and edit associations, send invitations'],
    ['manage_master_data', 'Create and edit sports and events'],
    ['manage_games', 'Create and edit games, limits, phases; open and close phases'],
    ['view_submissions', 'View all associations\' submissions and PDFs'],
    ['edit_submissions', 'Edit submitted data directly and add comments'],
    ['manage_people', 'View and edit the registry of any association'],
    ['send_reminders', 'Use "Remind pending"'],
    ['view_reports', 'View reports and export Excel'],
    ['view_audit_log', 'View the change history'],
  ], [0.3, 0.7]),

  H1('4. Functional Requirements'),
  P('Priority: M = Must have for go-live, S = Should have (can follow shortly after).'),
  H2('4.1 Access and onboarding'),
  ...table(['ID', 'Requirement', 'Priority'], [
    ['FR-01', 'Super Admin creates an association with name and email (mandatory); logo, phone, address and website are optional.', 'M'],
    ['FR-02', 'Super Admin generates an invitation for the Association Admin. The system emails the link and also shows a "copy link" button so it can be shared by other means.', 'M'],
    ['FR-03', 'Invitation links are single-use and expire after 7 days; the admin can resend or revoke them.', 'M'],
    ['FR-04', 'On accepting, the invitee sets their name and password and is linked to the association.', 'M'],
    ['FR-05', 'Association Admin can invite up to 2 Association Editors; the system blocks a third active editor.', 'M'],
    ['FR-06', 'Association Admin can deactivate an editor, freeing the slot.', 'M'],
    ['FR-07', 'Super Admin creates STA users and ticks their permissions.', 'M'],
    ['FR-08', 'Password reset by email; login rate limiting; optional app-based MFA for STA users.', 'M / S'],
  ], [0.1, 0.78, 0.12]),
  H2('4.2 Master data'),
  ...table(['ID', 'Requirement', 'Priority'], [
    ['FR-10', 'Admin creates sports and assigns each to one association. One association may own several sports.', 'M'],
    ['FR-11', 'Admin creates events under a sport with a name and gender (men, women, mixed), e.g. "100m – Men".', 'M'],
    ['FR-12', 'Associations can view but not add, edit or delete sports and events.', 'M'],
    ['FR-13', 'Sports and events used in a game cannot be deleted, only deactivated.', 'M'],
  ], [0.1, 0.78, 0.12]),
  H2('4.3 People registry'),
  ...table(['ID', 'Requirement', 'Priority'], [
    ['FR-20', 'Association users add athletes with: first, second, third and last name; photo; passport number and expiry; CPR number and expiry; date of birth; age (calculated); gender; height; weight; nationality.', 'M'],
    ['FR-21', 'Association users add team officials with the same fields plus nationality and function (e.g. Physio, Team Admin, Coach, Team Manager, Doctor).', 'M'],
    ['FR-22', 'The registry is permanent and reused across games; updating a person updates all open games.', 'M'],
    ['FR-23', 'Bulk import from an Excel template; each row is validated and errors are shown before anything is saved.', 'M'],
    ['FR-24', 'Duplicate check on passport number and CPR number within the system.', 'M'],
    ['FR-25', 'Photo upload (JPG/PNG, max 2 MB) stored privately in S3.', 'M'],
    ['FR-26', 'Warn when a passport or CPR expires before the end date of any game the person is entered in.', 'M'],
    ['FR-27', 'STA (with permission) can search people across all associations.', 'M'],
  ], [0.1, 0.78, 0.12]),
  H2('4.4 Game setup and phase control'),
  ...table(['ID', 'Requirement', 'Priority'], [
    ['FR-30', 'Admin creates a game with name, logo, start date and end date.', 'M'],
    ['FR-31', 'Admin selects participating associations, the sports and the events offered in the game.', 'M'],
    ['FR-32', 'For each game sport the admin sets Phase 2 limits (max men, max women) and Phase 4 maximums (max men, max women, max team officials).', 'M'],
    ['FR-33', 'For each game event the admin sets the maximum number of athletes per association (Phase 2).', 'M'],
    ['FR-34', 'Admin chooses which phases (1–4) are used for the game.', 'M'],
    ['FR-35', 'Admin sets a deadline per phase and opens / closes each phase manually. Only one phase is open at a time.', 'M'],
    ['FR-36', 'Opening a phase notifies all invited associations by email.', 'M'],
    ['FR-37', 'Associations can only edit the phase that is open and before its deadline; earlier phases are read-only.', 'M'],
  ], [0.1, 0.78, 0.12]),
  H2('4.5 Phase 1 – Entry by Sport (optional)'),
  ...table(['ID', 'Requirement', 'Priority'], [
    ['FR-40', 'Association answers Yes, No or Maybe for each sport offered to it in the game.', 'M'],
    ['FR-41', 'Sports not answered are reported as "No response".', 'M'],
    ['FR-42', 'When Phase 1 is used, sports answered "No" are hidden from that association in later phases.', 'M'],
    ['FR-43', 'When Phase 1 is disabled, the admin\'s selection of sports applies directly to Phase 2.', 'M'],
  ], [0.1, 0.78, 0.12]),
  H2('4.6 Phase 2 – Entry by Number'),
  ...table(['ID', 'Requirement', 'Priority'], [
    ['FR-50', 'Association sees a grid of its sports and events with one number field per event (e.g. 100m Men: 1, 100m Women: 1).', 'M'],
    ['FR-51', 'The number per event cannot exceed the event maximum.', 'M'],
    ['FR-52', 'The total of men\'s events per sport cannot exceed the sport\'s men limit; same for women.', 'M'],
    ['FR-53', 'Limits and running totals are shown live while typing. Team officials are not entered in Phase 2.', 'M'],
  ], [0.1, 0.78, 0.12]),
  H2('4.7 Phase 3 – Long List'),
  ...table(['ID', 'Requirement', 'Priority'], [
    ['FR-60', 'Association selects athletes from its registry and assigns each to one or more events.', 'M'],
    ['FR-61', 'An athlete\'s gender must match the event gender (mixed events accept both).', 'M'],
    ['FR-62', 'Association selects team officials from its registry and assigns them to sports.', 'M'],
    ['FR-63', 'The Phase 2 number is shown next to each event; differences are flagged (not blocked) for STA review.', 'M'],
    ['FR-64', 'Passport / CPR expiring before the game end date is flagged.', 'M'],
    ['FR-65', 'On final submission a Long List PDF (with photos, association and game logos) is generated and downloadable by the association and STA.', 'M'],
  ], [0.1, 0.78, 0.12]),
  H2('4.8 Phase 4 – Short List'),
  ...table(['ID', 'Requirement', 'Priority'], [
    ['FR-70', 'The Short List starts as a copy of the association\'s submitted Long List; the association unticks people to shorten it.', 'M'],
    ['FR-71', 'Limits per sport: maximum men athletes, maximum women athletes and maximum team officials.', 'M'],
    ['FR-72', 'If any limit is exceeded, one justification text field becomes mandatory before submitting; the submission is allowed.', 'M'],
    ['FR-73', 'A Short List PDF is generated on final submission.', 'M'],
    ['FR-74', 'An over-limit report lists every association and sport above the limit, with the justification.', 'M'],
  ], [0.1, 0.78, 0.12]),
  H2('4.9 Submission, approval and review'),
  ...table(['ID', 'Requirement', 'Priority'], [
    ['FR-80', 'One submission per association per phase per game, starting as Draft.', 'M'],
    ['FR-81', 'Editor submits → status "Awaiting Association Admin" and the admin is emailed.', 'M'],
    ['FR-82', 'Association Admin approves (→ "Submitted to STA") or returns it to Draft with a comment.', 'M'],
    ['FR-83', 'Association Admin may submit directly (→ "Submitted to STA").', 'M'],
    ['FR-84', 'After submission the association cannot edit; STA (with permission) can edit directly. Every change is audited.', 'M'],
    ['FR-85', 'STA and association users can add comments on a submission; each comment notifies the other side.', 'M'],
    ['FR-86', 'If two users edit at the same time, the second save is rejected with a "changed by X, reload" message.', 'M'],
  ], [0.1, 0.78, 0.12]),
  H2('4.10 Dashboard, notifications and reports'),
  ...table(['ID', 'Requirement', 'Priority'], [
    ['FR-90', 'Game dashboard: matrix of associations × enabled phases, coloured by status (not started, draft, awaiting admin, submitted, overdue).', 'M'],
    ['FR-91', '"Remind pending" button emails every association that has not submitted the open phase.', 'M'],
    ['FR-92', 'Automatic reminders: when the phase opens, 3 days before the deadline, on the deadline day, and daily while overdue.', 'M'],
    ['FR-93', 'Reports listed in section 11, each exportable to Excel.', 'M'],
    ['FR-94', 'Association home page shows each invited game, the open phase, its deadline and status.', 'M'],
  ], [0.1, 0.78, 0.12]),
];

const partB = [
  H1('5. Process and Phase Workflow'),
  ...landImg('workflow', 'Figure 1 – Phase sequence and submission status flow', 330),
  H2('5.1 Phase summary'),
  ...table(['Phase', 'Admin sets up', 'Association does', 'Validation', 'Output'], [
    ['1 – Entry by Sport (optional)', 'Invited associations and sports; deadline', 'Answers Yes / No / Maybe per sport', 'All sports answered', 'Interest report'],
    ['2 – Entry by Number', 'Per event max athletes; per sport max men / women', 'Enters number of athletes per event', 'Blocks above event or sport limits', 'Numbers report'],
    ['3 – Long List', 'Opens phase; deadline', 'Selects athletes per event and officials per sport from the registry', 'Gender match (block); Phase 2 difference and document expiry (warn)', 'Long List PDF and report'],
    ['4 – Short List', 'Per sport max men, max women, max officials', 'Unticks people from the Long List', 'Over limit requires one justification', 'Short List PDF; over-limit report'],
  ], [0.17, 0.2, 0.23, 0.22, 0.18], LAND_W),
  H2('5.2 End-to-end flow'),
  ...[
    'Admin creates associations, sports and events, and invites each Association Admin.',
    'Association Admin accepts the invite, completes the profile and invites up to two editors.',
    'Associations build their athlete and team official registry (form or Excel import).',
    'Admin creates a game, selects associations, sports, events and limits, and chooses the phases.',
    'Admin opens the first enabled phase; all invited associations are emailed.',
    'Associations fill in the phase; editors submit to their Association Admin, who approves to STA.',
    'Dashboard shows status live; reminders go out automatically; STA reviews, comments or edits.',
    'Admin closes the phase and opens the next one; repeat until the Short List.',
    'STA downloads PDFs and reports and shares them with the organising committee.',
  ].map(t => N(t)),

  H1pb('6. Data Flow Diagrams'),
  P('The diagrams use Gane–Sarson notation: grey boxes are external entities, dark rounded boxes are processes, open green boxes are data stores and arrows are data flows.'),
  H2('6.1 Context diagram (Level 0)'),
  ...landImg('dfd0-context', 'Figure 2 – Context diagram: the system and its external entities', 315),
  ...table(['External entity', 'Sends to the system', 'Receives from the system'], [
    ['Super Admin', 'Associations, sports, events, games, limits, phases, STA users', 'Dashboard, reports, exports, PDFs'],
    ['STA User', 'Edits, comments, reminder requests (per permission)', 'Submissions, PDFs, reports'],
    ['Association Admin', 'Profile, editor invitations, approvals, data entry', 'Invitations, approval requests, comments, PDFs'],
    ['Association Editor', 'People records, phase entries, submissions', 'Open phases, limits, validation messages, comments'],
    ['Amazon SES', '–', 'Emails to send'],
    ['Amazon S3', 'Stored files on request', 'Photos, logos, PDFs, import files'],
  ], [0.2, 0.4, 0.4], LAND_W),
  H2pb('6.2 Level 1 – Setup and onboarding'),
  ...landImg('dfd1a-setup', 'Figure 3 – Level 1 DFD: master data, onboarding and game setup', 540),
  H2pb('6.3 Level 1 – Registry, phase entries and review'),
  ...landImg('dfd1b-submissions', 'Figure 4 – Level 1 DFD: people registry, phase entries, approval and review', 540),
  H2pb('6.4 Level 1 – Notifications and outputs'),
  ...landImg('dfd1c-outputs', 'Figure 5 – Level 1 DFD: reminders, PDFs and reports', 540),
  H2pb('6.5 Process descriptions'),
  ...table(['No.', 'Process', 'Inputs', 'Outputs', 'Data stores'], [
    ['1.0', 'Manage Master Data', 'Association, sport and event details from admin', 'Saved associations, sports, events', 'D1, D2'],
    ['2.0', 'Invite & Onboard Users', 'Invite request; invite acceptance; editor invites', 'Invitation emails; user accounts', 'D1'],
    ['3.0', 'Set Up Game & Phases', 'Game details, limits, phases, deadlines', 'Game configuration; phase status', 'D1, D2, D3'],
    ['4.0', 'Maintain People Registry', 'Athlete / official details, photos, Excel files', 'People records, photos', 'D4, D7'],
    ['5.0', 'Capture Phase Entries', 'Answers, numbers, selections; limits and people', 'Draft / submitted entries; errors and warnings', 'D3, D4, D5'],
    ['6.0', 'Approve & Review Submissions', 'Pending submissions; approvals; STA edits and comments', 'Status changes, comments, audit records', 'D5, D6'],
    ['7.0', 'Send Notifications & Reminders', 'Deadlines, status, contacts; "Remind pending"', 'Queued emails', 'D1, D3, D5'],
    ['8.0', 'Generate PDFs & Reports', 'Lists, people, limits, justifications', 'PDFs, reports, Excel exports', 'D3, D4, D5, D7'],
  ], [0.06, 0.2, 0.28, 0.28, 0.18], LAND_W),
  H2('6.6 Data stores'),
  ...table(['Store', 'Name', 'Holds', 'Database tables'], [
    ['D1', 'Associations & Users', 'Associations, user accounts, roles, invitations', 'associations, users, invitations, roles/permissions'],
    ['D2', 'Sports & Events', 'Sports per association, events per sport', 'sports, events'],
    ['D3', 'Games, Phases & Limits', 'Games, invited associations, offered sports/events, limits, phases', 'games, game_associations, game_sports, game_events, game_phases'],
    ['D4', 'People Registry', 'Athletes and team officials', 'people'],
    ['D5', 'Submissions & Entries', 'Phase submissions and their contents, comments', 'submissions, interest_answers, number_entries, entries, comments'],
    ['D6', 'Audit Log', 'Who changed what and when, before and after values', 'activity_log'],
    ['D7', 'Files (S3)', 'Photos, logos, PDFs, uploaded Excel files', 'Private S3 bucket (paths stored in the tables above)'],
  ], [0.07, 0.2, 0.38, 0.35], LAND_W),

  H1pb('7. Data Model'),
  ...landImg('erd', 'Figure 6 – Entity relationship diagram (arrows point from child to parent)', 545),
  H2pb('7.1 Data dictionary'),
  ...table(['Table', 'Purpose', 'Key fields and rules'], [
    ['associations', 'National sports association', 'name and email required and unique; logo, phone, address, website optional'],
    ['users', 'All login accounts', 'role: super_admin, sta, assoc_admin, assoc_editor; association_id required for association roles; max 2 active editors per association'],
    ['invitations', 'Pending invites', 'token stored hashed; single use; expires_at = sent + 7 days'],
    ['sports', 'Sport owned by an association', 'association_id, name; unique name'],
    ['events', 'Event within a sport', 'sport_id, name, gender (men / women / mixed); unique (sport, name, gender)'],
    ['people', 'Athlete or team official', 'type; four name parts; photo; passport_no and cpr_no encrypted, unique; expiry dates; dob; gender; height cm; weight kg; nationality; function (officials only)'],
    ['games', 'Multi-sport event', 'name, logo, start_date ≤ end_date'],
    ['game_associations', 'Associations invited to a game', 'unique (game, association)'],
    ['game_sports', 'Sport offered in a game + limits', 'p2_max_men, p2_max_women, p4_max_men, p4_max_women, p4_max_officials (all ≥ 0)'],
    ['game_events', 'Event offered in a game', 'p2_max_athletes per association'],
    ['game_phases', 'Phase configuration per game', 'phase 1–4, enabled, status draft/open/closed, opens_at, deadline; only one open phase per game'],
    ['submissions', 'One per game + association + phase', 'status draft / awaiting_assoc_admin / submitted; submitted_by/at; approved_by/at; justification (Phase 4); pdf_path; unique (game, association, phase)'],
    ['interest_answers', 'Phase 1 answers', 'answer yes / no / maybe per game sport'],
    ['number_entries', 'Phase 2 numbers', 'count per game event, 0 ≤ count ≤ event max'],
    ['entries', 'Phase 3 and 4 lists', 'person + game_event (athlete) or person + game_sport (official)'],
    ['comments', 'Discussion on a submission', 'submission_id, user_id, body'],
    ['activity_log', 'Audit trail', 'user, subject table and id, old and new values, timestamp'],
  ], [0.17, 0.25, 0.58], LAND_W),

  H1pb('8. Technical Architecture'),
  ...landImg('architecture', 'Figure 7 – Deployment architecture on AWS (me-south-1, Bahrain)', 290),
  ...table(['Layer', 'Choice', 'Why'], [
    ['Application', 'Laravel (latest stable), PHP 8.5', 'Mature, well-supported framework; team preference'],
    ['Admin and portal UI', 'Filament (v4+) panels /admin and /portal, Livewire for custom grids', 'Forms, tables, filters, permissions, multi-tenancy and MFA out of the box'],
    ['Permissions', 'spatie/laravel-permission + Filament Shield', 'Per-module checkboxes for STA users'],
    ['Audit', 'spatie/laravel-activitylog', 'Before/after values for every change'],
    ['Database', 'Amazon RDS MySQL 8', 'Managed backups, patching, encryption at rest'],
    ['Files', 'Amazon S3 private bucket, presigned URLs (5 min)', 'Photos and PDFs never public'],
    ['Email', 'Amazon SES via Laravel queued notifications', 'Reliable bulk sending to ~50 associations'],
    ['Background jobs', 'Laravel queue under Supervisor; cron for scheduler', 'Emails and PDFs do not slow down pages'],
    ['PDF', 'Browsershot (headless Chrome)', 'Pixel-accurate PDFs with photos and logos'],
    ['Excel', 'Laravel Excel / Filament import-export', 'Registry import and report export'],
    ['Server', 'EC2, Amazon Linux 2023, Nginx + PHP-FPM', 'Simple single-server deployment, scalable later'],
  ], [0.18, 0.42, 0.4], LAND_W),
];

const partC = [
  H1('9. Business Rules and Validation'),
  ...table(['ID', 'Rule', 'Type'], [
    ['BR-01', 'An association user can only see and change data of their own association.', 'Block'],
    ['BR-02', 'Maximum 2 active Association Editors per association.', 'Block'],
    ['BR-03', 'Associations cannot create or change sports, events or games.', 'Block'],
    ['BR-04', 'Only an open phase before its deadline can be edited by an association.', 'Block'],
    ['BR-05', 'Only one phase per game can be open at a time; disabled phases are skipped.', 'Block'],
    ['BR-06', 'Phase 2: number per event ≤ event maximum.', 'Block'],
    ['BR-07', 'Phase 2: total men per sport ≤ sport men limit; total women ≤ sport women limit.', 'Block'],
    ['BR-08', 'Phase 3/4: athlete gender must match event gender (mixed accepts both).', 'Block'],
    ['BR-09', 'Phase 3: Long List count differing from Phase 2 number is flagged.', 'Warn'],
    ['BR-10', 'Passport or CPR expiring before the game end date is flagged.', 'Warn'],
    ['BR-11', 'Phase 4: Short List may only contain people from the submitted Long List.', 'Block'],
    ['BR-12', 'Phase 4: above max men / women / officials requires the justification field.', 'Block until filled'],
    ['BR-13', 'Passport and CPR numbers must be unique across the system.', 'Block'],
    ['BR-14', 'Submitted data can only be changed by STA; every change is audited.', 'Block'],
    ['BR-15', 'Concurrent edits: a stale save is rejected.', 'Block'],
    ['BR-16', 'Sports, events and people used in a game cannot be deleted, only deactivated.', 'Block'],
  ], [0.1, 0.72, 0.18]),

  H1('10. Notifications'),
  ...table(['Event', 'Trigger', 'Recipients'], [
    ['Invitation', 'Admin invites Association Admin; Association Admin invites editor', 'Invitee'],
    ['Phase opened', 'Admin opens a phase', 'All users of invited associations'],
    ['Deadline approaching', 'Daily job, 3 days before deadline, not submitted', 'Users of that association'],
    ['Deadline today', 'Daily job, deadline day, not submitted', 'Users of that association'],
    ['Overdue', 'Daily job, after deadline while phase open, not submitted', 'Users of that association; summary to STA'],
    ['Remind pending', 'STA clicks the button', 'Associations not yet submitted'],
    ['Approval needed', 'Editor submits', 'Association Admin'],
    ['Returned to draft', 'Association Admin returns', 'Editor(s)'],
    ['Submission received', 'Status becomes "Submitted to STA"', 'STA users with view_submissions; association users'],
    ['New comment', 'Comment added', 'The other party (STA or association)'],
    ['Edited by STA', 'STA changes submitted data', 'Association Admin'],
  ], [0.22, 0.48, 0.3]),

  H1('11. Reports and Exports'),
  ...table(['Report', 'Content', 'Format'], [
    ['Submission status', 'Associations × phases with status and submit date', 'Screen, Excel'],
    ['Phase 1 interest', 'Yes / No / Maybe / No response per association and sport', 'Screen, Excel'],
    ['Phase 2 numbers', 'Numbers per event and totals per sport and gender vs limits', 'Screen, Excel'],
    ['Long List', 'Per association: athletes by event, officials by sport, with photos', 'PDF, Excel'],
    ['Short List', 'Per association: final athletes and officials', 'PDF, Excel'],
    ['Phase 2 vs Long List differences', 'Events where the Long List differs from Phase 2 numbers', 'Screen, Excel'],
    ['Over-limit (Phase 4)', 'Association, sport, limit, actual, justification', 'Screen, Excel'],
    ['Expiring documents', 'People whose passport or CPR expires before a game ends', 'Screen, Excel'],
    ['Game consolidated list', 'All associations\' Short Lists for one game', 'Excel'],
  ], [0.28, 0.54, 0.18]),

  H1('12. Non-Functional Requirements'),
  ...table(['Area', 'Requirement'], [
    ['Security – isolation', 'Tenant isolation enforced by global scopes and Filament tenancy; automated tests prove cross-association access is impossible.'],
    ['Security – personal data', 'Passport and CPR numbers encrypted at rest (application-level); RDS and S3 encryption enabled; files only through 5-minute presigned URLs.'],
    ['Security – access', 'HTTPS only (TLS 1.2+); 30-minute idle session timeout; login rate limiting; strong passwords; optional MFA for STA.'],
    ['Audit', 'Every create / update / delete on people, submissions and entries logged with user, time and before/after values; retained for at least 3 years.'],
    ['Performance', 'Pages load in under 2 seconds for up to 150 concurrent users; PDF generated within 1 minute in the background.'],
    ['Availability', 'Target 99.5% monthly; planned maintenance outside working hours.'],
    ['Backup and recovery', 'RDS automated backups with 35-day retention and point-in-time restore; S3 versioning. RPO 24 hours, RTO 4 hours.'],
    ['Usability', 'Responsive layout for desktop, tablet and phone; English UI; clear inline validation messages.'],
    ['Browser support', 'Latest two versions of Chrome, Edge, Safari and Firefox.'],
    ['Maintainability', 'Automated feature tests for all workflows; infrastructure and deployment documented.'],
    ['Data retention', 'People records retained while the association is active; to be confirmed by STA policy (see open questions).'],
  ], [0.25, 0.75]),

  H1('13. Delivery Plan'),
  ...table(['Milestone', 'Deliverables', 'Acceptance criteria'], [
    ['M1 – Foundation', 'Roles and permissions, STA users, associations, invitations, editors cap, sports and events, people registry with encryption, S3 photos, Excel import, audit log', 'Admin can onboard an association end to end; association builds its registry by form and Excel; cross-association access tests pass'],
    ['M2 – Games and Phases 1–2', 'Game setup, limits, phase enable/open/close, deadlines, Phase 1, Phase 2 grid, submit and approval flow, status dashboard, automatic reminders, "Remind pending"', 'A pilot game runs Phase 1 and Phase 2 with real associations; limits are enforced; reminders arrive'],
    ['M3 – Phase 3 Long List', 'Athlete-to-event and official-to-sport assignment, flags and warnings, Long List PDF, STA comments and direct edits', 'Long List PDF matches the agreed template; STA edits appear in the audit log'],
    ['M4 – Phase 4 and Reports', 'Short List picker, officials limit, justification, Short List PDF, all reports and Excel exports', 'Over-limit report is correct; all section 11 reports export'],
    ['Go-live', 'Production deployment, data migration of associations, user training, hand-over', 'Sign-off by STA; first live game issued through the portal'],
  ], [0.2, 0.45, 0.35]),

  H1('14. Risks and Mitigations'),
  ...table(['Risk', 'Impact', 'Mitigation'], [
    ['Associations slow to adopt the portal', 'High', 'Simple invite flow, Excel import, short training session and a one-page user guide'],
    ['Personal data breach', 'High', 'Encryption, private storage, tenant isolation tests, audit log, least-privilege STA permissions'],
    ['Inconsistent existing Excel data', 'Medium', 'Import template with row-level validation and duplicate checks'],
    ['Emails landing in spam', 'Medium', 'SES with verified domain, SPF, DKIM and DMARC'],
    ['Rule changes from organising committees', 'Medium', 'Limits configurable per game; no hard-coded rules'],
    ['Late scope additions', 'Medium', 'Change control against this signed-off scope'],
  ], [0.35, 0.12, 0.53]),

  H1('15. Open Questions for Sign-off'),
  ...table(['#', 'Question', 'Proposed default'], [
    ['1', 'Is the Phase 4 team officials limit one total per sport, or split by gender?', 'One total per sport'],
    ['2', 'How do mixed events count against the Phase 2 men / women sport limits?', 'Mixed events count only against the event maximum'],
    ['3', 'Can STA extend a deadline or re-open a phase for a single association?', 'Yes – per-association deadline extension'],
    ['4', 'Is a sample of the current Long List / Short List Excel or PDF available to copy the layout?', 'Use a clean STA-branded template'],
    ['5', 'How long should personal data be kept after a game?', 'Keep while association active; delete on request'],
    ['6', 'Should scanned copies of passport / CPR be uploaded?', 'No (numbers and expiry only)'],
    ['7', 'Reminder timing – is 3 days before the deadline right?', 'Yes, configurable later'],
    ['8', 'Should the reference date for age be the game start date?', 'Yes'],
  ], [0.05, 0.6, 0.35]),

  H1('16. Glossary'),
  ...table(['Term', 'Meaning'], [
    ['STA', 'The organisation managing national participation in multi-sport games (system owner).'],
    ['Association', 'A national sports association / federation that enters athletes.'],
    ['Game', 'A multi-sport event, e.g. Arab Games, Asian Youth Games, Aichi-Nagoya Asian Games.'],
    ['Sport', 'A sport owned by an association, e.g. Athletics, Badminton, Squash.'],
    ['Event', 'A competition within a sport with a gender, e.g. 100m Men.'],
    ['Phase', 'One of the four data collection steps of a game.'],
    ['Long List', 'Provisional list of athletes and officials (Phase 3).'],
    ['Short List', 'Final reduced list within the limits (Phase 4).'],
    ['CPR', 'Bahrain personal identity number.'],
    ['DFD', 'Data Flow Diagram.'],
    ['Presigned URL', 'A temporary link that gives time-limited access to a private file.'],
  ], [0.22, 0.78]),
];

const doc = new Document({
  creator: 'Solution Architect',
  title: 'Sports Entry Management System – Scope & Design',
  styles: {
    default: { document: { run: { font: FONT, size: 20 } } },
    paragraphStyles: [
      { id: 'Heading1', name: 'Heading 1', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { font: FONT, size: 32, bold: true, color: NAVY }, paragraph: { spacing: { before: 360, after: 160 }, keepNext: true, outlineLevel: 0 } },
      { id: 'Heading2', name: 'Heading 2', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { font: FONT, size: 25, bold: true, color: NAVY }, paragraph: { spacing: { before: 240, after: 120 }, keepNext: true, outlineLevel: 1 } },
      { id: 'Heading3', name: 'Heading 3', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { font: FONT, size: 22, bold: true, color: '334155' }, paragraph: { spacing: { before: 200, after: 100 }, keepNext: true, outlineLevel: 2 } },
    ],
  },
  numbering: { config: [
    { reference: 'bullets', levels: [{ level: 0, format: LevelFormat.BULLET, text: '•', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 400, hanging: 260 } } } }] },
    { reference: 'num', levels: [{ level: 0, format: LevelFormat.DECIMAL, text: '%1.', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 400, hanging: 300 } } } }] },
  ] },

  sections: [
    { properties: portrait, headers: { default: header }, footers: { default: footer }, children: [...cover, ...partA] },
    { properties: landscape, headers: { default: header }, footers: { default: footer }, children: partB },
    { properties: portrait, headers: { default: header }, footers: { default: footer }, children: partC },
  ],
});

Packer.toBuffer(doc).then(buf => { fs.writeFileSync(outFile, buf); console.log('wrote', outFile); });
