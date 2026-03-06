*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f7f2f2;--surface:#ffffff;--card:#ffffff;--border:#f0d4d4;
  --accent:#c8102e;--accent2:#9b0c22;--text:#1a0608;--muted:#9c7b7e;
  --success:#059669;--danger:#c8102e;--warn:#d97706;--purple:#7b1fa2;
  --sidebar:260px;
}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex}

/* SIDEBAR */
.sidebar{width:var(--sidebar);background:var(--accent2);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100;box-shadow:4px 0 20px rgba(155,12,34,.15)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f7f2f2;--surface:#ffffff;--card:#ffffff;--border:#f0d4d4;
  --accent:#c8102e;--accent2:#9b0c22;--text:#1a0608;--muted:#9c7b7e;
  --success:#059669;--danger:#c8102e;--warn:#d97706;--purple:#7b1fa2;
  --sidebar:260px;
}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex}

/* SIDEBAR */
.sidebar{width:var(--sidebar);background:var(--accent2);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100;box-shadow:4px 0 20px rgba(155,12,34,.15)}
.sidebar-logo { padding: 24px 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.12); display: flex; flex-direction: column; align-items: center; gap: 12px; background: transparent; }
.sidebar-logo-img-wrap { width: 76px; height: 76px; border-radius: 50%; background: #fff; padding: 6px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 18px rgba(0,0,0,0.3), 0 0 0 3px rgba(255,255,255,0.18); flex-shrink: 0; }
.sidebar-logo img { width: 100%; height: 100%; object-fit: contain; display: block; }
.sidebar-logo-text { text-align: center; }
.sidebar-logo-text h2 { color: #fff; font-size: 12.5px; font-weight: 700; font-family: 'DM Serif Display', serif; line-height: 1.45; letter-spacing: 0.01em; }
.sidebar-logo-text span { color: rgba(255,255,255,0.45); font-size: 9px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 1.8px; display: block; margin-top: 4px; }
.nav-label{color:rgba(255,255,255,.45);font-size:10px;font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:2px;padding:12px 12px 6px}
.nav-item{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:10px;color:rgba(255,255,255,.65);font-size:13px;font-weight:500;transition:all .2s;margin-bottom:2px;text-decoration:none}
.nav-item:hover{background:rgba(255,255,255,.12);color:#fff}
.nav-item.active{background:rgba(255,255,255,.18);color:#fff;font-weight:600}
.nav-item .ni{font-size:16px}
.sidebar-footer{padding:16px 12px;border-top:1px solid rgba(255,255,255,.15)}
.user-chip{display:flex;align-items:center;gap:10px;padding:10px 12px;background:rgba(255,255,255,.12);border-radius:10px}
.avatar{width:34px;height:34px;background:rgba(255,255,255,.25);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:#fff;flex-shrink:0}
.user-info{flex:1;overflow:hidden}
.user-info strong{display:block;font-size:12px;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-info small{color:rgba(255,255,255,.6);font-size:10px;font-family:'JetBrains Mono',monospace}
.logout-btn{color:rgba(255,255,255,.55);font-size:16px;text-decoration:none}
.logout-btn:hover{color:#fff}

/* MAIN */
.main{margin-left:var(--sidebar);flex:1}
.topbar{padding:20px 32px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--surface);position:sticky;top:0;z-index:50}
.topbar h1{font-size:18px;font-weight:700}
.topbar p{color:var(--muted);font-size:12px;margin-top:2px}
.topbar-actions{display:flex;gap:10px;align-items:center}
.page{padding:28px 32px}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .2s;text-decoration:none}
.btn-primary{background:var(--accent);color:#fff}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(155,12,34,.35)}
.btn-blue{background:linear-gradient(135deg,#c8102e,var(--accent));color:#fff}
.btn-blue:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(155,12,34,.35)}
.btn-purple{background:linear-gradient(135deg,#6a1788,var(--purple));color:#fff}
.btn-purple:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(124,58,237,.35)}
.btn-sm{padding:6px 12px;font-size:11px}
.btn-danger{background:rgba(200,16,46,.1);color:var(--danger);border:1px solid rgba(200,16,46,.3)}
.btn-danger:hover{background:rgba(200,16,46,.18)}
.btn-ghost{background:var(--surface);color:var(--text);border:1px solid var(--border)}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}

/* ALERTS */
.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.alert-success{background:rgba(5,150,105,.1);border:1px solid rgba(5,150,105,.3);color:var(--success)}
.alert-danger{background:rgba(255,107,107,.1);border:1px solid rgba(200,16,46,.3);color:var(--danger)}

/* TABLES */
.table-wrap{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden}
table{width:100%;border-collapse:collapse}
thead{background:var(--surface)}
th{padding:12px 16px;text-align:left;font-size:11px;font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:1px;color:var(--muted)}
td{padding:13px 16px;border-top:1px solid var(--border);font-size:13px}
tr:hover td{background:rgba(200,16,46,.025)}
.file-icon{font-size:20px}

/* SECTION HEAD */
.section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.section-head h2{font-size:16px;font-weight:600}

/* STATS */
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:22px 24px;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.stat-card.green::before{background:linear-gradient(90deg,var(--accent2),var(--accent))}
.stat-card.blue::before{background:linear-gradient(90deg,#c8102e,var(--accent))}
.stat-card.warn::before{background:linear-gradient(90deg,#d97706,#f59e0b)}
.stat-card.purple::before{background:linear-gradient(90deg,#6a1788,var(--purple))}
.stat-card.danger::before{background:linear-gradient(90deg,#dc2626,var(--danger))}
.stat-icon{font-size:28px;margin-bottom:10px}
.stat-num{font-size:36px;font-weight:700;font-family:'JetBrains Mono',monospace}
.stat-label{color:var(--muted);font-size:12px;margin-top:4px}

/* PROFILE HERO */
.profile-hero{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;margin-bottom:24px;display:flex;gap:24px;align-items:center}
.prof-avatar{width:80px;height:80px;border-radius:18px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:#fff;flex-shrink:0}
.prof-info h2{font-size:20px;margin-bottom:4px}
.prof-info p{color:var(--muted);font-size:13px}
.prof-tags{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}
.tag{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-family:'JetBrains Mono',monospace}
.tag-green{background:rgba(5,150,105,.15);color:#059669}
.tag-blue{background:rgba(200,16,46,.15);color:var(--accent)}
.tag-purple{background:rgba(167,139,250,.15);color:var(--purple)}
.tag-red{background:rgba(200,16,46,.1);color:var(--danger)}
.tag-yellow{background:rgba(245,158,11,.15);color:var(--warn)}

/* FORM */
.edit-form{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px}
.edit-form h3{font-size:16px;margin-bottom:20px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{margin-bottom:16px}
.form-group label{display:block;color:var(--muted);font-size:11px;font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.form-group input,.form-group textarea,.form-group select{width:100%;padding:11px 14px;background:var(--surface);border:1px solid var(--border);border-radius:9px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:13px;outline:none;transition:border-color .2s}
.form-group input:focus,.form-group textarea:focus,.form-group select:focus{border-color:var(--accent)}
.form-group textarea{resize:vertical;min-height:80px}

/* LEAVE STATS */
.leave-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:28px}
.leave-stat-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:20px;position:relative;overflow:hidden}
.leave-stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.leave-stat-card.green::before{background:linear-gradient(90deg,var(--accent2),var(--accent))}
.leave-stat-card.danger::before{background:linear-gradient(90deg,#dc2626,var(--danger))}
.leave-stat-card.purple::before{background:linear-gradient(90deg,#6a1788,var(--purple))}
.leave-stat-card.blue::before{background:linear-gradient(90deg,#c8102e,var(--accent))}
.leave-stat-card.warn::before{background:linear-gradient(90deg,#d97706,#f59e0b)}
.ls-icon{font-size:24px;margin-bottom:8px}
.ls-num{font-size:32px;font-weight:700;font-family:'JetBrains Mono',monospace;line-height:1}
.ls-label{color:var(--muted);font-size:11px;margin-top:6px}
.ls-sub{font-size:10px;font-family:'JetBrains Mono',monospace;margin-top:4px}

/* PROGRESS */
.leave-progress-wrap{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:22px;margin-bottom:24px}
.leave-progress-wrap h3{font-size:14px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center}
.progress-bar-bg{background:rgba(200,16,46,.06);border-radius:999px;height:12px;overflow:hidden}
.progress-bar-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,var(--accent2),var(--accent));transition:width .6s ease}
.progress-bar-fill.warn-fill{background:linear-gradient(90deg,#d97706,#f59e0b)}
.progress-bar-fill.danger-fill{background:linear-gradient(90deg,#dc2626,var(--danger))}
.progress-labels{display:flex;justify-content:space-between;margin-top:8px}
.progress-labels span{font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--muted)}

/* LEAVE TYPE BADGES */
.leave-type-badge{padding:3px 10px;border-radius:20px;font-size:10px;font-family:'JetBrains Mono',monospace}
.leave-sick{background:rgba(239,68,68,.15);color:#f87171}
.leave-vacation{background:rgba(200,16,46,.15);color:var(--accent)}
.leave-emergency{background:rgba(245,158,11,.15);color:var(--warn)}
.leave-maternity{background:rgba(167,139,250,.15);color:var(--purple)}
.leave-other{background:rgba(136,146,176,.15);color:var(--muted)}

/* DATE CHIPS */
.date-chips{display:flex;flex-wrap:wrap;gap:4px}
.date-chip{padding:3px 8px;background:rgba(167,139,250,.15);color:var(--purple);border-radius:6px;font-size:10px;font-family:'JetBrains Mono',monospace}

/* MODALS */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
.modal-overlay.open{display:flex}
.modal{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:32px;width:100%;max-width:480px;animation:slideIn .3s ease;max-height:90vh;overflow-y:auto}
.modal.modal-wide{max-width:620px}
@keyframes slideIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
.modal h2{font-size:18px;margin-bottom:24px}
.modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:20px}

/* DROP ZONES */
.drop-zone{border:2px dashed var(--border);border-radius:12px;padding:32px;text-align:center;cursor:pointer;transition:all .2s;margin-bottom:16px}
.drop-zone:hover,.drop-zone.dragover{border-color:var(--accent);background:rgba(200,16,46,.04)}
.drop-zone p{color:var(--muted);font-size:13px;margin-top:8px}
.drop-zone span{font-size:32px}
.drop-zone-sm{border:2px dashed var(--border);border-radius:10px;padding:16px;text-align:center;cursor:pointer;transition:all .2s}
.drop-zone-sm:hover{border-color:var(--purple);background:rgba(167,139,250,.05)}
.drop-zone-sm .dz-icon{font-size:24px}
.drop-zone-sm p{color:var(--muted);font-size:12px;margin-top:4px}

/* CALENDAR */
.date-picker-wrap{border:1px solid var(--border);border-radius:9px;background:var(--surface);overflow:hidden}
.date-picker-header{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--border)}
.date-picker-header span{font-size:13px;font-weight:600}
.date-nav-btn{background:none;border:none;color:var(--muted);cursor:pointer;font-size:16px;padding:2px 6px;border-radius:4px}
.date-nav-btn:hover{color:var(--accent);background:rgba(200,16,46,.08)}
.date-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;padding:10px}
.day-label{text-align:center;font-size:10px;font-family:'JetBrains Mono',monospace;color:var(--muted);padding:4px 0}
.day-cell{aspect-ratio:1;display:flex;align-items:center;justify-content:center;font-size:12px;border-radius:6px;cursor:pointer;transition:all .15s;font-family:'JetBrains Mono',monospace}
.day-cell:hover:not(.empty){background:rgba(167,139,250,.15);color:var(--purple)}
.day-cell.selected{background:rgba(167,139,250,.25);color:var(--purple);border:1px solid rgba(167,139,250,.5);font-weight:700}
.day-cell.today{color:var(--accent);font-weight:700}
.day-cell.empty{opacity:.2;cursor:default}
.selected-dates-display{padding:8px 14px;border-top:1px solid var(--border);min-height:36px}
.selected-dates-display .no-dates{color:var(--muted);font-size:11px;font-style:italic}

/* SUCCESS MODALS */
.success-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:2000;align-items:center;justify-content:center;backdrop-filter:blur(6px)}
.success-modal-overlay.open{display:flex}
.success-modal{background:var(--card);border:1px solid var(--border);border-radius:24px;padding:40px 36px;width:100%;max-width:420px;text-align:center;position:relative;overflow:hidden;animation:successPop .4s cubic-bezier(.175,.885,.32,1.275)}
@keyframes successPop{from{opacity:0;transform:scale(.7) translateY(30px)}to{opacity:1;transform:scale(1) translateY(0)}}
.success-modal.doc-success::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#c8102e,var(--accent),#c8102e);background-size:200% 100%;animation:shimmer 2s infinite}
.success-modal.leave-success::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#7c3aed,var(--purple),#7c3aed);background-size:200% 100%;animation:shimmer 2s infinite}
@keyframes shimmer{0%{background-position:-200% 0}100%{background-position:200% 0}}
.success-icon-wrap{width:88px;height:88px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:40px;position:relative}
.success-icon-wrap.doc-icon{background:rgba(200,16,46,.12);border:2px solid rgba(200,16,46,.25)}
.success-icon-wrap.leave-icon{background:rgba(167,139,250,.12);border:2px solid rgba(167,139,250,.25)}
.success-icon-wrap::before{content:'';position:absolute;inset:-8px;border-radius:50%;border:2px solid transparent;animation:pulseRing 2s ease-out infinite}
.doc-icon::before{border-color:rgba(200,16,46,.3)}
.leave-icon::before{border-color:rgba(167,139,250,.3)}
@keyframes pulseRing{0%{transform:scale(1);opacity:1}100%{transform:scale(1.4);opacity:0}}
.check-badge{position:absolute;bottom:-4px;right:-4px;width:28px;height:28px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;border:2px solid var(--card);animation:checkBounce .5s .3s cubic-bezier(.175,.885,.32,1.275) both}
@keyframes checkBounce{from{transform:scale(0)}to{transform:scale(1)}}
.success-title{font-size:20px;font-weight:700;margin-bottom:8px;line-height:1.2}
.success-subtitle{color:var(--muted);font-size:13px;line-height:1.6;margin-bottom:24px}
.success-detail-pill{display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:12px;font-size:12px;font-family:'JetBrains Mono',monospace;margin-bottom:28px}
.success-detail-pill.doc-pill{background:rgba(200,16,46,.1);border:1px solid rgba(200,16,46,.2);color:var(--accent)}
.success-detail-pill.leave-pill{background:rgba(167,139,250,.1);border:1px solid rgba(167,139,250,.2);color:var(--purple)}
.success-close-btn{width:100%;padding:12px;border-radius:12px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:all .2s}
.success-close-btn.doc-close{background:linear-gradient(135deg,#c8102e,var(--accent));color:#fff}
.success-close-btn.doc-close:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(155,12,34,.4)}
.success-close-btn.leave-close{background:linear-gradient(135deg,#6a1788,var(--purple));color:#fff}
.success-close-btn.leave-close:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(124,58,237,.4)}
.confetti-container{position:absolute;inset:0;pointer-events:none;overflow:hidden;border-radius:24px}
.confetti-dot{position:absolute;width:6px;height:6px;border-radius:2px;animation:confettiFall 1.5s ease-out forwards}
@keyframes confettiFall{0%{transform:translateY(-20px) rotate(0deg);opacity:1}100%{transform:translateY(300px) rotate(720deg);opacity:0}}
.nav{padding:16px 12px;flex:1}
.nav-label{color:rgba(255,255,255,.45);font-size:10px;font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:2px;padding:12px 12px 6px}
.nav-item{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:10px;color:rgba(255,255,255,.65);font-size:13px;font-weight:500;transition:all .2s;margin-bottom:2px;text-decoration:none}
.nav-item:hover{background:rgba(255,255,255,.12);color:#fff}
.nav-item.active{background:rgba(255,255,255,.18);color:#fff;font-weight:600}
.nav-item .ni{font-size:16px}
.sidebar-footer{padding:16px 12px;border-top:1px solid rgba(255,255,255,.15)}
.user-chip{display:flex;align-items:center;gap:10px;padding:10px 12px;background:rgba(255,255,255,.12);border-radius:10px}
.avatar{width:34px;height:34px;background:rgba(255,255,255,.25);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:#fff;flex-shrink:0}
.user-info{flex:1;overflow:hidden}
.user-info strong{display:block;font-size:12px;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.user-info small{color:rgba(255,255,255,.6);font-size:10px;font-family:'JetBrains Mono',monospace}
.logout-btn{color:rgba(255,255,255,.55);font-size:16px;text-decoration:none}
.logout-btn:hover{color:#fff}

/* MAIN */
.main{margin-left:var(--sidebar);flex:1}
.topbar{padding:20px 32px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--surface);position:sticky;top:0;z-index:50}
.topbar h1{font-size:18px;font-weight:700}
.topbar p{color:var(--muted);font-size:12px;margin-top:2px}
.topbar-actions{display:flex;gap:10px;align-items:center}
.page{padding:28px 32px}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .2s;text-decoration:none}
.btn-primary{background:var(--accent);color:#fff}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(155,12,34,.35)}
.btn-blue{background:linear-gradient(135deg,#c8102e,var(--accent));color:#fff}
.btn-blue:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(155,12,34,.35)}
.btn-purple{background:linear-gradient(135deg,#6a1788,var(--purple));color:#fff}
.btn-purple:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(124,58,237,.35)}
.btn-sm{padding:6px 12px;font-size:11px}
.btn-danger{background:rgba(200,16,46,.1);color:var(--danger);border:1px solid rgba(200,16,46,.3)}
.btn-danger:hover{background:rgba(200,16,46,.18)}
.btn-ghost{background:var(--surface);color:var(--text);border:1px solid var(--border)}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}

/* ALERTS */
.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.alert-success{background:rgba(5,150,105,.1);border:1px solid rgba(5,150,105,.3);color:var(--success)}
.alert-danger{background:rgba(255,107,107,.1);border:1px solid rgba(200,16,46,.3);color:var(--danger)}

/* TABLES */
.table-wrap{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden}
table{width:100%;border-collapse:collapse}
thead{background:var(--surface)}
th{padding:12px 16px;text-align:left;font-size:11px;font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:1px;color:var(--muted)}
td{padding:13px 16px;border-top:1px solid var(--border);font-size:13px}
tr:hover td{background:rgba(200,16,46,.025)}
.file-icon{font-size:20px}

/* SECTION HEAD */
.section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.section-head h2{font-size:16px;font-weight:600}

/* STATS */
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:22px 24px;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.stat-card.green::before{background:linear-gradient(90deg,var(--accent2),var(--accent))}
.stat-card.blue::before{background:linear-gradient(90deg,#c8102e,var(--accent))}
.stat-card.warn::before{background:linear-gradient(90deg,#d97706,#f59e0b)}
.stat-card.purple::before{background:linear-gradient(90deg,#6a1788,var(--purple))}
.stat-card.danger::before{background:linear-gradient(90deg,#dc2626,var(--danger))}
.stat-icon{font-size:28px;margin-bottom:10px}
.stat-num{font-size:36px;font-weight:700;font-family:'JetBrains Mono',monospace}
.stat-label{color:var(--muted);font-size:12px;margin-top:4px}

/* PROFILE HERO */
.profile-hero{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;margin-bottom:24px;display:flex;gap:24px;align-items:center}
.prof-avatar{width:80px;height:80px;border-radius:18px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:#fff;flex-shrink:0}
.prof-info h2{font-size:20px;margin-bottom:4px}
.prof-info p{color:var(--muted);font-size:13px}
.prof-tags{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}
.tag{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-family:'JetBrains Mono',monospace}
.tag-green{background:rgba(5,150,105,.15);color:#059669}
.tag-blue{background:rgba(200,16,46,.15);color:var(--accent)}
.tag-purple{background:rgba(167,139,250,.15);color:var(--purple)}
.tag-red{background:rgba(200,16,46,.1);color:var(--danger)}
.tag-yellow{background:rgba(245,158,11,.15);color:var(--warn)}

/* FORM */
.edit-form{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px}
.edit-form h3{font-size:16px;margin-bottom:20px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{margin-bottom:16px}
.form-group label{display:block;color:var(--muted);font-size:11px;font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.form-group input,.form-group textarea,.form-group select{width:100%;padding:11px 14px;background:var(--surface);border:1px solid var(--border);border-radius:9px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:13px;outline:none;transition:border-color .2s}
.form-group input:focus,.form-group textarea:focus,.form-group select:focus{border-color:var(--accent)}
.form-group textarea{resize:vertical;min-height:80px}

/* LEAVE STATS */
.leave-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:28px}
.leave-stat-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:20px;position:relative;overflow:hidden}
.leave-stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.leave-stat-card.green::before{background:linear-gradient(90deg,var(--accent2),var(--accent))}
.leave-stat-card.danger::before{background:linear-gradient(90deg,#dc2626,var(--danger))}
.leave-stat-card.purple::before{background:linear-gradient(90deg,#6a1788,var(--purple))}
.leave-stat-card.blue::before{background:linear-gradient(90deg,#c8102e,var(--accent))}
.leave-stat-card.warn::before{background:linear-gradient(90deg,#d97706,#f59e0b)}
.ls-icon{font-size:24px;margin-bottom:8px}
.ls-num{font-size:32px;font-weight:700;font-family:'JetBrains Mono',monospace;line-height:1}
.ls-label{color:var(--muted);font-size:11px;margin-top:6px}
.ls-sub{font-size:10px;font-family:'JetBrains Mono',monospace;margin-top:4px}

/* PROGRESS */
.leave-progress-wrap{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:22px;margin-bottom:24px}
.leave-progress-wrap h3{font-size:14px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center}
.progress-bar-bg{background:rgba(200,16,46,.06);border-radius:999px;height:12px;overflow:hidden}
.progress-bar-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,var(--accent2),var(--accent));transition:width .6s ease}
.progress-bar-fill.warn-fill{background:linear-gradient(90deg,#d97706,#f59e0b)}
.progress-bar-fill.danger-fill{background:linear-gradient(90deg,#dc2626,var(--danger))}
.progress-labels{display:flex;justify-content:space-between;margin-top:8px}
.progress-labels span{font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--muted)}

/* LEAVE TYPE BADGES */
.leave-type-badge{padding:3px 10px;border-radius:20px;font-size:10px;font-family:'JetBrains Mono',monospace}
.leave-sick{background:rgba(239,68,68,.15);color:#f87171}
.leave-vacation{background:rgba(200,16,46,.15);color:var(--accent)}
.leave-emergency{background:rgba(245,158,11,.15);color:var(--warn)}
.leave-maternity{background:rgba(167,139,250,.15);color:var(--purple)}
.leave-other{background:rgba(136,146,176,.15);color:var(--muted)}

/* DATE CHIPS */
.date-chips{display:flex;flex-wrap:wrap;gap:4px}
.date-chip{padding:3px 8px;background:rgba(167,139,250,.15);color:var(--purple);border-radius:6px;font-size:10px;font-family:'JetBrains Mono',monospace}

/* MODALS */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
.modal-overlay.open{display:flex}
.modal{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:32px;width:100%;max-width:480px;animation:slideIn .3s ease;max-height:90vh;overflow-y:auto}
.modal.modal-wide{max-width:620px}
@keyframes slideIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
.modal h2{font-size:18px;margin-bottom:24px}
.modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:20px}

/* DROP ZONES */
.drop-zone{border:2px dashed var(--border);border-radius:12px;padding:32px;text-align:center;cursor:pointer;transition:all .2s;margin-bottom:16px}
.drop-zone:hover,.drop-zone.dragover{border-color:var(--accent);background:rgba(200,16,46,.04)}
.drop-zone p{color:var(--muted);font-size:13px;margin-top:8px}
.drop-zone span{font-size:32px}
.drop-zone-sm{border:2px dashed var(--border);border-radius:10px;padding:16px;text-align:center;cursor:pointer;transition:all .2s}
.drop-zone-sm:hover{border-color:var(--purple);background:rgba(167,139,250,.05)}
.drop-zone-sm .dz-icon{font-size:24px}
.drop-zone-sm p{color:var(--muted);font-size:12px;margin-top:4px}

/* CALENDAR */
.date-picker-wrap{border:1px solid var(--border);border-radius:9px;background:var(--surface);overflow:hidden}
.date-picker-header{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--border)}
.date-picker-header span{font-size:13px;font-weight:600}
.date-nav-btn{background:none;border:none;color:var(--muted);cursor:pointer;font-size:16px;padding:2px 6px;border-radius:4px}
.date-nav-btn:hover{color:var(--accent);background:rgba(200,16,46,.08)}
.date-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;padding:10px}
.day-label{text-align:center;font-size:10px;font-family:'JetBrains Mono',monospace;color:var(--muted);padding:4px 0}
.day-cell{aspect-ratio:1;display:flex;align-items:center;justify-content:center;font-size:12px;border-radius:6px;cursor:pointer;transition:all .15s;font-family:'JetBrains Mono',monospace}
.day-cell:hover:not(.empty){background:rgba(167,139,250,.15);color:var(--purple)}
.day-cell.selected{background:rgba(167,139,250,.25);color:var(--purple);border:1px solid rgba(167,139,250,.5);font-weight:700}
.day-cell.today{color:var(--accent);font-weight:700}
.day-cell.empty{opacity:.2;cursor:default}
.selected-dates-display{padding:8px 14px;border-top:1px solid var(--border);min-height:36px}
.selected-dates-display .no-dates{color:var(--muted);font-size:11px;font-style:italic}

/* SUCCESS MODALS */
.success-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:2000;align-items:center;justify-content:center;backdrop-filter:blur(6px)}
.success-modal-overlay.open{display:flex}
.success-modal{background:var(--card);border:1px solid var(--border);border-radius:24px;padding:40px 36px;width:100%;max-width:420px;text-align:center;position:relative;overflow:hidden;animation:successPop .4s cubic-bezier(.175,.885,.32,1.275)}
@keyframes successPop{from{opacity:0;transform:scale(.7) translateY(30px)}to{opacity:1;transform:scale(1) translateY(0)}}
.success-modal.doc-success::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#c8102e,var(--accent),#c8102e);background-size:200% 100%;animation:shimmer 2s infinite}
.success-modal.leave-success::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#7c3aed,var(--purple),#7c3aed);background-size:200% 100%;animation:shimmer 2s infinite}
@keyframes shimmer{0%{background-position:-200% 0}100%{background-position:200% 0}}
.success-icon-wrap{width:88px;height:88px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:40px;position:relative}
.success-icon-wrap.doc-icon{background:rgba(200,16,46,.12);border:2px solid rgba(200,16,46,.25)}
.success-icon-wrap.leave-icon{background:rgba(167,139,250,.12);border:2px solid rgba(167,139,250,.25)}
.success-icon-wrap::before{content:'';position:absolute;inset:-8px;border-radius:50%;border:2px solid transparent;animation:pulseRing 2s ease-out infinite}
.doc-icon::before{border-color:rgba(200,16,46,.3)}
.leave-icon::before{border-color:rgba(167,139,250,.3)}
@keyframes pulseRing{0%{transform:scale(1);opacity:1}100%{transform:scale(1.4);opacity:0}}
.check-badge{position:absolute;bottom:-4px;right:-4px;width:28px;height:28px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;border:2px solid var(--card);animation:checkBounce .5s .3s cubic-bezier(.175,.885,.32,1.275) both}
@keyframes checkBounce{from{transform:scale(0)}to{transform:scale(1)}}
.success-title{font-size:20px;font-weight:700;margin-bottom:8px;line-height:1.2}
.success-subtitle{color:var(--muted);font-size:13px;line-height:1.6;margin-bottom:24px}
.success-detail-pill{display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:12px;font-size:12px;font-family:'JetBrains Mono',monospace;margin-bottom:28px}
.success-detail-pill.doc-pill{background:rgba(200,16,46,.1);border:1px solid rgba(200,16,46,.2);color:var(--accent)}
.success-detail-pill.leave-pill{background:rgba(167,139,250,.1);border:1px solid rgba(167,139,250,.2);color:var(--purple)}
.success-close-btn{width:100%;padding:12px;border-radius:12px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:all .2s}
.success-close-btn.doc-close{background:linear-gradient(135deg,#c8102e,var(--accent));color:#fff}
.success-close-btn.doc-close:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(155,12,34,.4)}
.success-close-btn.leave-close{background:linear-gradient(135deg,#6a1788,var(--purple));color:#fff}
.success-close-btn.leave-close:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(124,58,237,.4)}
.confetti-container{position:absolute;inset:0;pointer-events:none;overflow:hidden;border-radius:24px}
.confetti-dot{position:absolute;width:6px;height:6px;border-radius:2px;animation:confettiFall 1.5s ease-out forwards}
@keyframes confettiFall{0%{transform:translateY(-20px) rotate(0deg);opacity:1}100%{transform:translateY(300px) rotate(720deg);opacity:0}}