(() => {
  'use strict';
  const root = document.getElementById('service-planner');
  if (!root || !Number(root.dataset.serviceId)) return;
  const endpoint = '/php/admin/service-planner/state.php';
  const sync = document.getElementById('sync-message');
  let pending = 0;
  let epoch = 0;
  let polling = false;
  let writeTail = Promise.resolve();
  function write(body) {
    const next = writeTail.then(() => request(body));
    writeTail = next.catch(() => {});
    return next;
  }
  const message = (text, error = false) => {
    if (!sync) return;
    sync.textContent = text;
    sync.classList.toggle('error', error);
  };
  async function request(body) {
    const response = await fetch(body ? endpoint : `${endpoint}?service_id=${root.dataset.serviceId}`, {
      method: body ? 'POST' : 'GET', body,
      credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
    });
    let data;
    try { data = await response.json(); } catch (_) {
      throw new Error(response.status === 419 ? 'Your security token expired. Copy unsaved text, then reload and sign in again.' : 'Unable to read the planner response. Check your connection and sign-in.');
    }
    if (!response.ok) throw new Error(data.error || 'Planner request failed.');
    return data;
  }
  function apply(data, savedInformation = false) {
    Object.entries(data.statuses).forEach(([key, status]) => {
      root.querySelectorAll('[data-status-key]').forEach(badge => {
        if (badge.dataset.statusKey === key) { badge.textContent = status; badge.dataset.status = status; }
      });
    });
    root.querySelectorAll('[data-task-key]').forEach(input => {
      if (input.dataset.pending === "1" && !data.archived) return;
      const state = data.completions[input.dataset.taskKey];
      input.checked = Boolean(state && Number(state.is_complete));
      input.dataset.revision = String(state ? state.revision : 0);
      if (data.archived) input.disabled = true;
    });
    root.querySelectorAll('[data-task-credit]').forEach(credit => {
      const state = data.completions[credit.dataset.taskCredit];
      credit.textContent = state ? `Updated by ${state.updated_by} · ${state.updated_at}` : '';
    });
    const title = root.querySelector('[data-feed-sermon]');
    if (title) title.textContent = data.sermon.title || 'Title, scriptures, media, and presentation instructions';
    const scripture = root.querySelector('[data-feed-scripture]');
    if (scripture) scripture.textContent = data.sermon.primary_scripture || 'Not entered';
    const worship = root.querySelector('[data-feed-worship]');
    if (worship) worship.textContent = `${data.worship.length} songs · keys · leaders · notes`;
    if (savedInformation) {
      root.dataset.revision = String(data.revision);
      root.querySelectorAll('input[name="revision"]').forEach(input => { input.value = data.revision; });
    } else if (Number(root.dataset.revision) !== data.revision) {
      document.getElementById('information-update').hidden = false;
    }
    if (data.archived && root.dataset.archived !== '1') {
      root.querySelectorAll('form input, form textarea, form button').forEach(input => { input.disabled = true; });
      root.dataset.archived = '1';
      message('This Sunday was archived on another device. Reload to view its preserved record.');
    }
  }
  async function refresh() {
    if (pending || polling || document.hidden) return;
    polling = true;
    const started = epoch;
    try {
      const data = await request();
      if (pending || started !== epoch) return;
      apply(data);
      message(`Up to date · checked ${new Date().toLocaleTimeString()}`);
    } catch (error) { message(`${error.message} Updates will retry.`, true); }
    finally { polling = false; }
  }
  root.querySelectorAll('[data-task-key]').forEach(input => {
    input.addEventListener('change', async () => {
      const checked = input.checked;
      const body = new FormData();
      body.set('action', 'task'); body.set('service_id', root.dataset.serviceId);
      body.set('task_key', input.dataset.taskKey); body.set('revision', input.dataset.revision);
      body.set('checked', checked ? '1' : '0'); body.set('csrf_token', document.getElementById('planner-csrf').value);
      input.dataset.pending = '1'; input.disabled = true; pending++; epoch++; message('Saving checklist…');
      try { const data = await write(body); delete input.dataset.pending; apply(data); message('Checklist saved for the team.'); }
      catch (error) { input.checked = !checked; message(`${error.message} This check was not saved.`, true); }
      finally { delete input.dataset.pending; pending--; input.disabled = root.dataset.archived === '1'; }
    });
  });
  root.querySelectorAll('[data-planner-form]').forEach(form => {
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (!form.reportValidity()) return;
      const body = new FormData(form);
      const button = form.querySelector('button[type="submit"]');
      const feedback = form.querySelector('.save-message');
      button.disabled = true; pending++; epoch++; feedback.textContent = 'Saving…'; feedback.classList.remove('error');
      try {
        const data = await write(body);
        if (['archive', 'instructions'].includes(body.get('action'))) { window.location.reload(); return; }
        apply(data, true); feedback.textContent = 'Saved for the team.'; message('Shared Sunday information saved.');
      } catch (error) { feedback.textContent = error.message; feedback.classList.add('error'); }
      finally { pending--; button.disabled = false; }
    });
  });
  const songs = document.getElementById('songs');
  function renumber() {
    if (!songs) return;
    [...songs.children].forEach((song, index) => {
      song.querySelector('.song-number').textContent = String(index + 1);
      song.querySelectorAll('[data-song-field]').forEach(input => { input.name = `songs[${index}][${input.dataset.songField}]`; });
      song.querySelector('[data-song-action="up"]').disabled = index === 0;
      song.querySelector('[data-song-action="down"]').disabled = index === songs.children.length - 1;
    });
  }
  const add = document.getElementById('add-song');
  if (songs && add) {
    add.addEventListener('click', () => {
      const song = document.createElement('div'); song.className = 'song';
      const head = document.createElement('div'); head.className = 'song-head';
      head.innerHTML = '<h4>Song <span class="song-number"></span></h4><div class="actions"><button type="button" data-song-action="up" aria-label="Move song up">↑ Up</button><button type="button" data-song-action="down" aria-label="Move song down">↓ Down</button><button type="button" data-song-action="remove">Remove</button></div>';
      song.append(head);
      Object.entries({title: 'Song title', key: 'Key', lead: 'Lead vocalist', additional: 'Additional vocalists', notes: 'Special notes / instruments'}).forEach(([key, text]) => {
        const label = document.createElement('label'); label.textContent = text;
        const input = document.createElement('input'); input.dataset.songField = key;
        input.maxLength = 12000; input.required = key === 'title'; label.append(input); song.append(label);
      });
      songs.append(song); renumber(); song.querySelector('input').focus();
    });
    songs.addEventListener('click', event => {
      const button = event.target.closest('[data-song-action]'); if (!button) return;
      const song = button.closest('.song');
      if (button.dataset.songAction === 'remove') song.remove();
      if (button.dataset.songAction === 'up' && song.previousElementSibling) songs.insertBefore(song, song.previousElementSibling);
      if (button.dataset.songAction === 'down' && song.nextElementSibling) songs.insertBefore(song.nextElementSibling, song);
      renumber();
    });
    renumber();
  }
  setInterval(refresh, 8000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  refresh();
})();
