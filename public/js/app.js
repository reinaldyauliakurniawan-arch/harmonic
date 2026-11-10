// Alpine components for the CSP build: markup references them by name only.
document.addEventListener('alpine:init', () => {
  const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
  const send = (url, method, body) => fetch(url, {
    method,
    credentials: 'same-origin',
    headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  window.hsSend = send;

  const cookie = (name, value) => { document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=31536000; SameSite=Lax`; };
  const readCookie = (name) => decodeURIComponent((document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`)) || [])[1] || '');

  Alpine.data('shell', () => ({
    menu: false,
    rail: document.body.classList.contains('nav-rail'),
    get expanded() { return !this.rail; },
    sideGroups() { return Array.from(document.querySelectorAll('aside .nav-group')); },
    // Collapsed to icons: every group shows its icons; expanding restores the groups the person had opened.
    toggleRail() {
      this.rail = !this.rail;
      document.body.classList.add('rail-anim');
      setTimeout(() => document.body.classList.remove('rail-anim'), 200);
      document.body.classList.toggle('nav-rail', this.rail);
      cookie('nav_rail', this.rail ? '1' : '0');
      const opened = readCookie('nav_open').split(',');
      this.sideGroups().forEach((d) => { d.open = this.rail || opened.includes(d.dataset.navGroup) || !!d.querySelector('[aria-current="page"]'); });
      document.querySelectorAll('aside .nav-item, aside .nav-top').forEach((a) => { if (this.rail) a.title = a.dataset.label; else a.removeAttribute('title'); });
    },
    group(e) {
      const d = e.target;
      if (this.rail && d.closest('aside')) return;
      const opened = new Set(readCookie('nav_open').split(',').filter(Boolean));
      if (d.open) opened.add(d.dataset.navGroup); else opened.delete(d.dataset.navGroup);
      cookie('nav_open', Array.from(opened).join(','));
    },
    openMenu() { this.menu = true; this.$nextTick(() => this.$refs.menuClose.focus()); },
    closeMenu() { if (!this.menu) return; this.menu = false; this.$refs.menuButton.focus(); },
  }));

  Alpine.data('toast', () => ({
    init() { setTimeout(() => this.$root.remove(), 3000); },
  }));

  // Reorderable list: children with data-sort-id, drag and drop plus arrow buttons for keyboard and touch.
  Alpine.data('sortable', () => ({
    dragged: null,
    items() { return Array.from(this.$root.querySelectorAll(':scope > [data-sort-id]')); },
    // The handle is what you drag; the whole row moves.
    start(e) { this.dragged = e.currentTarget.closest('[data-sort-id]'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setDragImage(this.dragged, 12, 12); this.dragged.classList.add('dragging'); },
    end() { if (this.dragged) this.dragged.classList.remove('dragging'); this.dragged = null; },
    over(e) {
      const target = e.currentTarget;
      if (!this.dragged || target === this.dragged) return;
      const after = e.clientY > target.getBoundingClientRect().top + target.offsetHeight / 2;
      target.parentNode.insertBefore(this.dragged, after ? target.nextSibling : target);
    },
    drop() { this.save(); },
    move(e, dir) {
      const row = e.currentTarget.closest('[data-sort-id]');
      const sib = dir < 0 ? row.previousElementSibling : row.nextElementSibling;
      if (!sib || !sib.dataset.sortId) return;
      if (dir < 0) row.parentNode.insertBefore(row, sib); else row.parentNode.insertBefore(sib, row);
      e.currentTarget.focus();
      this.save();
    },
    up(e) { this.move(e, -1); },
    down(e) { this.move(e, 1); },
    async save() {
      let node = this.$root;
      let status = null;
      while (node && !(status = node.querySelector('[data-sort-status]'))) node = node.parentElement;
      const say = (m) => { if (status) status.textContent = m; };
      try {
        const res = await send(this.$root.dataset.url, 'POST', { ids: this.items().map((i) => i.dataset.sortId) });
        say(res.ok ? 'Order saved' : "Couldn't save the order. Reload and try again.");
      } catch (err) { say("Couldn't save the order. Check your connection and try again."); }
    },
  }));

  Alpine.data('printPage', () => ({ print() { window.print(); } }));

  // Filter rows apply as soon as a value changes; the Apply button stays for no-JS use.
  Alpine.data('autoSubmit', () => ({
    init() { const b = this.$root.querySelector('[data-apply]'); if (b) b.classList.add('sr-only'); },
    submit() { this.$root.requestSubmit(); },
  }));

  Alpine.data('confirmForm', () => ({
    ask(e) { if (!window.confirm(this.$root.dataset.message)) e.preventDefault(); },
  }));

  Alpine.data('seatSearch', () => ({
    filter(e) {
      const q = e.target.value.trim().toLowerCase();
      document.querySelectorAll('.seat-card').forEach((c) => {
        const hit = q && c.dataset.search.includes(q);
        c.classList.toggle('seat-hit', !!hit);
        c.classList.toggle('seat-dim', !!q && !hit);
      });
    },
  }));

  // Native HTML5 drag and drop; every card also has a "Move to" form for keyboard and touch.
  Alpine.data('board', () => ({
    taskId: null,
    start(e) { this.taskId = e.currentTarget.dataset.task; e.currentTarget.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; },
    end(e) { e.currentTarget.classList.remove('dragging'); },
    over(e) { e.currentTarget.classList.add('drop-target'); },
    leave(e) { e.currentTarget.classList.remove('drop-target'); },
    async drop(e) {
      const col = e.currentTarget;
      col.classList.remove('drop-target');
      if (!this.taskId) return;
      const res = await send(this.$root.dataset.url.replace('__TASK__', this.taskId), 'POST', { column_id: col.dataset.column });
      if (res.ok) window.location.reload();
      else this.$root.querySelector('[data-board-status]').textContent = res.status === 403 ? "You can't move this card. Only its Responsible, Accountable, or the team leader can." : "Couldn't move the card. Check your connection and try again.";
    },
  }));

  // Metrics grid inline editing (PRD M-03): Enter saves and moves down, Tab right, Escape cancels.
  Alpine.data('metricGrid', () => ({
    noteTd: null,
    edit(e) {
      const td = e.target.closest('td[data-week]');
      if (td && e.target.closest('[data-note]')) { e.preventDefault(); this.openNote(td); return; }
      if (!td || td.dataset.editable !== '1' || td.querySelector('input')) return;
      e.preventDefault();
      this.open(td);
    },
    open(td) {
      const display = td.querySelector('[data-display]');
      const input = document.createElement('input');
      input.className = 'cell-input';
      input.inputMode = 'decimal';
      input.size = 1;
      input.value = td.dataset.raw || '';
      input.setAttribute('aria-label', td.dataset.label);
      if (display) display.hidden = true;
      td.appendChild(input);
      input.focus();
      input.select();
      input.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape') { ev.preventDefault(); this.close(td, input); td.focus(); }
        else if (ev.key === 'Enter') { ev.preventDefault(); ev.stopPropagation(); this.commit(td, input, 'down'); }
        else if (ev.key === 'Tab') { ev.preventDefault(); this.commit(td, input, ev.shiftKey ? 'left' : 'right'); }
      });
      input.addEventListener('blur', () => { if (!input.dataset.done) this.commit(td, input, null); });
    },
    close(td, input) {
      input.dataset.done = '1';
      input.remove();
      const display = td.querySelector('[data-display]');
      if (display) display.hidden = false;
    },
    status(msg) { const s = document.getElementById('grid-status'); if (s) s.textContent = msg; },
    // Notes live in a small dialog opened from the cell (PRD M-03).
    openNote(td) {
      this.noteTd = td;
      const d = this.$root.querySelector('[data-note-dialog]');
      d.querySelector('textarea').value = td.querySelector('[data-note]').dataset.note || '';
      d.showModal();
    },
    closeNote() { this.$root.querySelector('[data-note-dialog]').close(); if (this.noteTd) this.noteTd.focus(); },
    async saveNote() {
      const td = this.noteTd;
      const note = this.$root.querySelector('[data-note-dialog] textarea').value;
      try {
        const res = await send(td.closest('tr').dataset.url, 'POST', { week_start: td.dataset.week, note, note_only: true });
        const j = await res.json();
        if (!res.ok) { this.status((j.errors && Object.values(j.errors)[0][0]) || "Couldn't save the note."); return; }
        td.innerHTML = j.html;
        this.status('Note saved');
        this.closeNote();
      } catch (err) { this.status("Couldn't save the note. Check your connection and try again."); }
    },
    async commit(td, input, dir) {
      input.dataset.done = '1';
      if (input.value.trim() === (td.dataset.raw || '')) { this.close(td, input); this.move(td, dir); return; }
      // Save in the background and move on at once, so typing ahead never lands in the cell being saved.
      input.readOnly = true;
      this.status('Saving');
      this.move(td, dir);
      const fail = (msg) => {
        input.readOnly = false;
        input.dataset.done = '';
        input.setAttribute('aria-invalid', 'true');
        this.status(msg);
        input.focus();
      };
      try {
        const res = await send(td.closest('tr').dataset.url, 'POST', { week_start: td.dataset.week, value: input.value });
        const j = await res.json();
        if (!res.ok) { fail((j.errors && Object.values(j.errors)[0][0]) || "Couldn't save. Check your connection and try again."); return; }
        td.dataset.raw = j.raw;
        td.innerHTML = j.html;
        td.classList.toggle('cell-missing', j.status === 'missing');
        this.status('Saved');
      } catch (err) {
        fail("Couldn't save. Check your connection and try again.");
      }
    },
    move(td, dir) {
      if (!dir) return;
      let next = null;
      if (dir === 'right') next = td.nextElementSibling;
      else if (dir === 'left') next = td.previousElementSibling;
      else {
        const idx = Array.from(td.parentElement.children).indexOf(td);
        const rows = Array.from(this.$root.querySelectorAll('tr[data-url]'));
        const row = rows[rows.indexOf(td.parentElement) + 1];
        next = row ? row.children[idx] : null;
      }
      if (next && next.dataset.week) {
        if (next.dataset.editable === '1') this.open(next); else next.focus();
      } else td.focus();
    },
  }));

  Alpine.data('goalPreview', () => ({
    preview: '',
    init() { this.update(); },
    update() {
      const f = this.$root;
      const sym = { gte: '≥', lte: '≤', eq: '=', gt: '>', lt: '<' };
      const op = f.querySelector('[name=operator]').value;
      const g = f.querySelector('[name=goal_value]').value;
      const m = f.querySelector('[name=goal_value_max]').value;
      if (g === '') { this.preview = ''; return; }
      this.preview = op === 'between' ? `On track when ${g}–${m || '?'}` : `On track when ${sym[op]} ${g}`;
    },
  }));

  // Live Weekly Meeting: countdown from the shared segment start, and polling every 15 s while visible.
  Alpine.data('meetingLive', () => ({
    clock: '',
    over: false,
    overClass: '',
    init() {
      const d = this.$root.dataset;
      const offset = Number(d.now) * 1000 - Date.now();
      const end = (Number(d.started) + Number(d.minutes) * 60) * 1000;
      const tick = () => {
        const left = Math.round((end - (Date.now() + offset)) / 1000);
        const a = Math.abs(left);
        this.over = left < 0;
        this.overClass = this.over ? 'cell-off' : '';
        const time = a >= 3600 ? Math.floor(a / 3600) + ' h ' + Math.floor((a % 3600) / 60) + ' m' : Math.floor(a / 60) + ':' + String(a % 60).padStart(2, '0');
        this.clock = (left < 0 ? '+' : '') + time;
      };
      tick();
      setInterval(tick, 1000);
      let version = null;
      const poll = async () => {
        if (document.hidden) return;
        try {
          const res = await fetch(d.state, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
          if (!res.ok) return;
          const j = await res.json();
          if (version === null) version = j.version;
          if (j.ended || String(j.segment) !== d.segment || j.version !== version) window.location.reload();
        } catch (err) { /* offline: try again on the next tick */ }
      };
      poll();
      setInterval(poll, 15000);
    },
  }));

  // Fundamentals draft: the whole form saves when a field loses focus.
  Alpine.data('autosaveForm', () => ({
    saved: false,
    failed: false,
    async save(e) {
      if (!e.target.name || e.target.type === 'submit') return;
      try {
        const res = await fetch(this.$root.action, {
          method: 'POST', credentials: 'same-origin', body: new FormData(this.$root),
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        this.failed = !res.ok;
        this.saved = res.ok;
      } catch (err) { this.failed = true; this.saved = false; }
      if (this.saved) setTimeout(() => { this.saved = false; }, 2000);
    },
  }));

  // Procedure body: server-rendered Markdown preview and word count.
  Alpine.data('mdPreview', () => ({
    words: 0,
    long: false,
    init() { this.count(this.$root.querySelector('textarea').value); },
    count(text) { this.words = (text.trim().match(/\S+/g) || []).length; this.long = this.words > 1200; },
    async render(e) {
      this.count(e.target.value);
      try {
        const res = await send(this.$root.dataset.url, 'POST', { body: e.target.value });
        if (res.ok) this.$refs.out.innerHTML = (await res.json()).html;
      } catch (err) { /* keep the last preview */ }
    },
  }));

  // Personnel Evaluator: the result shows live before saving (PRD E-01).
  Alpine.data('evaluatorResult', () => ({
    label: '',
    init() { this.update(); },
    update() {
      const f = this.$root;
      const scores = Array.from(f.querySelectorAll('[data-score]:checked')).map((i) => i.value);
      const total = new Set(Array.from(f.querySelectorAll('[data-score]')).map((i) => i.name)).size;
      const fits = Array.from(f.querySelectorAll('[data-fit]:checked'));
      if (scores.length < total || fits.length < 3) { this.label = ''; return; }
      const count = (v) => scores.filter((s) => s === v).length;
      const person = count('minus') <= Number(f.dataset.minus) && count('mixed') <= Number(f.dataset.mixed);
      const seat = fits.every((i) => i.value === '1');
      this.label = 'Result: ' + (person ? 'Right person' : 'Wrong person') + ', ' + (seat ? 'right seat' : 'wrong seat');
    },
  }));

  // 360 matrix: "Tick row" and "Tick column" shortcuts.
  Alpine.data('tickMatrix', () => ({
    toggle(boxes) { const on = boxes.some((b) => !b.checked); boxes.forEach((b) => { b.checked = on; }); },
    row(e) { this.toggle(Array.from(this.$root.querySelectorAll(`[data-r="${e.currentTarget.dataset.row}"]`))); },
    col(e) { this.toggle(Array.from(this.$root.querySelectorAll(`[data-c="${e.currentTarget.dataset.col}"]`))); },
  }));

  Alpine.data('autosave', () => ({
    saved: false,
    failed: false,
    async save(e) {
      const body = {};
      body[this.$root.dataset.field] = e.target.value;
      try {
        const res = await send(this.$root.dataset.url, 'PATCH', body);
        this.failed = !res.ok;
        this.saved = res.ok;
      } catch (err) { this.failed = true; this.saved = false; }
      if (this.saved) setTimeout(() => { this.saved = false; }, 2000);
    },
  }));
});

// A page transition aborted by a reload or resize only skips the cross-fade; it is not an error.
['pageswap', 'pagereveal'].forEach((type) => window.addEventListener(type, (e) => {
  if (e.viewTransition) { e.viewTransition.ready.catch(() => {}); e.viewTransition.finished.catch(() => {}); }
}));

document.addEventListener('DOMContentLoaded', () => {
  const bad = document.querySelector('[aria-invalid="true"]');
  if (bad) bad.focus();
});
