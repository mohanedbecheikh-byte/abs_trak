const WEEKS = 14;
const STATE = {
  modules: [],
  byId: new Map(),
  attendance: {},
  activeId: null,
  activeTab: 'detail'
};

const PILL = {
  COURS: 'pill-cours',
  TD: 'pill-td',
  TP: 'pill-tp',
  ENLIGNE: 'pill-enligne'
};

const tip = document.getElementById('tooltip');
const ICONS = {
  modules: '<path d="M3 6h8v12H3z"/><path d="M13 4h8v14h-8z"/><path d="M11 8h2"/><path d="M11 12h2"/>',
  present: '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.3 2.3L15.5 9.7"/>',
  absent: '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/>',
  ratio: '<path d="M3 17h18"/><path d="m6 14 4-4 3 3 5-6"/>',
  risk: '<path d="M12 3 2.7 19h18.6z"/><path d="M12 9v4"/><path d="M12 16h.01"/>',
  week: '<rect x="4" y="5" width="16" height="14" rx="2"/><path d="M8 3v4m8-4v4M4 10h16"/>'
};

function icon(name, extraClass = '') {
  const body = ICONS[name] || '';
  return `<svg class="ui-icon ${extraClass}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${body}</svg>`;
}

function isoToday() {
  return new Date().toISOString().slice(0, 10);
}

function isFuture(row) {
  return row.date_start > isoToday();
}

function isCurrentWeek(row) {
  const t = isoToday();
  return t >= row.date_start && t <= row.date_end;
}

function nextStatus(current) {
  if (current === 'unknown') return 'present';
  if (current === 'present') return 'absent';
  return 'unknown';
}

function getStats(moduleId) {
  const rows = STATE.attendance[moduleId] || [];
  const done = rows.filter((r) => !isFuture(r));
  const present = done.filter((r) => r.status === 'present').length;
  const absent = done.filter((r) => r.status === 'absent').length;
  const unknown = done.filter((r) => r.status === 'unknown').length;
  const recorded = present + absent;
  return { present, absent, unknown, recorded };
}

function ruleLevel(absent) {
  if (absent >= 5) return 'excluded';
  if (absent >= 3) return 'danger';
  return 'ok';
}

function ruleLabel(absent) {
  const level = ruleLevel(absent);
  if (level === 'excluded') return 'EXCLU';
  if (level === 'danger') return 'DANGER';
  return 'OK';
}

function riskWidth(absent) {
  return Math.min(100, Math.round((absent / 5) * 100));
}

function progColor(absent) {
  const level = ruleLevel(absent);
  if (level === 'ok') return 'linear-gradient(90deg, #22c55e, #16a34a)';
  if (level === 'danger') return 'linear-gradient(90deg, #f59e0b, #d97706)';
  return 'linear-gradient(90deg, #ef4444, #dc2626)';
}

function levelClass(absent) {
  const level = ruleLevel(absent);
  if (level === 'ok') return 'ok';
  if (level === 'danger') return 'warn';
  return 'bad';
}

function moduleGroups() {
  const groups = {};
  STATE.modules.forEach((m) => {
    const key = m.name;
    if (!groups[key]) groups[key] = [];
    groups[key].push(m);
  });
  return groups;
}

function buildSidebar() {
  const sb = document.getElementById('sidebar');
  sb.innerHTML = '';
  const groups = moduleGroups();
  Object.entries(groups).forEach(([name, mods]) => {
    const title = document.createElement('div');
    title.className = 'sb-section';
    title.textContent = name;
    sb.appendChild(title);

    mods.forEach((m) => {
      const stats = getStats(m.id);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = `mod-btn${m.id === STATE.activeId ? ' active' : ''}`;
      btn.innerHTML = `
        <div class="mod-type-pill ${PILL[m.type] || 'pill-cours'}">${m.type}</div>
        <div class="mod-right">
          <div class="mod-name">${m.name}</div>
          <div class="mod-mini-bar">
            <div class="mod-mini-fill" style="width:${riskWidth(stats.absent)}%;background:${progColor(stats.absent)}"></div>
          </div>
        </div>
      `;
      btn.addEventListener('click', () => {
        STATE.activeId = m.id;
        STATE.activeTab = 'detail';
        renderAll();
      });
      sb.appendChild(btn);
    });
  });
}

function buildStats() {
  let gPresent = 0;
  let gAbsent = 0;
  let gUnknown = 0;
  STATE.modules.forEach((m) => {
    const s = getStats(m.id);
    gPresent += s.present;
    gAbsent += s.absent;
    gUnknown += s.unknown;
  });
  const danger = STATE.modules.filter((m) => getStats(m.id).absent >= 3 && getStats(m.id).absent < 5).length;
  const excluded = STATE.modules.filter((m) => getStats(m.id).absent >= 5).length;

  const bar = document.getElementById('stats-bar');
  bar.innerHTML = `
    <div class="stat"><div class="stat-icon si-modules">${icon('modules')}</div><div class="stat-info"><div class="stat-val c-blue">${STATE.modules.length}</div><div class="stat-label">Modules</div></div></div>
    <div class="stat"><div class="stat-icon si-present">${icon('present')}</div><div class="stat-info"><div class="stat-val c-green">${gPresent}</div><div class="stat-label">Presences</div></div></div>
    <div class="stat"><div class="stat-icon si-absent">${icon('absent')}</div><div class="stat-info"><div class="stat-val c-red">${gAbsent}</div><div class="stat-label">Absences</div></div></div>
    <div class="stat"><div class="stat-icon si-risk">${icon('risk')}</div><div class="stat-info"><div class="stat-val c-${danger > 0 ? 'warn' : 'green'}">${danger}</div><div class="stat-label">Danger (>=3)</div></div></div>
    <div class="stat"><div class="stat-icon si-excluded">${icon('absent')}</div><div class="stat-info"><div class="stat-val c-${excluded > 0 ? 'red' : 'green'}">${excluded}</div><div class="stat-label">Exclus (>=5)</div></div></div>
  `;
}

function buildDetail() {
  const m = STATE.byId.get(STATE.activeId);
  if (!m) return;
  const stats = getStats(m.id);
  const rows = STATE.attendance[m.id] || [];
  const current = rows.find((r) => isCurrentWeek(r));

  document.getElementById('dv-title').textContent = m.name;
  document.getElementById('dv-meta').innerHTML = `
    <div class="meta-chip"><span class="mod-type-pill ${PILL[m.type] || 'pill-cours'}">${m.type}</span></div>
    ${current ? `<div class="meta-chip">${icon('week', 'chip-icon')}Semaine en cours S${current.week_number}</div>` : ''}
  `;

  const pctEl = document.getElementById('dv-pct');
  pctEl.textContent = `${stats.absent} ABS`;
  pctEl.className = `pct-big ${levelClass(stats.absent)}`;
  document.getElementById('dv-counts').textContent = `${stats.present} presences · ${stats.absent} absences`;
  document.getElementById('dv-alert').classList.toggle('show', stats.absent >= 3);

  const fill = document.getElementById('dv-prog-fill');
  fill.style.width = `${riskWidth(stats.absent)}%`;
  fill.style.background = progColor(stats.absent);
  document.getElementById('dv-prog-label').textContent = `Statut: ${ruleLabel(stats.absent)} (seuil danger: 3, exclusion: 5)`;

  const labels = document.getElementById('week-labels');
  labels.innerHTML = '';
  rows.forEach((row) => {
    const d = document.createElement('div');
    d.className = `w-label${isCurrentWeek(row) ? ' cur' : ''}`;
    d.textContent = `S${row.week_number}`;
    labels.appendChild(d);
  });

  const cells = document.getElementById('cells-row');
  cells.innerHTML = '';
  rows.forEach((row) => {
    const future = isFuture(row);
    const cell = document.createElement('div');
    cell.className = `cell ${row.status}${future ? ' future' : ''}`;

    if (!future) {
      cell.addEventListener('click', async () => {
        const previous = row.status;
        row.status = nextStatus(previous);
        renderAll();
        try {
          await API.toggleAttendance(m.id, row.week_id, row.status);
        } catch (err) {
          row.status = previous;
          renderAll();
          alert(`Echec: ${err.message}`);
        }
      });
      cell.addEventListener('mousemove', (e) => showTip(e, row.week_number, row.status));
      cell.addEventListener('mouseleave', hideTip);
    }
    cells.appendChild(cell);
  });

}

function buildOverview() {
  const tbody = document.getElementById('ov-body');
  tbody.innerHTML = '';
  STATE.modules.forEach((m) => {
    const stats = getStats(m.id);
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${m.name}</td>
      <td><span class="mod-type-pill ${PILL[m.type] || 'pill-cours'}">${m.type}</span></td>
      <td style="color:var(--present)">${stats.present}</td>
      <td style="color:var(--absent)">${stats.absent}</td>
      <td>${ruleLabel(stats.absent)}</td>
      <td>
        <div class="ov-prog">
          <div class="ov-track"><div class="ov-fill" style="width:${riskWidth(stats.absent)}%;background:${progColor(stats.absent)}"></div></div>
          <span>${stats.absent}/5</span>
        </div>
      </td>
    `;
    tr.addEventListener('click', () => {
      STATE.activeId = m.id;
      switchTab('detail');
    });
    tbody.appendChild(tr);
  });
}

function showTip(e, week, status) {
  const labels = { present: 'Present', absent: 'Absent', unknown: 'Non saisi' };
  tip.innerHTML = `<div>Semaine ${week}</div><div>${labels[status]}</div><div>Clic pour changer</div>`;
  tip.style.left = `${e.clientX + 14}px`;
  tip.style.top = `${e.clientY - 8}px`;
  tip.classList.remove('hide');
}

function hideTip() {
  tip.classList.add('hide');
}

function switchTab(tab) {
  STATE.activeTab = tab;
  document.querySelectorAll('.tab-btn').forEach((b) => {
    b.classList.toggle('active', b.dataset.tab === tab);
  });
  document.getElementById('view-detail').style.display = tab === 'detail' ? 'block' : 'none';
  document.getElementById('view-overview').classList.toggle('show', tab === 'overview');
  document.getElementById('sidebar').style.display = tab === 'detail' ? 'flex' : 'none';
  renderAll();
}

function renderAll() {
  buildSidebar();
  buildStats();
  if (STATE.activeTab === 'detail') {
    buildDetail();
  } else {
    buildOverview();
  }
}

async function boot() {
  try {
    STATE.modules = await API.getModules();
    STATE.byId = new Map(STATE.modules.map((m) => [m.id, m]));
    await Promise.all(
      STATE.modules.map(async (m) => {
        STATE.attendance[m.id] = await API.getAttendance(m.id);
      })
    );

    if (STATE.modules.length > 0) {
      STATE.activeId = STATE.modules[0].id;
    }
    document.querySelectorAll('.tab-btn').forEach((btn) => {
      btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });
    renderAll();
  } catch (err) {
    alert(`Erreur de chargement: ${err.message}`);
  }
}

boot();
