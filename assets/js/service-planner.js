(() => {
  'use strict';
  document.addEventListener('click', event => {
    const link = event.target.closest('a[data-guide-popup], a[data-checklist-popup]');
    if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const checklist = link.hasAttribute('data-checklist-popup');
    const guide = window.open(link.href, checklist ? 'liberty-service-checklist' : 'liberty-service-guide', checklist ? 'popup,width=640,height=820,resizable=yes,scrollbars=yes' : 'popup,width=740,height=820,resizable=yes,scrollbars=yes');
    if (guide) { event.preventDefault(); guide.focus(); }
  });
  const root = document.getElementById('service-planner');
  if (!root || !Number(root.dataset.serviceId)) return;
  root.querySelectorAll('.station-checklist .process-group').forEach(group => {
    group.addEventListener('toggle', () => {
      if (group.open) group.closest('.station-checklist').querySelectorAll('.process-group').forEach(other => { if (other !== group) other.open = false; });
    });
  });
  if (root.dataset.popout === '1') {
    const stations = [...root.querySelectorAll('[data-station]')];
    function showStation(key) {
      stations.forEach(station => { station.hidden = station.dataset.station !== key; });
      root.querySelectorAll('[data-station-switch]').forEach(link => { link.setAttribute('aria-current', link.dataset.stationSwitch === key ? 'page' : 'false'); });
    }
    root.querySelectorAll('[data-station-switch]').forEach(link => link.addEventListener('click', event => { event.preventDefault(); showStation(link.dataset.stationSwitch); }));
    if (stations.length > 1) showStation(stations[0].dataset.station);
  }
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
      input.closest('.task-row')?.classList.toggle('is-complete', input.checked);
      if (data.archived) input.disabled = true;
    });
    root.querySelectorAll('[data-task-credit]').forEach(credit => {
      const state = data.completions[credit.dataset.taskCredit];
      credit.textContent = state ? `Updated by ${state.updated_by} · ${state.updated_at}` : '';
    });
    root.querySelectorAll('[data-progress-station]').forEach(progress => {
      const station = progress.dataset.progressStation;
      const done = Object.entries(data.completions).filter(([key, value]) => key.startsWith(station + '_') && Number(value.is_complete)).length;
      const total = Number(progress.dataset.progressTotal);
      progress.textContent = `${done} of ${total} tasks completed`;
    });
    root.querySelectorAll('[data-process-group]').forEach(group => {
      const inputs = [...group.querySelectorAll('[data-task-key]')];
      group.querySelector('[data-group-progress]').textContent = `${inputs.filter(input => input.checked).length} of ${inputs.length} tasks completed`;
    });
    root.querySelectorAll('[data-media-ready-count]').forEach(count => {
      const ready = ['foh','computer1','computer2','computer3','computer4'].filter(key => ['READY','COMPLETE'].includes(data.statuses[key])).length;
      count.textContent = `${ready} of 5 stations ready`;
    });
    const announcements = root.querySelector('[data-sheet-announcements]');
    if (announcements && data.announcements) {
      announcements.replaceChildren();
      if (!data.announcements.length) { const empty = document.createElement('p'); empty.textContent = 'No current or upcoming announcements.'; announcements.append(empty); }
      data.announcements.forEach(item => {
        const article = document.createElement('article'); article.className = 'sheet-announcement';
        const title = document.createElement('h4'); title.textContent = item.title;
        const dates = document.createElement('p'); dates.textContent = item.dates;
        const body = document.createElement('div'); body.className = 'reference'; body.textContent = item.body;
        article.append(title, dates, body); announcements.append(article);
      });
    }
    root.querySelectorAll('[data-sermon-field]').forEach(field => {
      field.textContent = data.sermon[field.dataset.sermonField] || 'Not entered.';
    });
    const songsList = root.querySelector('[data-sheet-songs]');
    if (songsList) {
      songsList.replaceChildren();
      data.worship.forEach(song => {
        const row = document.createElement('li');
        const title = document.createElement('strong'); title.textContent = song.title;
        const details = document.createElement('p');
        details.textContent = [`Key: ${song.key || 'Not entered'}`, `Lead: ${song.lead || 'Not entered'}`, song.additional ? `Additional: ${song.additional}` : ''].filter(Boolean).join(' · ');
        row.append(title, details);
        if (song.notes) { const notes = document.createElement('div'); notes.className = 'reference'; notes.textContent = song.notes; row.append(notes); }
        songsList.append(row);
      });
      root.querySelector('[data-empty-songs]').hidden = data.worship.length > 0;
    }
    const ready = root.querySelector('[data-sheet-readiness]');
    if (ready) ready.textContent = Object.values(data.statuses).every(status => ['READY', 'COMPLETE'].includes(status)) ? 'The service plan is ready.' : 'This service is still being prepared.';
    const title = root.querySelector('[data-feed-sermon]');
    if (title) title.textContent = data.sermon.title || 'Title, scriptures, media, and presentation instructions';
    const scripture = root.querySelector('[data-feed-scripture]');
    if (scripture) scripture.textContent = data.sermon.primary_scripture || 'Not entered';
    const worship = root.querySelector('[data-feed-worship]');
    if (worship) worship.textContent = `${data.worship.length} songs · keys · leaders · notes`;
    if (savedInformation || ['sheet', 'notes'].includes(root.dataset.section)) {
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
      message('Shared changes are up to date.');
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
      song.querySelector('.song-actions')?.setAttribute('aria-label', `Song ${index + 1} order controls`);
      song.querySelector('[data-song-action="up"]')?.setAttribute('aria-label', `Move song ${index + 1} up`);
      song.querySelector('[data-song-action="down"]')?.setAttribute('aria-label', `Move song ${index + 1} down`);
      song.querySelectorAll('[data-song-field]').forEach(input => { input.name = `songs[${index}][${input.dataset.songField}]`; });
      const up = song.querySelector('[data-song-action="up"]');
      const down = song.querySelector('[data-song-action="down"]');
      if (up) up.disabled = index === 0;
      if (down) down.disabled = index === songs.children.length - 1;
    });
  }
  const add = document.getElementById('add-song');
  if (songs && add) {
    add.addEventListener('click', () => {
      const template = document.getElementById('song-template');
      if (!template) return;
      const song = template.content.firstElementChild.cloneNode(true);
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
