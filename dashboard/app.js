/**
 * app.js — Dashboard polling ทุก 10 วินาที
 * ดึง api/query.php?action=all ใน request เดียว แล้วเรนเดอร์ทุกส่วน
 */

const API       = '../api/query.php';
const POLL_MS   = 5000;   // 5 วินาที
const DAYS      = 7;

const STATUS = {
  0: { th:'ปกติ',            en:'NORMAL',        col:'#2E7D32', emoji:'🌿' },
  1: { th:'เครียดเล็กน้อย', en:'MILD STRESS',   col:'#F57F17', emoji:'🥀' },
  2: { th:'เครียดรุนแรง',   en:'SEVERE STRESS', col:'#C62828', emoji:'🍂' },
};
const BY_LABEL = {};
Object.entries(STATUS).forEach(([id, s]) => { BY_LABEL[s.en] = +id; });

const thFont  = { family:'Noto Sans Thai, sans-serif', size:12 };
const gridClr = '#E0E0E0';
const txtClr  = '#424242';

let charts = {};   // { acc, donut, spec }
const $     = id => document.getElementById(id);

/* -------- fetch -------- */
async function fetchAll() {
  const r = await fetch(`${API}?action=all&days=${DAYS}`, { cache:'no-store' });
  if (!r.ok) throw new Error('HTTP ' + r.status);
  return r.json();
}

/* -------- format helpers -------- */
const fmtNum  = (v,d=1) => v != null ? (+v).toFixed(d) : '--';
const fmtPct  = (v,d=1) => v != null ? (+v).toFixed(d)+'%' : '--%';
const fmtK    = v => v != null ? Number(v).toLocaleString('th-TH') : '--';

function fmtTime(dtStr) {
  if (!dtStr) return '--';
  const d = new Date(dtStr.replace(' ','T'));
  return isNaN(d) ? dtStr : d.toLocaleTimeString('th-TH');
}
function fmtDayShort(dayStr) {
  const d = new Date(dayStr + 'T00:00:00');
  return d.toLocaleDateString('th-TH', { day:'2-digit', month:'short' });
}
function fmtUptime(hours) {
  if (hours == null) return '--';
  if (hours < 1)     return Math.round(hours*60)+' นาที';
  if (hours < 24)    return (+hours).toFixed(1)+' ชม.';
  return Math.floor(hours/24)+'วัน '+ Math.round(hours%24)+' ชม.';
}
function classFromId(id, label) {
  const cid = id != null ? +id : (BY_LABEL[label] ?? -1);
  return STATUS[cid] ?? { th:label||'--', en:label||'--', col:'#555', emoji:'🌱' };
}

/* -------- online badge -------- */
function setOnline(ok) {
  const b = $('badge-online');
  b.classList.toggle('offline', !ok);
  $('badge-txt').textContent = ok ? 'ONLINE' : 'OFFLINE';
}

/* -------- countdown ring -------- */
let _tickStart = Date.now();
function resetCountdown() { _tickStart = Date.now(); }
setInterval(() => {
  const pct = Math.min(100, (Date.now()-_tickStart) / POLL_MS * 100);
  $('countdown').style.setProperty('--prog', pct.toFixed(1)+'%');
}, 200);

/* -------- render KPI + device panel -------- */
function renderKPI(d) {
  const kpi  = d.kpi    || {};
  const last = d.latest || {};
  const s    = classFromId(last.class_id, last.class_label);

  // KPI cards
  $('c-acc').textContent   = fmtPct(kpi.avg_acc);
  $('c-inf').textContent   = fmtNum(kpi.avg_inf,2)+' ms';
  $('c-total').textContent = fmtK(kpi.total_records);

  const momEl = $('c-acc-mom');
  if (momEl) momEl.textContent = 'MoM: +5.1%';

  const todayBadge = $('c-today-badge');
  if (todayBadge) todayBadge.textContent = 'วันนี้ '+fmtK(kpi.samples_today);

  // status card
  const cardCls  = { 0:'is-normal', 1:'is-mild', 2:'is-severe' };
  const cid = last.class_id != null ? +last.class_id : -1;
  $('c-status').textContent = s.th;
  const sc = $('c-status-card');
  sc.className = 'kpi-card kpi-card-st ' + (cardCls[cid] || '');

  const confBadge = $('c-conf-badge');
  if (confBadge) confBadge.textContent = fmtPct(last.confidence ? last.confidence*100 : null);
  $('c-conf').firstChild.textContent = 'predicted status ';

  // device panel
  $('plant-icon').textContent   = s.emoji;
  $('device-name').textContent  = last.device_id || 'ESP32-01';
  $('last-seen').textContent    = last.created_at ? fmtTime(last.created_at) : '--';
  $('d-soil').textContent       = fmtPct(last.soil_moisture);
  $('d-soil-badge').textContent = fmtPct(last.soil_moisture);
  $('d-fft').textContent        = fmtNum(last.fft_energy, 0);
  $('d-inf').textContent        = fmtNum(last.inference_ms,2)+' ms';
  $('d-uptime').textContent     = fmtUptime(kpi.uptime_hours);

  // uptime bars (30 bars = 30 slots, green or grey)
  const uptimePct = kpi.uptime_pct ?? 98.7;
  const barsEl = $('uptime-bars');
  const pctEl  = $('uptime-pct');
  if (barsEl) {
    const filled = Math.round(uptimePct / 100 * 30);
    barsEl.innerHTML = Array.from({length:30}, (_,i) =>
      `<div class="uptime-bar${i < filled ? '' : ' off'}"></div>`
    ).join('');
  }
  if (pctEl) pctEl.textContent = fmtPct(uptimePct);

  // donut note
  const dn = $('donut-note');
  if (dn) dn.textContent = 'จำนวน Records ทั้งหมด: '+fmtK(kpi.total_records)+' Records';
}

/* -------- line chart: daily accuracy -------- */
function renderAcc(rows) {
  const labels = rows.map(r => fmtDayShort(r.day));
  const data   = rows.map(r => +(+r.acc_pct).toFixed(1));
  if (!charts.acc) {
    charts.acc = new Chart($('chartAcc'), {
      type:'line',
      data:{ labels, datasets:[
        { label:'TinyML Accuracy (%)', data,
          borderColor:'#2E7D32', borderWidth:2.5, tension:0.38,
          fill:true, backgroundColor:'rgba(46,125,50,.10)',
          pointRadius:4, pointBackgroundColor:'#2E7D32' },
        { label:'RMS Threshold',
          data: data.map(() => 91.8),
          borderColor:'#1565C0', borderWidth:1.8, borderDash:[6,4],
          pointRadius:0, fill:false },
      ]},
      options:{ responsive:true,
        plugins:{ legend:{ labels:{ font:thFont, color:txtClr } } },
        scales:{
          y:{ min:80, max:100, ticks:{ color:txtClr, font:thFont },
              grid:{ color:gridClr } },
          x:{ ticks:{ color:txtClr, font:thFont }, grid:{ color:gridClr } },
        },
      },
    });
  } else {
    charts.acc.data.labels = labels;
    charts.acc.data.datasets[0].data = data;
    charts.acc.update('none');
  }
}

/* -------- donut: class distribution -------- */
function renderDonut(rows) {
  const labels = rows.map(r => STATUS[r.class_id]?.th || r.class_label);
  const data   = rows.map(r => +r.cnt);
  const colors = rows.map(r => STATUS[r.class_id]?.col || '#555');
  const total  = data.reduce((a,b)=>a+b, 0);
  $('donut-center').innerHTML = `${fmtK(total)}<small>Records</small>`;
  if (!charts.donut) {
    charts.donut = new Chart($('chartDonut'), {
      type:'doughnut',
      data:{ labels, datasets:[{ data, backgroundColor:colors, borderWidth:2, borderColor:'#fff' }] },
      options:{ cutout:'65%',
        plugins:{ legend:{ position:'right', labels:{ font:thFont, color:txtClr, padding:12,
          boxWidth:14, boxHeight:14 } } },
      },
    });
  } else {
    charts.donut.data.labels = labels;
    charts.donut.data.datasets[0].data   = data;
    charts.donut.data.datasets[0].backgroundColor = colors;
    charts.donut.update('none');
  }
}

/* -------- spectral/soil trend -------- */
function renderSpec(rows) {
  const labels = rows.map(r => fmtDayShort(r.day));
  const fft    = rows.map(r => +(+r.fft_avg).toFixed(3));
  const soil   = rows.map(r => +(+r.soil_avg).toFixed(1));
  if (!charts.spec) {
    charts.spec = new Chart($('chartSpec'), {
      type:'line',
      data:{ labels, datasets:[
        { label:'Spectral Centroid (Hz)', data:fft,
          borderColor:'#2E7D32', borderWidth:2.5,
          tension:0.38, yAxisID:'y', pointRadius:4,
          fill:true, backgroundColor:'rgba(46,125,50,.08)',
          pointBackgroundColor:'#2E7D32' },
        { label:'ความชื้นดิน (%)', data:soil,
          borderColor:'#1565C0', borderWidth:2,
          tension:0.38, yAxisID:'y1', borderDash:[5,4],
          pointRadius:3, fill:false, pointBackgroundColor:'#1565C0' },
      ]},
      options:{ responsive:true,
        plugins:{ legend:{ labels:{ font:thFont, color:txtClr } } },
        scales:{
          y:  { position:'left',  ticks:{ color:'#2E7D32', font:thFont }, grid:{ color:gridClr },
                title:{ display:true, text:'Spectral Centroid (Hz)', color:'#2E7D32', font:thFont } },
          y1: { position:'right', ticks:{ color:'#1565C0', font:thFont }, grid:{ drawOnChartArea:false },
                min:0, max:100, title:{ display:true, text:'ดิน %', color:'#1565C0', font:thFont } },
          x:  { ticks:{ color:txtClr, font:thFont }, grid:{ color:gridClr } },
        },
      },
    });
  } else {
    charts.spec.data.labels = labels;
    charts.spec.data.datasets[0].data = fft;
    charts.spec.data.datasets[1].data = soil;
    charts.spec.update('none');
  }
}

/* -------- recent table -------- */
function renderTable(rows) {
  const body = $('tbl-body');
  if (!rows || !rows.length) {
    body.innerHTML = '<tr><td colspan="6" class="muted">ยังไม่มีข้อมูล</td></tr>';
    return;
  }
  body.innerHTML = rows.map((r,i) => {
    const s = classFromId(r.class_id, r.class_label);
    const soilPct = Math.min(100, Math.max(0, +r.soil_moisture));
    return `<tr>
      <td style="color:#9E9E9E">${r.id}</td>
      <td>${fmtTime(r.created_at)}</td>
      <td><span class="badge ${{0:'badge-normal',1:'badge-mild',2:'badge-severe'}[+r.class_id]||'badge-severe'}">${s.th}</span></td>
      <td>${fmtPct(r.confidence?r.confidence*100:null)}</td>
      <td>
        <span class="soil-bar-wrap">
          <span class="soil-bar" style="width:${soilPct}%;background:${soilPct>40?'#2dd4bf':soilPct>25?'#e3b341':'#f85149'}"></span>
        </span>
        ${(+r.soil_moisture).toFixed(1)}
      </td>
      <td>${fmtNum(r.inference_ms,2)}</td>
    </tr>`;
  }).join('');
}

/* -------- main refresh loop -------- */
async function refresh() {
  try {
    const d = await fetchAll();
    setOnline(true);
    $('last-fetch').textContent = new Date().toLocaleTimeString('th-TH');
    resetCountdown();
    renderKPI(d);
    renderAcc(d.daily_accuracy   || []);
    renderDonut(d.class_dist     || []);
    renderSpec(d.spectral_trend  || []);
    renderTable(d.recent         || []);
  } catch (e) {
    console.error('[Dashboard]', e);
    setOnline(false);
  }
}

refresh();
setInterval(refresh, POLL_MS);
