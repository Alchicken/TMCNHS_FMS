// ── MODALS ─────────────────────────────────────────────────────────────────────
function openUploadModal()  { document.getElementById('uploadModal').classList.add('open'); }
function closeUploadModal() { document.getElementById('uploadModal').classList.remove('open'); }
function openLeaveModal()   { document.getElementById('leaveModal').classList.add('open'); }
function closeLeaveModal()  { document.getElementById('leaveModal').classList.remove('open'); }
function closeDocSuccess()  { document.getElementById('docSuccessModal').classList.remove('open'); }
function closeLeaveSuccess(){ document.getElementById('leaveSuccessModal').classList.remove('open'); }

// ── CONFETTI ───────────────────────────────────────────────────────────────────
function spawnConfetti(id, c1, c2) {
  const el = document.getElementById(id); if (!el) return;
  const cols = [c1, c2, '#059669', '#d97706', '#1a0608'];
  for (let i = 0; i < 24; i++) {
    const d = document.createElement('div'); d.className = 'confetti-dot';
    d.style.cssText = `left:${Math.random()*100}%;top:-10px;background:${cols[i%cols.length]};
      width:${Math.random()*8+4}px;height:${Math.random()*8+4}px;
      border-radius:${Math.random()>.5?'50%':'2px'};
      animation-delay:${Math.random()*.6}s;animation-duration:${Math.random()*1+1.2}s`;
    el.appendChild(d);
  }
  setTimeout(() => { if(el) el.innerHTML = ''; }, 2500);
}

function openDocSuccess(title) {
  document.getElementById('docSuccessName').textContent = title || 'your document';
  document.getElementById('docSuccessModal').classList.add('open');
  setTimeout(() => spawnConfetti('docConfetti','#c8102e','#ff4d6d'), 200);
}
function openLeaveSuccess(type, days) {
  document.getElementById('leaveSuccessInfo').textContent = (type||'Leave') + ' — ' + days + ' day' + (days!==1?'s':'');
  document.getElementById('leaveSuccessModal').classList.add('open');
  setTimeout(() => spawnConfetti('leaveConfetti','#7b1fa2','#7c3aed'), 200);
}

// ── CALENDAR ───────────────────────────────────────────────────────────────────
let calDate = new Date(), selectedDates = new Set();
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const DSHORT = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

function renderCalendar() {
  const grid = document.getElementById('calGrid'); if (!grid) return;
  const y = calDate.getFullYear(), m = calDate.getMonth();
  document.getElementById('calMonthLabel').textContent = MONTHS[m] + ' ' + y;
  grid.innerHTML = '';
  DSHORT.forEach(d => { const c=document.createElement('div'); c.className='day-label'; c.textContent=d; grid.appendChild(c); });
  const first = new Date(y,m,1).getDay(), dim = new Date(y,m+1,0).getDate(), today = new Date();
  for (let i=0;i<first;i++) { const c=document.createElement('div'); c.className='day-cell empty'; grid.appendChild(c); }
  for (let d=1;d<=dim;d++) {
    const c=document.createElement('div'); c.className='day-cell'; c.textContent=d;
    const ds=y+'-'+String(m+1).padStart(2,'0')+'-'+String(d).padStart(2,'0');
    if (today.getDate()===d&&today.getMonth()===m&&today.getFullYear()===y) c.classList.add('today');
    if (selectedDates.has(ds)) c.classList.add('selected');
    c.addEventListener('click',()=>toggleDate(ds,c)); grid.appendChild(c);
  }
  updateDateDisplay();
}
function toggleDate(ds, c) {
  selectedDates.has(ds) ? (selectedDates.delete(ds), c.classList.remove('selected'))
                        : (selectedDates.add(ds),    c.classList.add('selected'));
  updateDateDisplay();
}
function updateDateDisplay() {
  const sorted = Array.from(selectedDates).sort(), n = sorted.length;
  const inp = document.getElementById('absentDatesInput'); if(inp) inp.value = JSON.stringify(sorted);
  const di  = document.getElementById('daysAbsentInput');  if(di)  di.value  = n;
  const ro  = document.getElementById('daysAbsentReadonly'); if(ro) ro.value = n>0 ? n+' day'+(n!==1?'s':'') : '';
  const dl  = document.getElementById('daysAbsentDisplay'); if(dl) dl.textContent = n>0?'('+n+')':'';
  const box = document.getElementById('selectedDatesDisplay'); if(!box) return;
  if (!n) { box.innerHTML='<span class="no-dates">No dates selected</span>'; return; }
  const w=document.createElement('div'); w.className='date-chips';
  sorted.forEach(d => {
    const ch=document.createElement('span'); ch.className='date-chip';
    const dt=new Date(d+'T00:00:00');
    ch.textContent=MONTHS[dt.getMonth()].substring(0,3)+' '+dt.getDate();
    ch.style.cursor='pointer'; ch.title='Click to remove';
    ch.addEventListener('click',()=>{ selectedDates.delete(d); renderCalendar(); }); w.appendChild(ch);
  });
  box.innerHTML=''; box.appendChild(w);
}
function changeMonth(dir) { calDate=new Date(calDate.getFullYear(),calDate.getMonth()+dir,1); renderCalendar(); }

// ── INIT ───────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Close on backdrop click
  document.querySelectorAll('.modal-overlay,.success-modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); });
  });
  // File input display
  const fi = document.getElementById('fileInput');
  if (fi) fi.addEventListener('change', function() {
    const el=document.getElementById('fileNameDisplay'); if(el) el.textContent=this.files[0]?'📎 '+this.files[0].name:'';
  });
  // Drag-drop
  const dz = document.getElementById('dropZone');
  if (dz) {
    dz.addEventListener('dragover', e=>{e.preventDefault();dz.classList.add('dragover');});
    dz.addEventListener('dragleave', ()=>dz.classList.remove('dragover'));
    dz.addEventListener('drop', e=>{
      e.preventDefault(); dz.classList.remove('dragover');
      const fi=document.getElementById('fileInput'); fi.files=e.dataTransfer.files;
      const el=document.getElementById('fileNameDisplay'); if(el&&fi.files[0]) el.textContent='📎 '+fi.files[0].name;
    });
  }
  // Leave scan input
  const ls = document.getElementById('leaveScanInput');
  if (ls) ls.addEventListener('change', function() {
    const el=document.getElementById('leaveFileNameDisplay'); if(el) el.textContent=this.files[0]?'📎 '+this.files[0].name:'';
  });
  // Leave form submit guard
  const lf = document.getElementById('leaveForm');
  if (lf) lf.addEventListener('submit', e=>{
    if (selectedDates.size===0) { e.preventDefault(); alert('Please select at least one absent date.'); return; }
    document.getElementById('absentDatesInput').value=JSON.stringify(Array.from(selectedDates).sort());
    document.getElementById('daysAbsentInput').value=selectedDates.size;
  });
  renderCalendar();
});