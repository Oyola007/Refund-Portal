(() => {
  const root = document.getElementById('chat');
  if (!root) return;

  const refund = root.dataset.refund || '', conv = root.dataset.conv || '';
  const csrf = root.dataset.csrf, api = root.dataset.api;
  const list = root.querySelector('.chat-list');
  const form = root.querySelector('.chat-form');
  const text = form.querySelector('textarea');
  const picker = form.querySelector('input[type=file]');
  const previews = form.querySelector('.chat-previews');
  const errorBox = form.querySelector('.chat-error');
  const noteBox = root.querySelector('.chat-note');
  const tabs = Array.from(root.querySelectorAll('.chat-tab'));
  const MAX_BYTES = 25 * 1024 * 1024, MAX_FILES = 5, POLL_MS = 3000;

  let kind = root.dataset.kind, gen = 0, lastId = 0, files = [], sending = false, fetching = false;

  const el = (tag, cls, txt) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (txt !== undefined) n.textContent = txt;
    return n;
  };
  const sizeLabel = b => b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
  const showError = m => { errorBox.textContent = m || ''; errorBox.hidden = !m; };
  const showEmpty = () => list.replaceChildren(el('p', 'chat-empty', 'No messages yet. Send a message, photo, video or document.'));
  // A direct chat is addressed by conversation id, a refund chat by refund id plus tab.
  const scope = () => conv ? `conv=${encodeURIComponent(conv)}` : `refund_id=${encodeURIComponent(refund)}&kind=${encodeURIComponent(kind)}`;

  function attachment(f) {
    const src = `${api}/file.php?id=${f.id}`;
    if (f.type === 'image') {
      const a = el('a', 'att-img'); a.href = src; a.target = '_blank'; a.rel = 'noopener';
      const img = el('img'); img.src = src; img.alt = f.name; img.loading = 'lazy';
      a.append(img); return a;
    }
    if (f.type === 'video') {
      const v = el('video', 'att-video'); v.src = src; v.controls = true; v.preload = 'metadata';
      return v;
    }
    const a = el('a', 'att-doc', `${f.name} (${sizeLabel(f.size)})`);
    a.href = src; a.target = '_blank'; a.rel = 'noopener';
    return a;
  }

  function render(m) {
    const row = el('div', 'msg ' + (m.mine ? 'mine' : 'theirs'));
    const who = m.mine ? 'You' : (m.role !== 'customer' ? `${m.name} (${m.role})` : m.name);
    const meta = el('div', 'msg-meta');
    meta.append(el('strong', null, who), el('span', null, m.time));
    row.append(meta);
    if (m.body) row.append(el('p', 'msg-body', m.body));
    m.files.forEach(f => row.append(attachment(f)));
    list.append(row);
  }

  function updateBadges(counts) {
    tabs.forEach(t => {
      const b = t.querySelector('.tab-badge');
      const n = t.dataset.kind === kind ? 0 : (counts[t.dataset.kind] || 0);
      b.textContent = n; b.hidden = !n;
    });
  }

  async function poll() {
    if (fetching) return;
    fetching = true;
    const myGen = gen;
    try {
      const r = await fetch(`${api}/chat_fetch.php?${scope()}&after=${lastId}`, { credentials: 'same-origin' });
      if (!r.ok) throw new Error('poll failed');
      const data = await r.json();
      if (myGen !== gen) return;               // the user switched tabs meanwhile
      if (data.messages.length) {
        const stick = lastId === 0 || list.scrollHeight - list.scrollTop - list.clientHeight < 80;
        const empty = list.querySelector('.chat-empty');
        if (empty) empty.remove();
        data.messages.forEach(m => { render(m); lastId = Math.max(lastId, m.id); });
        if (stick) list.scrollTop = list.scrollHeight;
      }
      updateBadges(data.counts || {});
    } catch (_) { /* try again on the next tick */ }
    finally { if (myGen === gen) fetching = false; }
  }

  function setNote(tab) {
    const canSend = tab.dataset.send === '1';
    noteBox.textContent = tab.dataset.note + (canSend ? '' : ' You can read this conversation but not send messages right now.');
    form.hidden = !canSend;
  }

  function selectThread(k) {
    const tab = tabs.find(t => t.dataset.kind === k);
    if (!tab) return;
    kind = k; gen++; lastId = 0; fetching = false;
    tabs.forEach(t => { const on = t === tab; t.classList.toggle('on', on); t.setAttribute('aria-selected', on ? 'true' : 'false'); });
    root.classList.toggle('internal', k === 'admin_finance');
    setNote(tab);
    files = []; drawPreviews(); text.value = ''; text.style.height = 'auto'; showError('');
    showEmpty();
    poll();
  }

  function drawPreviews() {
    previews.replaceChildren();
    files.forEach((f, i) => {
      const chip = el('div', 'chip');
      chip.append(el('span', null, `${f.name} (${sizeLabel(f.size)})`));
      const x = el('button', null, '\u00d7');
      x.type = 'button'; x.setAttribute('aria-label', 'Remove ' + f.name);
      x.addEventListener('click', () => { files.splice(i, 1); drawPreviews(); });
      chip.append(x); previews.append(chip);
    });
  }

  tabs.forEach(t => t.addEventListener('click', () => { if (t.dataset.kind !== kind) selectThread(t.dataset.kind); }));

  picker.addEventListener('change', () => {
    showError('');
    for (const f of picker.files) {
      if (files.length >= MAX_FILES) { showError(`You can attach up to ${MAX_FILES} files per message.`); break; }
      if (f.size > MAX_BYTES) { showError(`${f.name} is larger than 25 MB.`); continue; }
      files.push(f);
    }
    picker.value = '';
    drawPreviews();
  });

  text.addEventListener('input', () => { text.style.height = 'auto'; text.style.height = Math.min(text.scrollHeight, 140) + 'px'; });
  text.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
  });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    if (sending) return;
    const body = text.value.trim();
    if (!body && !files.length) return;
    sending = true; form.classList.add('busy'); showError('');

    const fd = new FormData();
    if (conv) { fd.append('conv', conv); } else { fd.append('refund_id', refund); fd.append('kind', kind); }
    fd.append('body', body);
    files.forEach(f => fd.append('files[]', f));
    try {
      const r = await fetch(`${api}/chat_send.php`, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf }, credentials: 'same-origin' });
      const data = await r.json().catch(() => ({}));
      if (!r.ok || !data.ok) throw new Error(data.error || 'Message not sent. The files may be too large.');
      text.value = ''; text.style.height = 'auto'; files = []; drawPreviews();
      await poll();
      list.scrollTop = list.scrollHeight;
    } catch (err) {
      showError(err.message);
    } finally {
      sending = false; form.classList.remove('busy'); text.focus();
    }
  });

  if (tabs.length) {
    const first = tabs.find(t => t.dataset.kind === kind) || tabs[0];
    kind = first.dataset.kind; setNote(first);
  } else {
    noteBox.textContent = root.dataset.note || '';
  }
  poll();
  setInterval(() => { if (!document.hidden) poll(); }, POLL_MS);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
})();
