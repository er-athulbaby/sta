// Generates PNG diagrams for the scope document. Usage: node diagrams.js <outDir>
const fs = require('fs');
const path = require('path');
const sharp = require('sharp');

const C = {
  navy: '#1F3A5F', teal: '#0F766E', tealBg: '#E6F4F1', gray: '#475569', grayBg: '#F1F5F9',
  line: '#334155', amber: '#B45309', amberBg: '#FEF3C7', white: '#FFFFFF', blueBg: '#E8EEF6',
};
const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

function Diagram(w, h) {
  const nodes = {}; const back = []; const front = []; const labels = [];
  const center = n => n.kind === 'circle' ? [n.cx, n.cy] : [n.x + n.w / 2, n.y + n.h / 2];
  function clip(n, toward) {
    const [cx, cy] = center(n); const dx = toward[0] - cx, dy = toward[1] - cy;
    if (n.kind === 'circle') { const L = Math.hypot(dx, dy); return [cx + dx / L * n.r, cy + dy / L * n.r]; }
    const t = Math.min(dx ? (n.w / 2) / Math.abs(dx) : Infinity, dy ? (n.h / 2) / Math.abs(dy) : Infinity);
    return [cx + dx * t, cy + dy * t];
  }
  function text(x, y, lines, o = {}) {
    const size = o.size || 15, lh = size * 1.25, fill = o.fill || C.line, weight = o.bold ? 700 : 400;
    const anchor = o.anchor || 'middle';
    const y0 = y - ((lines.length - 1) * lh) / 2 + size * 0.35;
    return lines.map((l, i) => `<text x="${x}" y="${y0 + i * lh}" font-family="Arial, Helvetica, sans-serif" font-size="${size}" font-weight="${weight}" fill="${fill}" text-anchor="${anchor}">${esc(l)}</text>`).join('');
  }
  const api = {
    node(id, kind, x, y, w2, h2, lines, o = {}) {
      const n = { id, kind, x, y, w: w2, h: h2, ...o };
      if (kind === 'circle') { n.cx = x; n.cy = y; n.r = w2; }
      nodes[id] = n;
      const L = Array.isArray(lines) ? lines : [lines];
      let s = '';
      if (kind === 'entity') {
        s += `<rect x="${x}" y="${y}" width="${w2}" height="${h2}" fill="${C.grayBg}" stroke="${C.gray}" stroke-width="2"/>`;
        s += text(x + w2 / 2, y + h2 / 2, L, { bold: true, fill: C.gray });
      } else if (kind === 'process') {
        s += `<rect x="${x}" y="${y}" width="${w2}" height="${h2}" rx="14" fill="${C.navy}"/>`;
        if (o.num) s += `<line x1="${x}" y1="${y + 28}" x2="${x + w2}" y2="${y + 28}" stroke="#ffffff55"/>` + text(x + w2 / 2, y + 15, [o.num], { size: 13, fill: '#CBD5E1', bold: true });
        s += text(x + w2 / 2, y + (o.num ? (h2 + 28) / 2 : h2 / 2), L, { bold: true, fill: C.white });
      } else if (kind === 'store') {
        s += `<rect x="${x}" y="${y}" width="${w2}" height="${h2}" fill="${C.tealBg}" stroke="none"/>`;
        s += `<line x1="${x}" y1="${y}" x2="${x + w2}" y2="${y}" stroke="${C.teal}" stroke-width="2"/><line x1="${x}" y1="${y + h2}" x2="${x + w2}" y2="${y + h2}" stroke="${C.teal}" stroke-width="2"/>`;
        s += `<line x1="${x}" y1="${y}" x2="${x}" y2="${y + h2}" stroke="${C.teal}" stroke-width="2"/><line x1="${x + 46}" y1="${y}" x2="${x + 46}" y2="${y + h2}" stroke="${C.teal}" stroke-width="2"/>`;
        s += text(x + 23, y + h2 / 2, [o.num || ''], { bold: true, fill: C.teal, size: 14 });
        s += text(x + 46 + (w2 - 46) / 2, y + h2 / 2, L, { fill: C.teal, bold: true, size: 14 });
      } else if (kind === 'circle') {
        s += `<circle cx="${x}" cy="${y}" r="${w2}" fill="${C.navy}"/>`;
        s += text(x, y, L, { bold: true, fill: C.white, size: 22 });
      } else if (kind === 'state') {
        const fill = o.fill || C.blueBg, stroke = o.stroke || C.navy;
        s += `<rect x="${x}" y="${y}" width="${w2}" height="${h2}" rx="12" fill="${fill}" stroke="${stroke}" stroke-width="2" ${o.dash ? 'stroke-dasharray="8 5"' : ''}/>`;
        s += text(x + w2 / 2, y + h2 / 2, L, { bold: true, fill: stroke, size: o.size || 15 });
      } else if (kind === 'group') {
        s += `<rect x="${x}" y="${y}" width="${w2}" height="${h2}" rx="10" fill="${o.fill || 'none'}" stroke="${o.stroke || C.gray}" stroke-width="2" stroke-dasharray="${o.solid ? '' : '10 6'}"/>`;
        s += text(x + 16, y + 22, L, { bold: true, fill: o.stroke || C.gray, anchor: 'start', size: 16 });
      } else if (kind === 'table') {
        const [title, ...fields] = L;
        s += `<rect x="${x}" y="${y}" width="${w2}" height="${h2}" fill="#fff" stroke="${C.navy}" stroke-width="2"/>`;
        s += `<rect x="${x}" y="${y}" width="${w2}" height="30" fill="${C.navy}"/>`;
        s += text(x + w2 / 2, y + 15, [title], { bold: true, fill: C.white, size: 15 });
        fields.forEach((f, i) => { s += text(x + 10, y + 46 + i * 20, [f], { anchor: 'start', size: 13 }); });
      }
      (kind === 'group' ? back : front).push(s);
      return api;
    },
    edge(a, b, label, o = {}) {
      const A = nodes[a], B = nodes[b]; const via = o.via || [];
      let ca = center(A), cb = center(B);
      if (o.off && !via.length) {
        const dx = cb[0] - ca[0], dy = cb[1] - ca[1], L = Math.hypot(dx, dy); const px = -dy / L * o.off, py = dx / L * o.off;
        ca = [ca[0] + px, ca[1] + py]; cb = [cb[0] + px, cb[1] + py];
        const p1 = clipShifted(A, ca, cb), p2 = clipShifted(B, cb, ca); return draw([p1, p2]);
      }
      const p1 = clip(A, via[0] || cb), p2 = clip(B, via[via.length - 1] || ca);
      return draw([p1, ...via, p2]);
      function clipShifted(n, from, to) { // clip a shifted line against node n
        const dx = to[0] - from[0], dy = to[1] - from[1];
        if (n.kind === 'circle') { // ray-circle intersection
          const fx = from[0] - n.cx, fy = from[1] - n.cy; const aa = dx * dx + dy * dy, bb = 2 * (fx * dx + fy * dy), cc = fx * fx + fy * fy - n.r * n.r;
          const t = (-bb + Math.sqrt(bb * bb - 4 * aa * cc)) / (2 * aa); return [from[0] + dx * t, from[1] + dy * t];
        }
        const tx = dx > 0 ? (n.x + n.w - from[0]) / dx : dx < 0 ? (n.x - from[0]) / dx : Infinity;
        const ty = dy > 0 ? (n.y + n.h - from[1]) / dy : dy < 0 ? (n.y - from[1]) / dy : Infinity;
        const t = Math.min(tx, ty); return [from[0] + dx * t, from[1] + dy * t];
      }
      function draw(pts) {
        const both = o.both ? `marker-start="url(#arrS)"` : '';
        const col = o.color || C.line;
        back.push(`<polyline points="${pts.map(p => p.join(',')).join(' ')}" fill="none" stroke="${col}" stroke-width="${o.width || 2}" ${o.dash ? 'stroke-dasharray="7 5"' : ''} marker-end="url(#arr)" ${both}/>`);
        if (label) {
          // label at fraction t along the whole polyline
          const segs = []; let total = 0;
          for (let i = 0; i < pts.length - 1; i++) { const l = Math.hypot(pts[i + 1][0] - pts[i][0], pts[i + 1][1] - pts[i][1]); segs.push(l); total += l; }
          let d = total * (o.t ?? 0.5), i = 0; while (i < segs.length - 1 && d > segs[i]) { d -= segs[i]; i++; }
          const f = segs[i] ? d / segs[i] : 0;
          const lx = pts[i][0] + (pts[i + 1][0] - pts[i][0]) * f + (o.dx || 0), ly = pts[i][1] + (pts[i + 1][1] - pts[i][1]) * f + (o.dy || 0);
          const L = Array.isArray(label) ? label : [label]; const size = 13;
          const bw = Math.max(...L.map(s => s.length)) * size * 0.56 + 12, bh = L.length * size * 1.25 + 8;
          labels.push(`<rect x="${lx - bw / 2}" y="${ly - bh / 2}" width="${bw}" height="${bh}" rx="4" fill="#fff" stroke="#CBD5E1"/>` + text(lx, ly, L, { size, fill: C.line }));
        }
        return api;
      }
    },
    raw(s) { front.push(s); return api; },
    title(x, y, s, o = {}) { front.push(text(x, y, [s], { bold: true, size: o.size || 18, fill: o.fill || C.navy, anchor: 'start' })); return api; },
    svg() {
      return `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}">
<defs><marker id="arr" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="8" markerHeight="8" orient="auto-start-reverse"><path d="M0,0 L10,5 L0,10 z" fill="${C.line}"/></marker>
<marker id="arrS" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="8" markerHeight="8" orient="auto-start-reverse"><path d="M0,0 L10,5 L0,10 z" fill="${C.line}"/></marker></defs>
<rect width="100%" height="100%" fill="#fff"/>${back.join('')}${front.join('')}${labels.join('')}</svg>`;
    },
  };
  return api;
}

const out = process.argv[2] || '.';
const diagrams = {};

// ---------- Context diagram (DFD level 0)
diagrams['dfd0-context'] = Diagram(1400, 860)
  .node('sys', 'circle', 700, 430, 150, 0, ['0', 'Sports Entry', 'System'])
  .node('sa', 'entity', 60, 90, 220, 80, ['Super Admin'])
  .node('sta', 'entity', 60, 650, 220, 80, ['STA User'])
  .node('aa', 'entity', 1120, 90, 220, 80, ['Association Admin'])
  .node('ae', 'entity', 1120, 650, 220, 80, ['Association Editor'])
  .node('ses', 'entity', 590, 770, 220, 70, ['Email Service', '(Amazon SES)'])
  .node('s3', 'entity', 590, 20, 220, 70, ['File Storage', '(Amazon S3)'])
  .edge('sa', 'sys', ['Associations, sports, events,', 'games, limits, STA users'], { off: 16, t: 0.22 })
  .edge('sys', 'sa', ['Status dashboard,', 'reports, exports'], { off: 16, t: 0.22 })
  .edge('sta', 'sys', ['Edits, comments,', 'reminder requests'], { off: -16, t: 0.22 })
  .edge('sys', 'sta', ['Submissions, PDFs,', 'reports'], { off: -16, t: 0.22 })
  .edge('aa', 'sys', ['Profile, approvals,', 'editor invites'], { off: -16, t: 0.22 })
  .edge('sys', 'aa', ['Invitations, approval', 'requests, PDFs'], { off: -16, t: 0.22 })
  .edge('ae', 'sys', ['People, phase entries,', 'submissions'], { off: 16, t: 0.22 })
  .edge('sys', 'ae', ['Open phases, limits,', 'warnings, comments'], { off: 16, t: 0.22 })
  .edge('sys', 'ses', ['Invites, reminders, notifications'])
  .edge('sys', 's3', ['Photos, logos, PDFs'], { both: true });

// ---------- DFD 1a: setup & onboarding
diagrams['dfd1a-setup'] = Diagram(1500, 740)
  .node('e1', 'entity', 40, 330, 200, 80, ['Super Admin /', 'STA User'])
  .node('aa', 'entity', 1260, 200, 200, 80, ['Association Admin'])
  .node('ses', 'entity', 1260, 520, 200, 80, ['Email Service'])
  .node('p1', 'process', 400, 50, 240, 100, ['Manage Master Data'], { num: '1.0' })
  .node('p2', 'process', 860, 325, 240, 100, ['Invite & Onboard', 'Users'], { num: '2.0' })
  .node('p3', 'process', 400, 580, 240, 100, ['Set Up Game', '& Phases'], { num: '3.0' })
  .node('d1', 'store', 820, 90, 260, 54, ['Associations & Users'], { num: 'D1' })
  .node('d2', 'store', 390, 265, 260, 54, ['Sports & Events'], { num: 'D2' })
  .node('d3', 'store', 860, 610, 280, 54, ['Games, Phases & Limits'], { num: 'D3' })
  .edge('e1', 'p1', ['Association, sport,', 'event details'])
  .edge('p1', 'd1', ['Associations'])
  .edge('p1', 'd2', ['Sports & events'])
  .edge('e1', 'p2', ['Invite association admin'], { t: 0.45 })
  .edge('p2', 'd1', ['User accounts,', 'invitations'])
  .edge('p2', 'ses', ['Invitation emails'])
  .edge('ses', 'aa', ['Invitation link'], { dx: 0 })
  .edge('aa', 'p2', ['Accept invite, set password,', 'invite up to 2 editors'], { t: 0.5 })
  .edge('e1', 'p3', ['Game details, sports, event limits,', 'Phase 2 & 4 limits, phases, deadlines'], { t: 0.45, dx: -40 })
  .edge('d2', 'p3', ['Sports & events'])
  .edge('d1', 'p3', ['Invited associations'], { t: 0.62 })
  .edge('p3', 'd3', ['Game setup &', 'phase status']);

// ---------- DFD 1b: registry, phase entries, review
diagrams['dfd1b-submissions'] = Diagram(1500, 800)
  .node('ae', 'entity', 40, 110, 200, 80, ['Association Editor'])
  .node('aa', 'entity', 40, 580, 200, 80, ['Association Admin'])
  .node('sta', 'entity', 1260, 600, 200, 80, ['STA User'])
  .node('p4', 'process', 380, 40, 240, 100, ['Maintain People', 'Registry'], { num: '4.0' })
  .node('p5', 'process', 380, 330, 240, 100, ['Capture Phase', 'Entries'], { num: '5.0' })
  .node('p6', 'process', 820, 580, 240, 100, ['Approve & Review', 'Submissions'], { num: '6.0' })
  .node('d7', 'store', 860, 10, 240, 54, ['Files (S3)'], { num: 'D7' })
  .node('d4', 'store', 860, 130, 240, 54, ['People Registry'], { num: 'D4' })
  .node('d3', 'store', 860, 250, 280, 54, ['Games, Phases & Limits'], { num: 'D3' })
  .node('d5', 'store', 860, 400, 280, 54, ['Submissions & Entries'], { num: 'D5' })
  .node('d6', 'store', 1230, 400, 220, 54, ['Audit Log'], { num: 'D6' })
  .edge('ae', 'p4', ['Athletes & officials,', 'photos, Excel import'], { t: 0.45, dy: -30, dx: -20 })
  .edge('p4', 'd4', ['People records'], { t: 0.45 })
  .edge('p4', 'd7', ['Photos'], { t: 0.45 })
  .edge('ae', 'p5', ['Yes/No/Maybe, numbers,', 'event & official selections'], { off: 14, t: 0.5, dx: -105 })
  .edge('p5', 'ae', ['Errors & warnings'], { off: 14, t: 0.5, dx: 85 })
  .edge('d3', 'p5', ['Open phase, limits'], { t: 0.4 })
  .edge('d4', 'p5', ['People'], { t: 0.3 })
  .edge('p5', 'd5', ['Draft entries,', 'submit'], { t: 0.55 })
  .edge('d5', 'p6', ['Pending', 'submissions'], { off: 14, t: 0.5, dx: -60 })
  .edge('p6', 'd5', ['Status, edits,', 'comments'], { off: 14, t: 0.5, dx: 70 })
  .edge('aa', 'p6', ['Approve / return to draft'])
  .edge('sta', 'p6', ['Direct edits, comments'], { off: 14 })
  .edge('p6', 'sta', ['Submitted lists'], { off: 14 })
  .edge('p6', 'd6', ['Change history'], { t: 0.8 });

// ---------- DFD 1c: notifications & outputs
diagrams['dfd1c-outputs'] = Diagram(1500, 760)
  .node('d1', 'store', 60, 40, 260, 54, ['Associations & Users'], { num: 'D1' })
  .node('d3', 'store', 60, 190, 280, 54, ['Games, Phases & Limits'], { num: 'D3' })
  .node('d5', 'store', 60, 340, 280, 54, ['Submissions & Entries'], { num: 'D5' })
  .node('d4', 'store', 60, 520, 260, 54, ['People Registry'], { num: 'D4' })
  .node('p7', 'process', 580, 90, 260, 100, ['Send Notifications', '& Reminders'], { num: '7.0' })
  .node('p8', 'process', 580, 460, 260, 100, ['Generate PDFs', '& Reports'], { num: '8.0' })
  .node('d7', 'store', 590, 670, 240, 54, ['Files (S3)'], { num: 'D7' })
  .node('ses', 'entity', 1160, 100, 200, 80, ['Email Service'])
  .node('assoc', 'entity', 1160, 330, 200, 80, ['Association', 'Admin / Editor'])
  .node('sta', 'entity', 1160, 560, 200, 80, ['Super Admin /', 'STA User'])
  .edge('d1', 'p7', ['Contacts'])
  .edge('d3', 'p7', ['Deadlines,', 'phase status'])
  .edge('d5', 'p7', ['Who has not', 'submitted'], { t: 0.4 })
  .edge('sta', 'p7', ['"Remind pending"'], { t: 0.6 })
  .edge('p7', 'ses', ['Queued emails'])
  .edge('ses', 'assoc', ['Invites, reminders,', 'notifications'])
  .edge('ses', 'sta', ['Notifications'], { via: [[1420, 140], [1420, 600]], t: 0.5 })
  .edge('d5', 'p8', ['Long / Short lists,', 'justifications'], { t: 0.5 })
  .edge('d4', 'p8', ['Athlete & official', 'details'], { t: 0.45 })
  .edge('d3', 'p8', ['Limits'], { t: 0.75 })
  .edge('p8', 'd7', ['Generated PDFs'])
  .edge('p8', 'assoc', ['Long / Short List PDFs'], { t: 0.5 })
  .edge('p8', 'sta', ['Reports, Excel exports,', 'over-limit report'], { t: 0.5 });

// ---------- Phase sequence + submission status
diagrams['workflow'] = Diagram(1460, 500)
  .title(30, 22, 'A. Phase sequence for one game (each phase is opened and closed by the admin)')
  .node('gc', 'state', 30, 90, 190, 90, ['Game created', '(admin)'], { fill: C.grayBg, stroke: C.gray })
  .node('ph1', 'state', 270, 90, 190, 90, ['Phase 1', 'Entry by Sport', '(optional)'], { dash: true })
  .node('ph2', 'state', 510, 90, 190, 90, ['Phase 2', 'Entry by Number'])
  .node('ph3', 'state', 750, 90, 190, 90, ['Phase 3', 'Long List'])
  .node('ph4', 'state', 990, 90, 190, 90, ['Phase 4', 'Short List'])
  .node('rep', 'state', 1230, 90, 200, 90, ['Final lists', '& reports'], { fill: '#DCFCE7', stroke: '#166534' })
  .edge('gc', 'ph1', null).edge('ph1', 'ph2', null).edge('ph2', 'ph3', null).edge('ph3', 'ph4', null).edge('ph4', 'rep', null)
  .edge('gc', 'ph2', ['skip if Phase 1 disabled'], { via: [[125, 55], [605, 55]], dash: true, t: 0.5 })
  .title(30, 250, 'B. Submission status inside every open phase (one submission per association)')
  .node('dr', 'state', 30, 320, 200, 80, ['Draft'])
  .node('aw', 'state', 360, 320, 240, 80, ['Awaiting', 'Association Admin'], { fill: C.amberBg, stroke: C.amber })
  .node('su', 'state', 860, 320, 230, 80, ['Submitted to STA'], { fill: '#DCFCE7', stroke: '#166534' })
  .node('cl', 'state', 1220, 320, 210, 80, ['Phase closed', '(read-only)'], { fill: C.grayBg, stroke: C.gray })
  .edge('dr', 'aw', ['Editor submits'], { off: -12 })
  .edge('aw', 'dr', ['Admin returns'], { off: -12 })
  .edge('aw', 'su', ['Association admin approves'])
  .edge('dr', 'su', ['Association admin submits directly'], { via: [[130, 460], [975, 460]], t: 0.5 })
  .edge('su', 'su', ['STA edits / comments (audited)'], { via: [[920, 290], [920, 275], [1030, 275], [1030, 290]], t: 0.5 })
  .edge('su', 'cl', ['Admin closes /', 'deadline passes']);

// ---------- Deployment architecture
diagrams['architecture'] = Diagram(1400, 720)
  .node('br', 'entity', 30, 300, 220, 110, ['Web browsers', 'STA users &', '~50 associations'])
  .node('aws', 'group', 300, 30, 1080, 660, ['AWS region me-south-1 (Bahrain)'], { stroke: C.amber })
  .node('ec2', 'group', 350, 90, 500, 480, ['EC2 instance (Amazon Linux)'], { stroke: C.navy })
  .node('web', 'process', 380, 140, 440, 110, ['Nginx + PHP-FPM 8.5', 'Laravel + Filament', '/admin  and  /portal'])
  .node('q', 'process', 380, 300, 205, 100, ['Queue worker', '(Supervisor)'])
  .node('cron', 'process', 615, 300, 205, 100, ['Scheduler (cron)', 'daily reminders'])
  .node('chr', 'process', 380, 440, 440, 90, ['Headless Chrome', '(Browsershot PDF rendering)'])
  .node('rds', 'state', 940, 110, 380, 110, ['Amazon RDS MySQL 8', 'all application data', 'automated backups 35 days'], { fill: C.tealBg, stroke: C.teal })
  .node('s3', 'state', 940, 300, 380, 110, ['Amazon S3 (private bucket)', 'photos, logos, PDFs, imports', 'presigned URLs (5 min)'], { fill: C.tealBg, stroke: C.teal })
  .node('ses', 'state', 940, 480, 380, 100, ['Amazon SES', 'invites, reminders, notifications'], { fill: C.tealBg, stroke: C.teal })
  .edge('br', 'web', ['HTTPS'])
  .edge('web', 'rds', ['SQL (TLS)'])
  .edge('web', 's3', ['Signed uploads / URLs'], { t: 0.55 })
  .edge('cron', 'q', null)
  .edge('q', 'chr', ['PDF jobs'])
  .edge('chr', 's3', ['Store PDFs'], { t: 0.55 })
  .edge('q', 'ses', ['Send emails'], { via: [[365, 350], [365, 625], [1130, 625]], t: 0.6 })
  .edge('br', 's3', ['Download via presigned URL'], { via: [[140, 670], [1350, 670], [1350, 355]], t: 0.4, dash: true });

// ---------- Entity relationship diagram
const T = (x, y, title, fields) => ({ x, y, title, fields, h: 30 + fields.length * 20 + 12 });
const tables = {
  associations: T(30, 30, 'associations', ['id', 'name *', 'email *', 'logo, phone', 'address, website']),
  users: T(30, 290, 'users', ['id', 'association_id (null = STA)', 'name, email, password', 'role', 'is_active']),
  invitations: T(30, 560, 'invitations', ['id', 'association_id', 'email, role', 'token_hash', 'expires_at, accepted_at']),
  sports: T(330, 30, 'sports', ['id', 'association_id', 'name']),
  events: T(330, 250, 'events', ['id', 'sport_id', 'name', 'gender (men/women/mixed)']),
  people: T(330, 470, 'people', ['id', 'association_id', 'type (athlete/official)', 'first, second, third, last', 'photo_path', 'passport_no (enc), expiry', 'cpr_no (enc), expiry', 'dob, gender, nationality', 'height, weight', 'function (officials)']),
  games: T(630, 30, 'games', ['id', 'name, logo', 'start_date, end_date']),
  game_associations: T(630, 200, 'game_associations', ['game_id', 'association_id']),
  game_sports: T(630, 340, 'game_sports', ['id', 'game_id, sport_id', 'p2_max_men, p2_max_women', 'p4_max_men, p4_max_women', 'p4_max_officials']),
  game_events: T(630, 540, 'game_events', ['id', 'game_id, event_id', 'p2_max_athletes']),
  game_phases: T(930, 30, 'game_phases', ['id', 'game_id', 'phase (1-4), enabled', 'status (draft/open/closed)', 'opens_at, deadline']),
  submissions: T(930, 250, 'submissions', ['id', 'game_id, association_id', 'phase', 'status', 'submitted_by / at', 'approved_by / at', 'justification (Phase 4)', 'pdf_path']),
  comments: T(930, 560, 'comments', ['id', 'submission_id', 'user_id', 'body']),
  interest_answers: T(1230, 30, 'interest_answers (P1)', ['id', 'submission_id', 'game_sport_id', 'answer (yes/no/maybe)']),
  number_entries: T(1230, 230, 'number_entries (P2)', ['id', 'submission_id', 'game_event_id', 'count']),
  entries: T(1230, 430, 'entries (P3 / P4)', ['id', 'submission_id', 'person_id', 'game_event_id (athlete)', 'game_sport_id (official)']),
  activity_log: T(1230, 640, 'activity_log', ['id', 'user_id', 'subject (any table)', 'old / new values', 'created_at']),
};
const erd = Diagram(1500, 820);
for (const [id, t] of Object.entries(tables)) erd.node(id, 'table', t.x, t.y, 250, t.h, [t.title, ...t.fields]);
const g = { color: '#64748B', width: 1.6 };
erd.edge('users', 'associations', null, g)
  .edge('invitations', 'associations', null, { ...g, via: [[15, 640], [15, 90]] })
  .edge('sports', 'associations', null, g)
  .edge('events', 'sports', null, g)
  .edge('people', 'associations', null, { ...g, via: [[305, 600], [305, 140]] })
  .edge('game_associations', 'games', null, g)
  .edge('game_sports', 'games', null, { ...g, via: [[612, 440], [612, 100]] })
  .edge('game_events', 'events', null, { ...g, via: [[598, 600], [598, 330]] })
  .edge('game_phases', 'games', null, g)
  .edge('submissions', 'games', null, g)
  .edge('comments', 'submissions', null, g)
  .edge('interest_answers', 'submissions', null, g)
  .edge('number_entries', 'submissions', null, g)
  .edge('entries', 'submissions', null, g);
diagrams['erd'] = erd;

(async () => {
  fs.mkdirSync(out, { recursive: true });
  for (const [name, d] of Object.entries(diagrams)) {
    const svg = d.svg();
    fs.writeFileSync(path.join(out, name + '.svg'), svg);
    await sharp(Buffer.from(svg), { density: 144 }).png().toFile(path.join(out, name + '.png'));
    console.log('wrote', name);
  }
})();
